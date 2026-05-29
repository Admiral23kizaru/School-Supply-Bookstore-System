<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    // header('Location: ../index.php');
    // exit;
}
require_once __DIR__ . '/../db/db.php';

$email = $_SESSION['email'] ?? '';
$customer_id = (int) ($_SESSION['user_id'] ?? 0);

$appBaseUrl = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

function ensureProfileImageColumn($conn, string $table): void
{
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'profile_image_url'");
        if ($check && (int) $check->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` ADD profile_image_url varchar(255) DEFAULT NULL");
        }
    } catch (Throwable $e) {
        // Ignore to avoid breaking the portal.
    }
}

function handleProfileImageUpload(array $file, string $appBaseUrl): ?string
{
    if (empty($file['tmp_name']) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!$mime || !isset($allowed[$mime])) {
        return null;
    }

    $maxBytes = 2 * 1024 * 1024; // 2MB
    if (!empty($file['size']) && (int) $file['size'] > $maxBytes) {
        return null;
    }

    $uploadDir = __DIR__ . '/../assets/uploads/profiles';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_dir($uploadDir)) {
        return null;
    }

    $ext = $allowed[$mime];
    $filename = 'profile_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }

    return $appBaseUrl . '/assets/uploads/profiles/' . $filename;
}

ensureProfileImageColumn($conn, 'customers');

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Resolve/validate customer_id so "My Orders" shows newly created orders reliably.
if ($customer_id <= 0 && $email !== '') {
    $stmtResolve = $conn->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
    if ($stmtResolve) {
        $stmtResolve->bind_param("s", $email);
        $stmtResolve->execute();
        $resResolve = $stmtResolve->get_result();
        $rowResolve = $resResolve->fetch_assoc();
        if ($rowResolve && isset($rowResolve['id'])) {
            $customer_id = (int) $rowResolve['id'];
        }
    }
} else if ($customer_id > 0) {
    // If session user_id doesn't exist in customers, fall back to email.
    $stmtCheck = $conn->prepare("SELECT id FROM customers WHERE id = ? LIMIT 1");
    if ($stmtCheck) {
        $stmtCheck->bind_param("i", $customer_id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $rowCheck = $resCheck->fetch_assoc();
        if (!$rowCheck && $email !== '') {
            $stmtResolve = $conn->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
            if ($stmtResolve) {
                $stmtResolve->bind_param("s", $email);
                $stmtResolve->execute();
                $resResolve = $stmtResolve->get_result();
                $rowResolve = $resResolve->fetch_assoc();
                if ($rowResolve && isset($rowResolve['id'])) {
                    $customer_id = (int) $rowResolve['id'];
                } else {
                    $customer_id = 0;
                }
            } else {
                $customer_id = 0;
            }
        }
    } else if ($email !== '') {
        $stmtResolve = $conn->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        if ($stmtResolve) {
            $stmtResolve->bind_param("s", $email);
            $stmtResolve->execute();
            $resResolve = $stmtResolve->get_result();
            $rowResolve = $resResolve->fetch_assoc();
            if ($rowResolve && isset($rowResolve['id'])) {
                $customer_id = (int) $rowResolve['id'];
            } else {
                $customer_id = 0;
            }
        }
    }
}

if ($customer_id > 0) {
    $_SESSION['user_id'] = $customer_id;
}

$customerName = 'Customer Account';
$customerProfileImageUrl = '';
$customerAddress = '';
if ($customer_id > 0) {
    $custStmt = $conn->prepare("SELECT name, email, address, profile_image_url FROM customers WHERE id = ? LIMIT 1");
    if ($custStmt) {
        $custStmt->bind_param("i", $customer_id);
        $custStmt->execute();
        $custRes = $custStmt->get_result();
        if ($row = $custRes->fetch_assoc()) {
            $customerName = $row['name'] ?: $customerName;
            $email = $row['email'] ?: $email;
            $customerAddress = $row['address'] ?? '';
            $customerProfileImageUrl = $row['profile_image_url'] ?? '';
        }
    }
}

// Handle profile update (name/email + optional image upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $newName = trim($_POST['name'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');

    if ($customer_id > 0 && $newName !== '' && $newEmail !== '') {
        $uploadedUrl = null;
        if (isset($_FILES['image']) && is_array($_FILES['image'])) {
            $uploadedUrl = handleProfileImageUpload($_FILES['image'], $appBaseUrl);
        }

        // Password Change Logic
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmNewPassword = $_POST['confirm_new_password'] ?? '';
        $passwordToUpdate = null;
        $errorRedirect = '';

        if ($currentPassword !== '' || $newPassword !== '' || $confirmNewPassword !== '') {
            $stmt = $conn->prepare("SELECT password FROM customers WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($user = $res->fetch_assoc()) {
                    if (!password_verify($currentPassword, $user['password'])) {
                        $errorRedirect = '&error=wrong_password';
                    } elseif ($newPassword !== $confirmNewPassword) {
                        $errorRedirect = '&error=password_mismatch';
                    } elseif (strlen($newPassword) < 8) {
                        $errorRedirect = '&error=weak_password';
                    } else {
                        $passwordToUpdate = password_hash($newPassword, PASSWORD_DEFAULT);
                    }
                }
            }
        }

        if ($errorRedirect) {
            header('Location: customer_dashboard.php?' . $errorRedirect);
            exit;
        }

        try {
            if ($uploadedUrl && $passwordToUpdate) {
                $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, profile_image_url = ?, password = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("ssssi", $newName, $newEmail, $uploadedUrl, $passwordToUpdate, $customer_id);
                    $stmt->execute();
                }
            } elseif ($uploadedUrl && !$passwordToUpdate) {
                $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, profile_image_url = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sssi", $newName, $newEmail, $uploadedUrl, $customer_id);
                    $stmt->execute();
                }
            } elseif (!$uploadedUrl && $passwordToUpdate) {
                $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, password = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sssi", $newName, $newEmail, $passwordToUpdate, $customer_id);
                    $stmt->execute();
                }
            } else {
                $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("ssi", $newName, $newEmail, $customer_id);
                    $stmt->execute();
                }
            }
            $_SESSION['email'] = $newEmail;
        } catch (Throwable $e) {
            // Ignore to avoid breaking; user can retry.
        }
    }

    header('Location: customer_dashboard.php');
    exit;
}

function productImageKey(string $value): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value));
}

function productImageUrl(string $filename): string
{
    return '../assets/uploads/products/' . str_replace('%2F', '/', rawurlencode($filename));
}

function productImageUrlIfExists(string $filename): string
{
    return is_file(__DIR__ . '/../assets/uploads/products/' . $filename)
        ? productImageUrl($filename)
        : '';
}

function productImagePathFromStoredUrl(string $url): string
{
    $path = urldecode(parse_url($url, PHP_URL_PATH) ?: $url);
    $assetPos = strpos($path, '/assets/uploads/products/');
    if ($assetPos !== false) {
        return __DIR__ . '/..' . substr($path, $assetPos);
    }

    return __DIR__ . '/../' . ltrim(preg_replace('#^\.\./#', '', $path), '/\\');
}

function resolveProductImage(array $row, array $productImages, array $autoProductImages): string
{
    $img = trim((string) ($row['image_url'] ?? ''));
    if ($img !== '') {
        if (preg_match('#^https?://#i', $img) || is_file(productImagePathFromStoredUrl($img))) {
            return $img;
        }
    }

    $name = (string) ($row['name'] ?? '');
    if (isset($productImages[$name])) {
        $mapped = productImageUrlIfExists($productImages[$name]);
        if ($mapped !== '') {
            return $mapped;
        }
    }

    $keys = [
        productImageKey($name),
        productImageKey(preg_replace('/\s*\([^)]*\)/', '', $name)),
    ];

    foreach ($keys as $key) {
        if ($key !== '' && isset($autoProductImages[$key])) {
            return $autoProductImages[$key];
        }
    }

    foreach ($autoProductImages as $key => $path) {
        if ($key !== '' && ($keys[0] === $key || strpos($keys[0], $key) === 0 || strpos($key, $keys[0]) === 0)) {
            return $path;
        }
    }

    $categoryKey = productImageKey((string) ($row['category'] ?? ''));
    if (strpos($keys[0], 'tape') !== false || strpos($keys[0], 'glue') !== false || strpos($categoryKey, 'supply') !== false) {
        return productImageUrlIfExists('Scotch Tape.jpg');
    }
    if (strpos($keys[0], 'pen') !== false || strpos($keys[0], 'pencil') !== false || strpos($keys[0], 'marker') !== false || strpos($categoryKey, 'writing') !== false) {
        return productImageUrlIfExists('Faber-Castell Pencil.jpg');
    }
    if (strpos($keys[0], 'card') !== false) {
        return productImageUrlIfExists('Graphing Paper (50 sheets).jpg');
    }
    if (strpos($categoryKey, 'paper') !== false) {
        return productImageUrlIfExists('Pad Paper (Short).webp');
    }

    return '';
}

// Manual image mapping — filenames from assets/uploads/products/
$productImages = [
    'Yellow Pad (80 leaves)' => 'Yellow Pad (80 leaves).jpg',
    'Intermediate Pad'       => 'Intermediate Pad.webp',
    'Faber-Castell Pencil'   => 'Faber-Castell Pencil.jpg',
    'Ballpoint Pen (12pc)'   => 'Ballpoint Pen (12pc).jpg',
    'Scotch Tape (1 in)'     => 'Scotch Tape.jpg',
    'Index Cards (100pc)'    => 'Index Cards (100pc).jpg',
];
$autoProductImages = [];
$productImageDir = __DIR__ . '/../assets/uploads/products';
$allowedProductImageExts = ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'gif', 'bmp', 'avif', 'svg', 'ico'];
foreach (scandir($productImageDir) ?: [] as $filename) {
    $imagePath = $productImageDir . DIRECTORY_SEPARATOR . $filename;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!is_file($imagePath) || !in_array($ext, $allowedProductImageExts, true)) {
        continue;
    }
    $autoProductImages[productImageKey(pathinfo($filename, PATHINFO_FILENAME))] = productImageUrl($filename);
}

// Fetch products from database
$productsData = [];
$seenProducts = [];
$res = $conn->query("SELECT id, name, category, description AS desc_text, price, stock, status, image_url FROM products ORDER BY name ASC, id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $productKey = productImageKey($row['name']);
        if (isset($seenProducts[$productKey])) {
            continue;
        }
        $seenProducts[$productKey] = true;
        $img = resolveProductImage($row, $productImages, $autoProductImages);
        $productsData[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['stock'],
            'status' => $row['status'],
            'desc' => $row['desc_text'],
            'image' => $img
        ];
    }
}

// Fetch orders from database
$ordersData = [];
$stmt = $conn->prepare("
    SELECT o.id, DATE_FORMAT(o.created_at, '%b %d, %Y') as date, o.total_amount as total, o.status,
           (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ')
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = o.id) as items
    FROM orders o
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
");
if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $resOrder = $stmt->get_result();
    while ($row = $resOrder->fetch_assoc()) {
        $ordersData[] = [
            'id' => $row['id'],
            'date' => $row['date'],
            'items' => $row['items'] ? $row['items'] : 'No items',
            'total' => (float)$row['total'],
            'status' => $row['status']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - School Supply</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        [x-cloak] { display: none !important; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8f9fa; 
        }
        
        .sidebar { width: 260px; flex-shrink: 0; background-color: #1a1a1a; transition: all 0.3s ease; }
        .bg-dark-custom { background-color: #1a1a1a !important; color: white !important;}
        .bg-dark-custom:hover { background-color: #000 !important; color: white !important;}
        .text-dark-custom { color: #1a1a1a !important; }
        .text-gray-400 { color: #9ca3af !important; }
        .text-gray-500 { color: #6b7280 !important; }
        
        .nav-btn {
            width: 100%; text-align: left; background: none; border: none;
            color: #d1d5db; padding: 10px 16px; border-radius: 0.75rem;
            display: flex; align-items: center; gap: 12px; font-weight: 500;
            transition: all 0.2s; font-size: 14px; margin-bottom: 4px;
        }
        .nav-btn:hover { background-color: rgba(255,255,255,0.1); color: white; }
        .nav-btn.active { background-color: white; color: #1a1a1a; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .nav-btn i { font-size: 18px; }

        .card-custom { border-radius: 1rem; border: 1px solid #f3f4f6; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn-custom { border-radius: 0.75rem; padding: 10px 20px; font-size: 14px; font-weight: 600; }
        
        /* Mobile Patterns */
        .mobile-top-header { display: none; background: #1a1a1a; color: #fff; padding: 10px 16px; align-items: center; justify-content: space-between; position: fixed; top: 0; left: 0; width: 100%; z-index: 1040; border-bottom: 1px solid #374151; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1045; backdrop-filter: blur(2px); }
        .hamburger-btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; color: #fff; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; cursor: pointer; }
        .hamburger-btn:hover { background: rgba(255,255,255,0.2); }
        .hamburger-btn i { font-size: 20px; }

        @media (max-width: 991.98px) {
            .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 1050; height: 100vh !important; transition: transform 0.3s cubic-bezier(.4,0,.2,1); box-shadow: 10px 0 30px rgba(0,0,0,0.2); }
            .sidebar.mobile-show { transform: translateX(0); }
            .mobile-top-header { display: flex; }
            .sidebar-overlay { display: block; }
            main { padding-top: 60px; height: calc(100vh - 60px) !important; }
        }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .text-nowrap { white-space: nowrap; }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    </style>
</head>
<body class="d-flex vh-100 overflow-hidden" x-data="dashboardData()">

    <!-- Mobile Top Header -->
    <div class="mobile-top-header">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-white text-dark rounded-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-journal-bookmark-fill" style="font-size: 14px;"></i>
            </div>
            <div>
                <span class="fw-bold" style="font-size:14px;">School Supply</span>
                <span class="d-block" style="font-size:10px;color:#9ca3af;margin-top:-2px;">Customer Portal</span>
            </div>
        </div>
        <div class="hamburger-btn" @click="mobileMenuOpen = true">
            <i class="bi bi-list"></i>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" x-show="mobileMenuOpen" x-cloak @click="mobileMenuOpen = false"></div>

    <!-- Sidebar -->
    <aside class="sidebar text-white d-flex flex-column justify-content-between h-100" :class="mobileMenuOpen ? 'mobile-show' : ''">
        <div>
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between px-4 border-bottom" style="height: 80px; border-color: #374151 !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white text-dark rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-journal-bookmark-fill fs-5"></i>
                    </div>
                    <div>
                        <h1 class="h6 mb-0 fw-bold">School Supply</h1>
                        <p class="mb-0 text-gray-400 fw-medium" style="font-size: 11px;">Customer Portal</p>
                    </div>
                </div>
                <!-- Close Button (Mobile Only) -->
                <button class="btn btn-link text-white d-lg-none p-0 border-0" @click="mobileMenuOpen = false">
                    <i class="bi bi-x-lg fs-5"></i>
                </button>
            </div>

            <!-- Navigation -->
            <div class="px-3 py-4">
                <p class="text-gray-500 fw-bold text-uppercase px-3 mb-3" style="font-size: 11px; letter-spacing: 0.5px;">Menu</p>
                <nav>
                    <button @click="tab = 'shop'" :class="tab === 'shop' ? 'active' : ''" class="nav-btn">
                        <i class="bi bi-cart3"></i> Shop
                    </button>
                    <button @click="tab = 'orders'" :class="tab === 'orders' ? 'active' : ''" class="nav-btn">
                        <i class="bi bi-receipt"></i> My Orders
                    </button>
                    <button @click="tab = 'cart'" :class="tab === 'cart' ? 'active' : ''" class="nav-btn justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-bag"></i> Cart
                        </div>
                        <span :class="[cartCount > 0 ? 'd-flex' : 'd-none', tab === 'cart' ? 'bg-dark text-white' : 'bg-secondary']" class="badge rounded-circle align-items-center justify-content-center" 
                              style="width:20px; height:20px; font-size:10px; padding:0;" x-text="cartCount"></span>
                    </button>
                </nav>
            </div>
        </div>

        <!-- Footer / Account -->
        <div class="px-3 pb-4">
            <div class="d-flex align-items-center gap-3 px-3 py-2 rounded-3 mb-2" style="cursor: pointer;">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-gray-400" style="width: 36px; height: 36px; background-color: #374151; overflow:hidden;">
                    <?php if (!empty($customerProfileImageUrl)): ?>
                        <img src="<?= esc($customerProfileImageUrl) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person"></i>
                    <?php endif; ?>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <h4 class="mb-0 text-white fw-semibold text-truncate" style="font-size: 13px;"><?= esc($customerName) ?></h4>
                    <p class="mb-0 text-gray-400 text-truncate" style="font-size: 11px;"><?= htmlspecialchars($email) ?></p>
                </div>
            </div>
            <button type="button" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 mb-2" data-bs-toggle="modal" data-bs-target="#profileModal" style="font-size: 13px; border-radius: 10px;">
                <i class="bi bi-pencil-square"></i> Edit Profile
            </button>
            <a href="../logout.php" class="d-flex align-items-center gap-3 px-3 py-2 text-gray-400 text-decoration-none fw-medium" style="font-size: 13px; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#9ca3af'">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 card-custom overflow-hidden" style="border-radius: 1rem;">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="modal-header border-0">
                        <div>
                            <h6 class="mb-0 fw-bold">Edit Profile</h6>
                            <div class="text-secondary small">Update your name, email, and profile image</div>
                        </div>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= esc($customerName) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= esc($email) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Profile Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <div class="text-muted small mt-1">Optional</div>
                        </div>
                        <?php if (!empty($customerProfileImageUrl)): ?>
                            <div class="mb-3 text-center">
                                <div class="text-secondary small fw-semibold mb-2">Current image</div>
                                <div style="width:160px;height:160px;margin:0 auto;border-radius:12px;overflow:hidden;border:1px solid #e7e8ec;">
                                    <img src="<?= esc($customerProfileImageUrl) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                                </div>
                            </div>
                        <?php endif; ?>
                        <hr>
                        <div class="mb-3">
                            <div class="fw-bold mb-1">Change Password</div>
                            <div class="text-muted small mb-2">Leave blank if you don't want to change your password.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small">Current password</label>
                            <input type="password" name="current_password" class="form-control" autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small">New password</label>
                            <input type="password" name="new_password" class="form-control" autocomplete="new-password">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-secondary small">Confirm new password</label>
                            <input type="password" name="confirm_new_password" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light border fw-semibold flex-fill btn-custom text-secondary shadow-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn bg-dark-custom btn-custom flex-fill text-white d-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check-circle"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="flex-grow-1 d-flex flex-column h-100 position-relative bg-white" style="background-color: rgba(255,255,255,0.5) !important;">
        <div class="flex-grow-1 overflow-auto w-100 p-4 p-md-5">
            <?php include __DIR__ . '/shop.php'; ?>
            <?php include __DIR__ . '/my_orders.php'; ?>
            <?php include __DIR__ . '/carts.php'; ?>
        </div>
    </main>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true" x-ref="bsModal">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 card-custom overflow-hidden" style="border-radius: 1rem;">
            <template x-if="selectedProduct">
                <div class="modal-body p-4 p-sm-4">
                    <!-- Modal Header -->
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0 fw-bold fs-6">Product Details</h6>
                        <button type="button" class="btn-close shadow-none" @click="closeModal()"></button>
                    </div>

                    <!-- Image -->
                    <template x-if="selectedProduct.image">
                        <div class="bg-light rounded-4 d-flex align-items-center justify-content-center mb-4 border" style="height: 160px; border-radius:0.75rem; overflow:hidden;">
                            <img :src="selectedProduct.image" @error="selectedProduct.image = ''" alt="" style="max-width:100%; max-height:100%; object-fit:contain;">
                        </div>
                    </template>
                    <template x-if="!selectedProduct.image">
                        <div class="bg-light rounded-4 d-flex align-items-center justify-content-center mb-4 border" style="height: 160px; border-radius:0.75rem;">
                            <i class="bi bi-journal-text" style="font-size: 40px; color: #dee2e6;"></i>
                        </div>
                    </template>

                    <!-- Info -->
                    <div class="mb-4">
                        <span class="text-gray-400 fw-bold text-uppercase mb-1 d-block" style="font-size: 10px; letter-spacing: 1px;" x-text="selectedProduct.category"></span>
                        <h4 class="fw-bold text-dark lh-sm mb-2 fs-5" x-text="selectedProduct.name"></h4>
                        <p class="text-secondary small mb-0" style="line-height: 1.6;" x-text="selectedProduct.desc"></p>
                    </div>

                    <!-- Pricing box -->
                    <div class="bg-light border rounded-3 p-3 d-flex align-items-center justify-content-between mb-4" style="border-radius:0.75rem;">
                        <div>
                            <p class="text-gray-400 fw-bold text-uppercase mb-0" style="font-size: 10px;">Price</p>
                            <p class="text-dark mb-0 fs-5" x-text="'₱' + selectedProduct.price.toFixed(2)" style="font-weight: 900;"></p>
                        </div>
                        <div class="text-end">
                            <p class="text-gray-400 fw-bold text-uppercase mb-0" style="font-size: 10px;">Stock</p>
                            <p class="fw-bold text-dark mb-0 mt-0" style="font-size: 13px;" x-text="selectedProduct.stock + ' units'"></p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2">
                        <button type="button" @click="closeModal()" class="btn btn-light border fw-semibold flex-fill btn-custom text-secondary shadow-sm">Close</button>
                        <button type="button" @click="addToCart(selectedProduct)" class="btn bg-dark-custom btn-custom flex-fill text-white d-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </template>
        </div>
      </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Alpine.js Application Logic -->
    <script>
        document.addEventListener('alpine:init', () => {
             // Let Alpine initialize naturally before mapping the modal
        });
        
        function dashboardData() {
            return {
                tab: 'shop',
                search: '',
                perPage: 10,
                productPage: 1,
                orderPage: 1,
                orderStatusFilter: '',
                cart: [],
                checkoutForm: {
                    name: <?php echo json_encode($customerName); ?>,
                    email: <?php echo json_encode($email); ?>,
                    address: <?php echo json_encode($customerAddress); ?>,
                    payment_method: 'Cash'
                },
                modalInstance: null,
                selectedProduct: null,
                isLoading: false,
                mobileMenuOpen: false,
                
                products: <?php echo json_encode($productsData); ?>,
                orders: <?php echo json_encode($ordersData); ?>,

                init() {
                    // Initialize Bootstrap Modal
                    this.$nextTick(() => {
                        this.modalInstance = new bootstrap.Modal(this.$refs.bsModal, {
                            backdrop: 'static'
                        });
                    });
                },

                get filteredProducts() {
                    if(!this.search.trim()) return this.products;
                    const q = this.search.toLowerCase();
                    return this.products.filter(p => p.name.toLowerCase().includes(q) || p.category.toLowerCase().includes(q));
                },

                get totalProductPages() {
                    return Math.max(1, Math.ceil(this.filteredProducts.length / this.perPage));
                },

                get paginatedProducts() {
                    const safePage = Math.min(this.productPage, this.totalProductPages);
                    const start = (safePage - 1) * this.perPage;
                    return this.filteredProducts.slice(start, start + this.perPage);
                },

                get productRangeStart() {
                    if (this.filteredProducts.length === 0) return 0;
                    return ((Math.min(this.productPage, this.totalProductPages) - 1) * this.perPage) + 1;
                },

                get productRangeEnd() {
                    if (this.filteredProducts.length === 0) return 0;
                    return Math.min(this.productRangeStart + this.perPage - 1, this.filteredProducts.length);
                },

                get filteredOrders() {
                    const statusOrder = { Pending: 1, Processing: 2, Delivered: 3, Fulfilled: 3, Cancelled: 4 };
                    let list = [...this.orders];
                    if (this.orderStatusFilter) {
                        list = list.filter(o => o.status === this.orderStatusFilter);
                    }
                    list.sort((a, b) => {
                        const diff = (statusOrder[a.status] ?? 99) - (statusOrder[b.status] ?? 99);
                        return diff !== 0 ? diff : 0;
                    });
                    return list;
                },

                get totalOrderPages() {
                    return Math.max(1, Math.ceil(this.filteredOrders.length / this.perPage));
                },

                get paginatedOrders() {
                    const safePage = Math.min(this.orderPage, this.totalOrderPages);
                    const start = (safePage - 1) * this.perPage;
                    return this.filteredOrders.slice(start, start + this.perPage);
                },

                get orderRangeStart() {
                    if (this.filteredOrders.length === 0) return 0;
                    return ((Math.min(this.orderPage, this.totalOrderPages) - 1) * this.perPage) + 1;
                },

                get orderRangeEnd() {
                    if (this.filteredOrders.length === 0) return 0;
                    return Math.min(this.orderRangeStart + this.perPage - 1, this.filteredOrders.length);
                },

                prevProductPage() {
                    this.productPage = Math.max(1, this.productPage - 1);
                },

                nextProductPage() {
                    this.productPage = Math.min(this.totalProductPages, this.productPage + 1);
                },

                prevOrderPage() {
                    this.orderPage = Math.max(1, this.orderPage - 1);
                },

                nextOrderPage() {
                    this.orderPage = Math.min(this.totalOrderPages, this.orderPage + 1);
                },

                get cartTotal() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                },

                get cartCount() {
                    return this.cart.reduce((sum, item) => sum + item.qty, 0);
                },

                openModal(product) {
                    this.selectedProduct = product;
                    if(this.modalInstance) {
                        this.modalInstance.show();
                    }
                },

                closeModal() {
                    if(this.modalInstance) {
                        this.modalInstance.hide();
                    }
                    setTimeout(() => { this.selectedProduct = null; }, 300);
                },

                addToCart(product) {
                    if(product.stock <= 0 || product.status === 'Out of Stock') {
                        alert('This product is currently out of stock.');
                        return;
                    }
                    const existing = this.cart.find(i => i.id === product.id);
                    if(existing) {
                        if(existing.qty >= product.stock) {
                            alert('You have reached the available stock for this product.');
                            return;
                        }
                        existing.qty++;
                    } else {
                        // Create a reactive clone
                        this.cart.push(JSON.parse(JSON.stringify({...product, qty: 1})));
                    }
                    this.closeModal();
                },

                updateQty(id, change) {
                    const index = this.cart.findIndex(i => i.id === id);
                    if(index > -1) {
                        const product = this.products.find(p => p.id === id);
                        if(change > 0 && product && this.cart[index].qty >= product.stock) {
                            alert('You have reached the available stock for this product.');
                            return;
                        }
                        this.cart[index].qty += change;
                    }
                },

                removeFromCart(id) {
                    if (!confirm('Remove this item from your cart?')) return;
                    this.cart = this.cart.filter(i => i.id !== id);
                },

                async checkout() {
                    if (this.cart.length === 0) return;
                    if (this.cart.some(item => item.qty <= 0)) {
                        alert('Please make all cart quantities at least 1 before checkout.');
                        return;
                    }
                    if (!this.checkoutForm.name || !this.checkoutForm.email || !this.checkoutForm.address || !this.checkoutForm.payment_method) {
                        alert('Please complete the customer details before checkout.');
                        return;
                    }
                    this.isLoading = true;
                    
                    try {
                        const response = await fetch('process_checkout.php', {
                            method: 'POST',
                            credentials: 'include',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ cart: this.cart, customer: this.checkoutForm })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('Order placed successfully! Check your My Orders tab.');
                            window.location.reload();
                        } else {
                            const debug = result.debug || null;
                            const message = result.error || 'Failed to process checkout.';
                            const debugLine = debug?.stage && debug?.message ? `\n${debug.stage}: ${debug.message}` : '';
                            alert(message + debugLine);
                            console.error('Checkout failure response:', result);
                        }
                    } catch (error) {
                        console.error(error);
                        alert('A network error occurred.');
                    } finally {
                        this.isLoading = false;
                    }
                },

                async cancelOrder(order) {
                    if (!order || !order.id) return;
                    if (!confirm('Cancel this order?')) return;

                    try {
                        const response = await fetch('cancel_order.php', {
                            method: 'POST',
                            credentials: 'include',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order_id: order.id })
                        });
                        const result = await response.json();

                        if (result.success) {
                            order.status = result.status || 'Cancelled';
                            alert('Order cancelled successfully.');
                        } else {
                            alert(result.error || 'Unable to cancel order.');
                        }
                    } catch (error) {
                        console.error(error);
                        alert('A network error occurred.');
                    }
                }
            }
        }
    </script>
</body>
</html>
