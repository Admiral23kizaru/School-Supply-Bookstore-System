<?php
session_start();
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/mailer_2fa.php';
require_once __DIR__ . '/mailer_verify.php';
require_once __DIR__ . '/activity_logger.php';

function resolveAccountStatus(mysqli $conn, string $table, int $id): string
{
    // Prevent fatal errors if the admin never created the column yet.
    try {
        $colCheck = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'account_status'");
        if ($colCheck && (int) $colCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT account_status FROM `$table` WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                if ($row && isset($row['account_status'])) {
                    $val = (string) $row['account_status'];
                    return $val !== '' ? $val : 'Active';
                }
            }
        }
    } catch (Throwable $e) {
        // Default to Active.
    }
    return 'Active';
}

function ensureApprovalStatusColumn(mysqli $conn, string $table): void
{
    if (!in_array($table, ['customers', 'sellers'], true)) {
        return;
    }
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'approval_status'");
        if ($check && (int) $check->num_rows === 0) {
            // Keep existing users working; new registrations explicitly start as Pending.
            $conn->query("ALTER TABLE `$table` ADD approval_status VARCHAR(20) NOT NULL DEFAULT 'Approved'");
        }
    } catch (Throwable $e) {
        // Ignore to avoid blocking auth flow.
    }
}

function ensureVerificationColumn(mysqli $conn, string $table): void
{
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'is_verified'");
        if ($check && (int) $check->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` ADD is_verified TINYINT(1) DEFAULT 0");
        }
    } catch (Throwable $e) {
        // Ignore to avoid blocking auth flow.
    }
}

function resolveApprovalStatus(mysqli $conn, string $table, int $id): string
{
    if (!in_array($table, ['customers', 'sellers'], true)) {
        return 'Approved';
    }
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'approval_status'");
        if (!$check || (int) $check->num_rows === 0) {
            return 'Approved';
        }
        $stmt = $conn->prepare("SELECT approval_status FROM `$table` WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return 'Approved';
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $status = trim((string) ($row['approval_status'] ?? ''));
        if ($status === '' || strcasecmp($status, 'Approved') === 0) {
            return 'Approved';
        }
        if (strcasecmp($status, 'Rejected') === 0) {
            return 'Rejected';
        }
        return 'Pending';
    } catch (Throwable $e) {
        return 'Approved';
    }
}

function locateUserForReset(mysqli $conn, string $email): ?array
{
    $tables = ['admins', 'sellers', 'customers'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT id FROM `$table` WHERE email = ? LIMIT 1");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && isset($row['id'])) {
            return ['table' => $table, 'id' => (int) $row['id']];
        }
    }
    return null;
}

// Handle GET Request for Email Verification
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'verify_email') {
    $token = trim($_GET['token'] ?? '');
    if ($token !== '') {
        $verified = false;
        $verifiedTable = '';
        $tables = ['customers', 'sellers'];
        foreach ($tables as $table) {
            try {
                $stmt = $conn->prepare("SELECT id FROM `$table` WHERE verification_token = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $token);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        if ($table === 'customers' || $table === 'sellers') {
                            ensureApprovalStatusColumn($conn, $table);
                        }
                        $update = $conn->prepare("UPDATE `$table` SET is_verified = 1, verification_token = NULL WHERE id = ?");
                        if ($update) {
                            $update->bind_param("i", $row['id']);
                            $update->execute();
                            $verified = true;
                            $verifiedTable = $table;
                            break;
                        }
                    }
                }
            } catch (Throwable $e) {}
        }
        
        if ($verified) {
            if ($verifiedTable === 'customers' || $verifiedTable === 'sellers') {
                header('Location: index.php?action=login&success=verified_pending');
            } else {
                header('Location: index.php?action=login&success=verified');
            }
        } else {
            header('Location: index.php?action=login&error=invalid_token');
        }
        exit;
    }
}

// Removing triggerLoginOtp as requested
// POST Request Handler for both Login and Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    if (isset($_POST['forgot_action'])) {
        $forgotAction = (string) ($_POST['forgot_action'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($forgotAction === 'send_otp') {
            $user = locateUserForReset($conn, $email);
            if (!$user) {
                header('Location: index.php?action=forgot&error=email_not_found');
                exit;
            }

            $otp = (string) random_int(100000, 999999);
            $_SESSION['pw_reset'] = [
                'email' => $email,
                'table' => $user['table'],
                'id' => $user['id'],
                'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
                'expires_at' => time() + 600,
                'verified' => false,
            ];

            $sent = sendOtpMail($email, $otp);
            if (!$sent) {
                header('Location: index.php?action=forgot&error=mail_failed');
                exit;
            }

            header('Location: index.php?action=forgot&step=verify&sent=1');
            exit;
        }

        if ($forgotAction === 'verify_otp') {
            $otpInput = trim($_POST['otp'] ?? '');
            $reset = $_SESSION['pw_reset'] ?? null;
            if (!$reset || !isset($reset['otp_hash'], $reset['expires_at'])) {
                header('Location: index.php?action=forgot&error=session_expired');
                exit;
            }
            if (time() > (int) $reset['expires_at']) {
                unset($_SESSION['pw_reset']);
                header('Location: index.php?action=forgot&error=otp_expired');
                exit;
            }
            if (!password_verify($otpInput, (string) $reset['otp_hash'])) {
                header('Location: index.php?action=forgot&step=verify&error=invalid_otp');
                exit;
            }

            $_SESSION['pw_reset']['verified'] = true;
            header('Location: index.php?action=forgot&step=reset');
            exit;
        }

        if ($forgotAction === 'reset_password') {
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            $reset = $_SESSION['pw_reset'] ?? null;

            if (!$reset || empty($reset['verified']) || time() > (int) ($reset['expires_at'] ?? 0)) {
                unset($_SESSION['pw_reset']);
                header('Location: index.php?action=forgot&error=session_expired');
                exit;
            }
            if (strlen($newPassword) < 8) {
                header('Location: index.php?action=forgot&step=reset&error=weak_password');
                exit;
            }
            if ($newPassword !== $confirmPassword) {
                header('Location: index.php?action=forgot&step=reset&error=password_mismatch');
                exit;
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $table = (string) $reset['table'];
            $id = (int) $reset['id'];
            $stmt = $conn->prepare("UPDATE `$table` SET password = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $hash, $id);
                $stmt->execute();
            }

            unset($_SESSION['pw_reset']);
            header('Location: index.php?action=login&reset=1');
            exit;
        }
    }

    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';
    
    error_log("LOGIN ATTEMPT INITIATED for email: $email");

    // --- REGISTRATION LOGIC ---
    if (isset($_POST['username']) && isset($_POST['role'])) {
        error_log("Registration block entered. Username: " . $_POST['username'] . ", Role: " . $_POST['role']);
        $username = trim($_POST['username']);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $role = trim($_POST['role']); // 'Seller', 'Customer'
        
        if ($role === 'Admin') {
            error_log("SECURITY WARNING: Attempted to register as Admin. Blocked.");
            header('Location: index.php?action=register&error=invalid_role');
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $table = 'customers';
        if ($role === 'Seller') $table = 'sellers';
        
        // Ensure columns exist gracefully
        try {
            $conn->query("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL");
            $conn->query("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0");
            $conn->query("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NULL");
            $conn->query("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NULL");
            if ($table === 'customers' || $table === 'sellers') {
                ensureApprovalStatusColumn($conn, $table);
            }
        } catch (Throwable $e) {}

        $token = bin2hex(random_bytes(16));

        // Insert into the respective database table
        if ($table === 'customers' || $table === 'sellers') {
            $stmt = $conn->prepare("INSERT INTO $table (name, first_name, last_name, email, password, verification_token, is_verified, approval_status) VALUES (?, ?, ?, ?, ?, ?, 0, 'Pending')");
            $stmt->bind_param("ssssss", $username, $firstName, $lastName, $email, $hashed_password, $token);
        } else {
            // Admin accounts are immediately verified and do not go through email verification.
            $stmt = $conn->prepare("INSERT INTO $table (name, first_name, last_name, email, password, verification_token, is_verified) VALUES (?, ?, ?, ?, ?, NULL, 1)");
            $stmt->bind_param("sssss", $username, $firstName, $lastName, $email, $hashed_password);
        }
        
        if ($stmt->execute()) {
            error_log("Registration SUCCESS for $email in $table");
            if ($table === 'customers' || $table === 'sellers') {
                sendVerificationMail($email, $token);
                header('Location: index.php?action=login&success=registered_verify');
            } else {
                header('Location: index.php?action=login&success=1');
            }
            exit;
        } else {
            error_log("Registration DB insert FAILED for $email. Error: " . $stmt->error);
            header('Location: index.php?action=register&error=duplicate');
            exit;
        }
    }
    
    // --- LOGIN LOGIC ---
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';
    
    error_log("Login validation starting. Searching tables...");
    
    // Check Admins
    $stmt = $conn->prepare("SELECT id, name, password, is_super_admin FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['role'] = 'admin';
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            $_SESSION['is_super_admin'] = !empty($user['is_super_admin']);
            logActivity($conn, (int)$user['id'], $user['name'] ?? $email, 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully');
            if (!empty($user['is_super_admin'])) {
                header('Location: superadmin/dashboard.php');
            } else {
                header('Location: admin/dashboard.php');
            }
            exit;
        }
        logActivity($conn, (int)$user['id'], $user['name'] ?? $email, 'admin', 'LOGIN_FAILED', 'Failed login attempt for admin — wrong password', 'Failed');
    }
    
    // Check Sellers
    ensureVerificationColumn($conn, 'sellers');
    ensureApprovalStatusColumn($conn, 'sellers');
    $stmt = $conn->prepare("SELECT id, name, password, is_verified FROM sellers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            if (isset($user['is_verified']) && (int) $user['is_verified'] !== 1) {
                header('Location: index.php?action=login&error=unverified');
                exit;
            }
            $approvalStatus = resolveApprovalStatus($conn, 'sellers', (int) $user['id']);
            if ($approvalStatus === 'Pending') {
                header('Location: index.php?action=login&error=pending_approval');
                exit;
            }
            if ($approvalStatus === 'Rejected') {
                header('Location: index.php?action=login&error=approval_rejected');
                exit;
            }
            $acStatus = resolveAccountStatus($conn, 'sellers', (int) $user['id']);
            if ($acStatus !== 'Active') {
                $error = $acStatus === 'Banned' ? 'banned' : 'suspended';
                logActivity($conn, (int)$user['id'], $user['name'] ?? $email, 'seller', 'LOGIN_BLOCKED', 'Seller login blocked — account ' . $acStatus, 'Failed');
                header('Location: index.php?action=login&error=' . $error);
                exit;
            }
            $_SESSION['role'] = 'seller';
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            logActivity($conn, (int)$user['id'], $user['name'] ?? $email, 'seller', 'LOGIN_SUCCESS', 'Seller logged in to portal');
            header('Location: seller/dashboard.php');
            exit;
        }
        logActivity($conn, (int)$user['id'], $user['name'] ?? $email, 'seller', 'LOGIN_FAILED', 'Failed login attempt for seller — wrong password', 'Failed');
    }

    // Check Customers
    ensureVerificationColumn($conn, 'customers');
    ensureApprovalStatusColumn($conn, 'customers');
    $stmt = $conn->prepare("SELECT id, name, password, is_verified FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            if (isset($user['is_verified']) && (int) $user['is_verified'] !== 1) {
                header('Location: index.php?action=login&error=unverified');
                exit;
            }
            $approvalStatus = resolveApprovalStatus($conn, 'customers', (int) $user['id']);
            if ($approvalStatus === 'Pending') {
                header('Location: index.php?action=login&error=pending_approval');
                exit;
            }
            if ($approvalStatus === 'Rejected') {
                header('Location: index.php?action=login&error=approval_rejected');
                exit;
            }
            $acStatus = resolveAccountStatus($conn, 'customers', (int) $user['id']);
            if ($acStatus !== 'Active') {
                $error = $acStatus === 'Banned' ? 'banned' : 'suspended';
                logActivity($conn, (int)$user['id'], $user['name'] ?? $email, 'customer', 'LOGIN_BLOCKED', 'Customer login blocked — account ' . $acStatus, 'Failed');
                header('Location: index.php?action=login&error=' . $error);
                exit;
            }
            // Temporarily ignore check if they have is_verified column unless forced
            $_SESSION['role'] = 'customer';
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            logActivity($conn, (int)$user['id'], $user['name'] ?? $email, 'customer', 'LOGIN_SUCCESS', 'Customer logged in');
            header('Location: customer/customer_dashboard.php');
            exit;
        }
        logActivity($conn, (int)$user['id'], $user['name'] ?? $email, 'customer', 'LOGIN_FAILED', 'Failed login attempt — wrong password', 'Failed');
    }
    
    // Unknown email — log and fail
    logActivity($conn, null, $email, 'unknown', 'LOGIN_FAILED', 'Failed login attempt — email not found', 'Failed');
    header('Location: index.php?action=login&error=invalid');
    exit;
}

$appName = 'School Supply Bookstore System';
$action = isset($_GET['action']) ? (string)$_GET['action'] : 'login';
$action = strtolower(trim($action));
$page = in_array($action, ['register', 'forgot', 'login_otp', 'login_success'], true) ? $action : 'login';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($appName) ?> • <?= $page === 'register' ? 'Register' : 'Login' ?></title>
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
    
    body { 
        font-family: 'Inter', sans-serif; 
        background-color: #f8f9fa; 
    }
    .auth-container { 
        max-width: 450px; 
        width: 100%; 
        margin: auto; 
    }
    .bg-dark-custom { bg-color: #1a1a1a !important; color: white !important; background-color: #1a1a1a !important; }
    .bg-dark-custom:hover { background-color: #000 !important; color: white !important;}
    .text-dark-custom { color: #1a1a1a !important; }
    
    /* Bootstrap overrides to match minimal style */
    .form-control:focus, .form-select:focus { 
        border-color: #1a1a1a; 
        box-shadow: 0 0 0 0.25rem rgba(26, 26, 26, 0.15); 
    }
    .rounded-xl { border-radius: 0.75rem !important; }
    .input-group-text { border-color: #dee2e6; }
    .form-control { border-color: #dee2e6; }
  </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-4">

  <main class="auth-container p-3">
    <div class="card border-0 shadow-sm rounded-xl">
      <div class="card-body p-4 p-sm-5" id="auth-content">
        <?php
          if ($page === 'register') {
            include __DIR__ . '/auth/register.php';
          } else if ($page === 'forgot') {
            include __DIR__ . '/auth/forgot_password.php';
          } else if ($page === 'login_otp') {
            include __DIR__ . '/auth/login_otp.php';
          } else if ($page === 'login_success') {
              $redirUrl = $_SESSION['show_sweetalert_redirect'] ?? 'index.php?action=login';
              unset($_SESSION['show_sweetalert_redirect']);
              // We render a simple loader that fires SweetAlert then redirects
              echo '
              <div class="text-center py-5">
                  <div class="spinner-border text-dark mb-3" role="status"></div>
                  <h4 class="fw-bold">Authenticating...</h4>
              </div>
              <script>
                  document.addEventListener("DOMContentLoaded", function() {
                      Swal.fire({
                          icon: "success",
                          title: "Verified!",
                          text: "Login successful.",
                          timer: 1500,
                          showConfirmButton: false,
                          backdrop: `rgba(0,0,10,0.4)`
                      }).then(() => {
                          window.location.href = "' . htmlspecialchars($redirUrl, ENT_QUOTES, 'UTF-8') . '";
                      });
                  });
              </script>
              ';
          } else {
            include __DIR__ . '/auth/login.php';
          }
        ?>
      </div>
    </div>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="assets/js/background.js"></script>
</body>
</html>
