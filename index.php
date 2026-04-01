<?php
session_start();
require_once __DIR__ . '/db/db.php';

// POST Request Handler for both Login and Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';
    
    error_log("LOGIN ATTEMPT INITIATED for email: $email");

    // --- REGISTRATION LOGIC ---
    if (isset($_POST['username']) && isset($_POST['role'])) {
        error_log("Registration block entered. Username: " . $_POST['username'] . ", Role: " . $_POST['role']);
        $username = trim($_POST['username']);
        $role = trim($_POST['role']); // 'Admin', 'Seller', 'Customer'
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $table = 'customers';
        if ($role === 'Admin') $table = 'admins';
        if ($role === 'Seller') $table = 'sellers';
        
        // Insert into the respective database table
        $stmt = $conn->prepare("INSERT INTO $table (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $hashed_password);
        
        if ($stmt->execute()) {
            error_log("Registration SUCCESS for $email in $table");
            header('Location: index.php?action=login&success=1');
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
    $stmt = $conn->prepare("SELECT id, name, password FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        error_log("User found in admins table. Verifying password...");
        if (password_verify($password, $user['password'])) {
            error_log("Admin authenticated successfully. Redirecting.");
            $_SESSION['role'] = 'admin';
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            header('Location: admin/index.php');
            exit;
        }
        error_log("Admin password validation failed!");
    }
    
    // Check Sellers
    $stmt = $conn->prepare("SELECT id, name, password FROM sellers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        error_log("User found in sellers table. Verifying password...");
        if (password_verify($password, $user['password'])) {
            error_log("Seller authenticated successfully. Redirecting.");
            $_SESSION['role'] = 'seller';
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            header('Location: seller/index.php');
            exit;
        }
        error_log("Seller password validation failed!");
    }

    // Check Customers
    $stmt = $conn->prepare("SELECT id, name, password FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        error_log("User found in customers table. Verifying password...");
        if (password_verify($password, $user['password'])) {
            error_log("Customer authenticated successfully. Redirecting.");
            $_SESSION['role'] = 'customer';
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            header('Location: customer/customer_dashboard.php');
            exit;
        }
        error_log("Customer password validation failed!");
    }
    
    // If we get here, login failed.
    error_log("Login exhausted all tables. Failure triggered.");
    header('Location: index.php?action=login&error=invalid');
    exit;
}

$appName = 'School Supply Bookstore System';
$action = isset($_GET['action']) ? (string)$_GET['action'] : 'login';
$action = strtolower(trim($action));
$page = $action === 'register' ? 'register' : 'login';
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
          } else {
            include __DIR__ . '/auth/login.php';
          }
        ?>
      </div>
    </div>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
