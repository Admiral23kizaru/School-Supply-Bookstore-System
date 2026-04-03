<?php
session_start();
require_once __DIR__ . '/../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../index.php?action=login');
    exit;
}

$sellerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$sellerEmail = $_SESSION['email'] ?? 'seller@gmail.com';

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Base URL of the project (used for saving `products.image_url`).
// Example: /proj/School-Supply-Bookstore-System
$appBaseUrl = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

function productStatusFromStock(int $stock): string
{
    if ($stock <= 0) {
        return 'Out of Stock';
    }
    if ($stock <= 10) {
        return 'Low Stock';
    }
    return 'In Stock';
}

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

ensureProfileImageColumn($conn, 'sellers');

function handleProductImageUpload(array $file, string $appBaseUrl): ?string
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

    $uploadDir = __DIR__ . '/../assets/uploads/products';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_dir($uploadDir)) {
        return null;
    }

    $ext = $allowed[$mime];
    $filename = 'product_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }

    return $appBaseUrl . '/assets/uploads/products/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $stock = (int) ($_POST['stock'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'pc');
        $status = productStatusFromStock($stock);
        $imageUrl = '';
        if (isset($_FILES['image']) && is_array($_FILES['image'])) {
            $uploaded = handleProductImageUpload($_FILES['image'], $appBaseUrl);
            if ($uploaded) {
                $imageUrl = $uploaded;
            }
        }

        if ($name !== '' && $category !== '' && $price > 0) {
            $desc = 'Unit: ' . $unit;
            $stmt = $conn->prepare(
                "INSERT INTO products (seller_id, name, category, description, price, stock, status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if ($stmt) {
                $stmt->bind_param("isssdiss", $sellerId, $name, $category, $desc, $price, $stock, $status, $imageUrl);
                $stmt->execute();
            }
        }

        header('Location: index.php?tab=inventory');
        exit;
    }

    if ($action === 'edit_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $stock = (int) ($_POST['stock'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'pc');
        $status = productStatusFromStock($stock);
        $newImageUrl = '';
        if (isset($_FILES['image']) && is_array($_FILES['image'])) {
            $uploaded = handleProductImageUpload($_FILES['image'], $appBaseUrl);
            if ($uploaded) {
                $newImageUrl = $uploaded;
            }
        }

        if ($id > 0 && $name !== '' && $category !== '' && $price > 0) {
            $desc = 'Unit: ' . $unit;

            if ($newImageUrl !== '') {
                $stmt = $conn->prepare(
                    "UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ?, status = ?, image_url = ? WHERE id = ? AND (seller_id = ? OR seller_id IS NULL)"
                );
                if ($stmt) {
                    $stmt->bind_param("sssdissii", $name, $category, $desc, $price, $stock, $status, $newImageUrl, $id, $sellerId);
                    $stmt->execute();
                }
            } else {
                $stmt = $conn->prepare(
                    "UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ?, status = ? WHERE id = ? AND (seller_id = ? OR seller_id IS NULL)"
                );
                if ($stmt) {
                    $stmt->bind_param("sssdisii", $name, $category, $desc, $price, $stock, $status, $id, $sellerId);
                    $stmt->execute();
                }
            }
        }

        header('Location: index.php?tab=inventory');
        exit;
    }

    if ($action === 'delete_product') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND (seller_id = ? OR seller_id IS NULL)");
            $stmt->bind_param("ii", $id, $sellerId);
            $stmt->execute();
        }
        header('Location: index.php?tab=inventory');
        exit;
    }

    if ($action === 'fulfill_order') {
        $orderId = trim($_POST['order_id'] ?? '');
        if ($orderId !== '') {
            $newStatus = 'Delivered';
            $pendingStatus = 'Pending';
            $processingStatus = 'Processing';
            $stmt = $conn->prepare(
                "UPDATE orders SET status = ? WHERE id = ? AND status IN (?, ?)"
            );
            $stmt->bind_param("ssss", $newStatus, $orderId, $pendingStatus, $processingStatus);
            $stmt->execute();
        }
        header('Location: index.php?tab=orders&view=' . urlencode($orderId));
        exit;
    }

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($sellerId > 0 && $name !== '' && $email !== '') {
            $newImageUrl = null;
            if (isset($_FILES['image']) && is_array($_FILES['image'])) {
                $uploaded = handleProfileImageUpload($_FILES['image'], $appBaseUrl);
                if ($uploaded) {
                    $newImageUrl = $uploaded;
                }
            }

            try {
                if ($newImageUrl) {
                    $stmt = $conn->prepare("UPDATE sellers SET name = ?, email = ?, profile_image_url = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("sssi", $name, $email, $newImageUrl, $sellerId);
                        $stmt->execute();
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE sellers SET name = ?, email = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("ssi", $name, $email, $sellerId);
                        $stmt->execute();
                    }
                }

                $_SESSION['email'] = $email;
            } catch (Throwable $e) {
                // If email is duplicated, keep session values and just redirect.
            }
        }

        header('Location: index.php?tab=inventory');
        exit;
    }
}

$tab = $_GET['tab'] ?? 'inventory';
$allowedTabs = ['inventory', 'sales', 'orders'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'inventory';
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$viewOrderId = trim($_GET['view'] ?? '');

$sellerName = 'Seller Account';
$sellerProfileImageUrl = '';
$sellerStmt = $conn->prepare("SELECT name, email, profile_image_url FROM sellers WHERE id = ?");
if ($sellerStmt) {
    $sellerStmt->bind_param("i", $sellerId);
    $sellerStmt->execute();
    $sellerRes = $sellerStmt->get_result();
    if ($seller = $sellerRes->fetch_assoc()) {
        $sellerName = $seller['name'] ?: $sellerName;
        $sellerEmail = $seller['email'] ?: $sellerEmail;
        $sellerProfileImageUrl = $seller['profile_image_url'] ?? '';
    }
}

$inventorySql = "SELECT id, name, category, description, price, stock, status FROM products WHERE seller_id = ? OR seller_id IS NULL ORDER BY id ASC";
$inventoryStmt = $conn->prepare($inventorySql);
$inventoryRows = [];
if ($inventoryStmt) {
    $inventoryStmt->bind_param("i", $sellerId);
    $inventoryStmt->execute();
    $inventoryRows = $inventoryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$totalProducts = count($inventoryRows);
$totalUnits = 0;
$lowStockAlerts = 0;
foreach ($inventoryRows as $item) {
    $totalUnits += (int) $item['stock'];
    if ($item['status'] === 'Low Stock' || (int) $item['stock'] <= 10) {
        $lowStockAlerts++;
    }
}

$salesSql = "
SELECT
    o.id AS order_id,
    o.status,
    DATE_FORMAT(o.created_at, '%b %e, %Y') AS order_date,
    p.name AS product_name,
    p.category,
    oi.quantity,
    oi.price AS unit_price,
    (oi.quantity * oi.price) AS line_total
FROM orders o
INNER JOIN order_items oi ON oi.order_id = o.id
INNER JOIN products p ON p.id = oi.product_id
WHERE (p.seller_id = ? OR p.seller_id IS NULL)
ORDER BY o.created_at ASC, oi.id ASC
";
$salesStmt = $conn->prepare($salesSql);
$salesRows = [];
if ($salesStmt) {
    $salesStmt->bind_param("i", $sellerId);
    $salesStmt->execute();
    $salesRows = $salesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($tab === 'sales' && (($_GET['export'] ?? '') === 'csv')) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['TXN ID', 'Date', 'Product', 'Category', 'Qty', 'Unit Price', 'Total', 'Status']);
    foreach ($salesRows as $row) {
        fputcsv($out, [
            $row['order_id'],
            $row['order_date'],
            $row['product_name'],
            ucfirst(strtolower($row['category'])),
            (int) $row['quantity'],
            number_format((float) $row['unit_price'], 2, '.', ''),
            number_format((float) $row['line_total'], 2, '.', ''),
            $row['status'],
        ]);
    }
    fclose($out);
    exit;
}

$totalRevenue = 0;
$completedSales = 0;
$transactions = [];
foreach ($salesRows as $row) {
    $transactions[] = $row;
    if ($row['status'] === 'Delivered') {
        $totalRevenue += (float) $row['line_total'];
        $completedSales++;
    }
}

$ordersSql = "
SELECT
    o.id,
    o.created_at,
    o.total_amount,
    o.status,
    c.name AS customer_name,
    c.address
FROM orders o
INNER JOIN customers c ON c.id = o.customer_id
WHERE 1 = 1
";
$params = [];
$types = '';

if ($statusFilter !== '' && in_array($statusFilter, ['Pending', 'Processing', 'Delivered', 'Cancelled'], true)) {
    $ordersSql .= " AND o.status = ? ";
    $types .= 's';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $ordersSql .= " AND (o.id LIKE ? OR c.name LIKE ?) ";
    $types .= 'ss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$ordersSql .= " ORDER BY o.created_at ASC ";
$ordersStmt = $conn->prepare($ordersSql);
$ordersRows = [];
if ($ordersStmt) {
    if ($types !== '') {
        $ordersStmt->bind_param($types, ...$params);
    }
    $ordersStmt->execute();
    $ordersRows = $ordersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$totalOrders = count($ordersRows);
$deliveredOrders = 0;
$pendingOrProcessing = 0;
foreach ($ordersRows as $order) {
    if ($order['status'] === 'Delivered') {
        $deliveredOrders++;
    }
    if ($order['status'] === 'Pending' || $order['status'] === 'Processing') {
        $pendingOrProcessing++;
    }
}

$selectedOrder = null;
$selectedOrderItems = [];
if ($viewOrderId !== '') {
    $detailStmt = $conn->prepare("
        SELECT
            o.id,
            o.created_at,
            o.total_amount,
            o.status,
            c.name AS customer_name,
            c.address
        FROM orders o
        INNER JOIN customers c ON c.id = o.customer_id
        WHERE o.id = ?
        LIMIT 1
    ");
    if ($detailStmt) {
        $detailStmt->bind_param("s", $viewOrderId);
        $detailStmt->execute();
        $selectedOrder = $detailStmt->get_result()->fetch_assoc();
    }

    if ($selectedOrder) {
        $itemStmt = $conn->prepare("
            SELECT
                p.name,
                p.category,
                oi.quantity,
                oi.price,
                (oi.quantity * oi.price) AS subtotal
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
        if ($itemStmt) {
            $itemStmt->bind_param("s", $viewOrderId);
            $itemStmt->execute();
            $selectedOrderItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { background: #f3f4f6; font-family: 'Inter', Arial, sans-serif; color: #12141a; }
        .seller-layout { min-height: 100vh; display: flex; max-width: 1460px; margin: 0 auto; background: #f8f9fb; box-shadow: 0 4px 30px rgba(15, 19, 31, 0.08); }
        .sidebar { width: 185px; background: #13151a; color: #d4d7dd; display: flex; flex-direction: column; padding: 12px 10px; }
        .brand { font-weight: 700; color: #fff; font-size: 14px; margin-bottom: 20px; line-height: 1.2; border-bottom: 1px solid #22252e; padding: 8px 8px 14px; }
        .brand small { display: block; color: #808797; font-weight: 500; font-size: 10px; }
        .menu-label { color: #636a78; font-size: 9px; margin: 0 8px 6px; text-transform: uppercase; letter-spacing: .08em; }
        .nav-link-custom { color: #b4bac8; text-decoration: none; display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; margin-bottom: 4px; font-size: 13px; }
        .nav-link-custom:hover { color: #fff; background: #1b1d24; }
        .nav-link-custom.active { background: #f6f7f9; color: #111; font-weight: 600; }
        .sidebar-bottom { margin-top: auto; padding: 14px 8px 8px; border-top: 1px solid #22252e; }
        .account-name { color: #fff; font-size: 12px; font-weight: 600; line-height: 1.2; }
        .account-email { color: #7f8696; font-size: 10px; }
        .logout-link { display: inline-flex; margin-top: 10px; color: #aab1bf; text-decoration: none; font-size: 12px; }
        .logout-link:hover { color: #fff; }
        .content { flex: 1; padding: 10px; }
        .panel { background: #fff; border: 1px solid #e9ebf0; border-radius: 0; padding: 14px 16px; min-height: calc(100vh - 20px); }
        .page-title { font-size: 28px; margin: 0; font-weight: 700; line-height: 1.05; }
        .subtitle { color: #9aa0ad; font-size: 12px; margin-top: 3px; }
        .stat-box { border: 1px solid #e7eaf0; border-radius: 9px; padding: 12px 14px; background: #fff; min-height: 92px; }
        .stat-box .label { font-size: 10px; color: #9ba2b0; text-transform: uppercase; letter-spacing: .11em; font-weight: 600; }
        .stat-box .value { font-size: 44px; font-weight: 700; line-height: .98; margin-top: 7px; }
        .toolbar-btn { border-radius: 8px; font-weight: 600; font-size: 13px; padding: 8px 14px; }
        .table-wrap { border: 1px solid #e7eaf0; border-radius: 10px; overflow: hidden; background: #fff; }
        .table { margin-bottom: 0; }
        .table thead th { background: #f9fafc; font-size: 10px; color: #9aa1ae; text-transform: uppercase; border-bottom: 1px solid #eceff4; letter-spacing: .1em; font-weight: 700; padding: 10px 11px; white-space: nowrap; }
        .table td { vertical-align: middle; font-size: 13px; color: #49505f; padding: 10px 11px; }
        .table tbody tr { border-color: #eff1f5; }
        .table tbody tr:last-child td { border-bottom: 0; }
        .status-pill { font-size: 11px; border-radius: 6px; border: 1px solid #d5d8df; padding: 2px 9px; display: inline-block; font-weight: 500; background: #fff; }
        .status-in-stock { border-color: #b9c3d7; }
        .status-low-stock { border-color: #f0c084; background: #fff8ef; }
        .status-out-stock { border-color: #df9ca0; background: #fff3f4; }
        .status-delivered { border-color: #b9c3d7; }
        .status-pending { border-color: #d6d9e0; background: #f7f8fa; }
        .status-processing { border-color: #d8c6a5; background: #fff9ef; }
        .status-cancelled { border-color: #d7d7da; background: #f2f3f5; color: #8a909d; }
        .order-card { border: 1px solid #e8e9ed; border-radius: 10px; padding: 14px; background: #fff; min-height: 156px; }
        .order-card .title { font-size: 10px; color: #8a909d; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 12px; font-weight: 700; }
        .meta-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 9px; }
        .meta-icon { width: 26px; height: 26px; border: 1px solid #e7e8ec; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; color: #8a909d; font-size: 12px; }
        .meta-label { color: #8a909d; font-size: 11px; line-height: 1; margin-bottom: 2px; }
        .meta-value { font-size: 13px; font-weight: 600; line-height: 1.2; color: #141820; }
        .table-footer { display: flex; justify-content: space-between; align-items: center; color: #a0a7b4; font-size: 12px; border: 1px solid #e7eaf0; border-top: 0; border-radius: 0 0 10px 10px; padding: 10px 12px; }
        .mini-btn { border: 1px solid #e6e9ef; background: #fff; color: #9fa6b3; border-radius: 6px; padding: 3px 8px; font-size: 12px; }
        .mini-page { border: 1px solid #c5cad4; color: #161b24; border-radius: 4px; padding: 1px 5px; font-size: 11px; margin: 0 6px; }
        .form-control, .form-select { border-color: #e4e7ee; border-radius: 8px; font-size: 13px; min-height: 38px; }
        .modal-content { border: 0; border-radius: 14px; box-shadow: 0 24px 70px rgba(17, 22, 30, 0.25); }
        .modal-header { padding: 16px 18px 8px; }
        .modal-body { padding: 12px 18px; }
        .modal-footer { padding: 10px 18px 16px; }
        .modal-title { font-size: 26px; line-height: 1; }
        .form-label { font-size: 12px; color: #586072; }
        .action-btn { border-radius: 8px; font-size: 12px; padding: 5px 12px; }
    </style>
</head>
<body>
<div class="seller-layout">
    <aside class="sidebar">
        <div class="brand">
            School Supply
            <small>Seller Portal</small>
        </div>
        <div class="menu-label">Menu</div>
        <a class="nav-link-custom <?= $tab === 'inventory' ? 'active' : '' ?>" href="index.php?tab=inventory">
            <i class="bi bi-box-seam"></i> Inventory
        </a>
        <a class="nav-link-custom <?= $tab === 'sales' ? 'active' : '' ?>" href="index.php?tab=sales">
            <i class="bi bi-bar-chart"></i> Sales Report
        </a>
        <a class="nav-link-custom <?= $tab === 'orders' ? 'active' : '' ?>" href="index.php?tab=orders">
            <i class="bi bi-bag"></i> Orders
        </a>

        <div class="sidebar-bottom">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;background:#2a2d34;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                    <?php if (!empty($sellerProfileImageUrl)): ?>
                        <img src="<?= esc($sellerProfileImageUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person" style="color:#aab1bf;font-size:18px;"></i>
                    <?php endif; ?>
                </div>
                <div class="overflow-hidden">
                    <div class="account-name"><?= esc($sellerName) ?></div>
                    <div class="account-email"><?= esc($sellerEmail) ?></div>
                </div>
            </div>

            <button type="button" class="btn btn-outline-secondary w-100 btn-sm" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="bi bi-pencil-square me-1"></i> Edit Profile
            </button>

            <a class="logout-link" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
    </aside>

    <main class="content">
        <div class="panel">
            <?php if ($tab === 'inventory'): ?>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h1 class="page-title">Manage Inventory</h1>
                        <div class="subtitle"><?= $totalProducts ?> items total</div>
                    </div>
                    <button class="btn btn-dark toolbar-btn" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="bi bi-plus"></i> Add Item
                    </button>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Total Products</div>
                            <div class="value"><?= $totalProducts ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Total Units</div>
                            <div class="value"><?= $totalUnits ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Low Stock Alerts</div>
                            <div class="value"><?= $lowStockAlerts ?></div>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($inventoryRows as $idx => $product): ?>
                            <?php
                            $stock = (int) $product['stock'];
                            // Derive status from current stock to keep UI accurate.
                            $status = productStatusFromStock($stock);
                            $statusClass = 'status-in-stock';
                            if ($status === 'Low Stock') {
                                $statusClass = 'status-low-stock';
                            } elseif ($status === 'Out of Stock') {
                                $statusClass = 'status-out-stock';
                            }
                            $unit = 'pc';
                            if (isset($product['description']) && stripos($product['description'], 'Unit:') === 0) {
                                $unit = trim(substr($product['description'], 5));
                            }
                            ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong><?= esc($product['name']) ?></strong></td>
                                <td><?= 'SKU-' . str_pad((string) $product['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td><?= esc(ucfirst(strtolower($product['category']))) ?></td>
                                <td>₱<?= number_format((float) $product['price'], 2) ?></td>
                                <td><?= $stock ?></td>
                                <td><span class="status-pill <?= $statusClass ?>"><?= esc($status) ?></span></td>
                                <td class="text-end">
                                    <button
                                        class="btn btn-sm btn-outline-secondary me-1 edit-product-btn action-btn"
                                        data-id="<?= (int) $product['id'] ?>"
                                        data-name="<?= esc($product['name']) ?>"
                                        data-category="<?= esc($product['category']) ?>"
                                        data-price="<?= number_format((float) $product['price'], 2, '.', '') ?>"
                                        data-stock="<?= $stock ?>"
                                        data-unit="<?= esc($unit) ?>"
                                    >
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button
                                        class="btn btn-sm btn-outline-secondary delete-product-btn action-btn"
                                        data-id="<?= (int) $product['id'] ?>"
                                        data-name="<?= esc($product['name']) ?>"
                                    >
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($inventoryRows) === 0): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No products found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span>Showing 1-<?= min(5, $totalProducts) ?> of <?= $totalProducts ?> items</span>
                    <span><button class="mini-btn" type="button">Previous</button><span class="mini-page">1</span><button class="mini-btn" type="button">Next</button></span>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'sales'): ?>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h1 class="page-title">Sales Report</h1>
                        <div class="subtitle">Transaction history overview</div>
                    </div>
                    <a class="btn btn-outline-dark toolbar-btn" href="index.php?tab=sales&export=csv">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Total Revenue</div>
                            <div class="value">₱<?= number_format($totalRevenue, 0) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Transactions</div>
                            <div class="value"><?= count($transactions) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Completed</div>
                            <div class="value"><?= $completedSales ?></div>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>TXN ID</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($transactions as $row): ?>
                            <?php
                            $statusClass = 'status-pending';
                            if ($row['status'] === 'Delivered') {
                                $statusClass = 'status-delivered';
                            } elseif ($row['status'] === 'Processing') {
                                $statusClass = 'status-processing';
                            } elseif ($row['status'] === 'Cancelled') {
                                $statusClass = 'status-cancelled';
                            }
                            ?>
                            <tr>
                                <td><?= esc($row['order_id']) ?></td>
                                <td><?= esc($row['order_date']) ?></td>
                                <td><strong><?= esc($row['product_name']) ?></strong></td>
                                <td><?= esc(ucfirst(strtolower($row['category']))) ?></td>
                                <td><?= (int) $row['quantity'] ?></td>
                                <td>₱<?= number_format((float) $row['unit_price'], 2) ?></td>
                                <td><strong>₱<?= number_format((float) $row['line_total'], 2) ?></strong></td>
                                <td><span class="status-pill <?= $statusClass ?>"><?= esc($row['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($transactions) === 0): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No transactions found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span>Showing 1-<?= min(8, count($transactions)) ?> of <?= count($transactions) ?> transactions</span>
                    <span><button class="mini-btn" type="button">Previous</button><span class="mini-page">1</span><button class="mini-btn" type="button">Next</button></span>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'orders'): ?>
                <?php if ($selectedOrder): ?>
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <h1 class="page-title">Order #<?= esc($selectedOrder['id']) ?></h1>
                            <div class="subtitle">Order details and fulfillment</div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <a href="index.php?tab=orders" class="btn btn-outline-dark toolbar-btn">
                                <i class="bi bi-arrow-left"></i> Back to Orders
                            </a>
                            <span class="status-pill status-<?= strtolower($selectedOrder['status']) === 'delivered' ? 'delivered' : strtolower($selectedOrder['status']) ?>">
                                <?= esc($selectedOrder['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="order-card">
                                <div class="title">Customer Information</div>
                                <div class="meta-row">
                                    <span class="meta-icon"><i class="bi bi-person"></i></span>
                                    <div><div class="meta-label">Customer Name</div><div class="meta-value"><?= esc($selectedOrder['customer_name']) ?></div></div>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-icon"><i class="bi bi-tag"></i></span>
                                    <div><div class="meta-label">Grade Level</div><div class="meta-value">Grade <?= preg_match('/(\d+)/', (string) ($selectedOrder['address'] ?? ''), $m) ? esc($m[1]) : '10' ?></div></div>
                                </div>
                                <div class="meta-row mb-0">
                                    <span class="meta-icon"><i class="bi bi-geo-alt"></i></span>
                                    <div><div class="meta-label">Address</div><div class="meta-value"><?= esc($selectedOrder['address'] ?: 'Cebu City') ?></div></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="order-card">
                                <div class="title">Order Information</div>
                                <div class="meta-row">
                                    <span class="meta-icon"><i class="bi bi-calendar3"></i></span>
                                    <div><div class="meta-label">Date & Time</div><div class="meta-value"><?= esc(date('M j, Y · g:i A', strtotime($selectedOrder['created_at']))) ?></div></div>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-icon"><i class="bi bi-credit-card"></i></span>
                                    <div><div class="meta-label">Payment Method</div><div class="meta-value">Cash</div></div>
                                </div>
                                <div class="meta-row mb-0">
                                    <span class="meta-icon"><i class="bi bi-bag-check"></i></span>
                                    <div><div class="meta-label">Order Status</div><div class="meta-value"><?= esc($selectedOrder['status']) ?></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap mb-3">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selectedOrderItems as $item): ?>
                                    <tr>
                                        <td><strong><?= esc($item['name']) ?></strong></td>
                                        <td><?= esc(ucfirst(strtolower($item['category']))) ?></td>
                                        <td><?= (int) $item['quantity'] ?></td>
                                        <td>₱<?= number_format((float) $item['price'], 2) ?></td>
                                        <td class="text-end"><strong>₱<?= number_format((float) $item['subtotal'], 2) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="4"><strong>Order Total</strong></td>
                                    <td class="text-end"><strong style="font-size: 30px;">₱<?= number_format((float) $selectedOrder['total_amount'], 2) ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php?tab=orders" class="btn btn-outline-dark toolbar-btn">
                            <i class="bi bi-arrow-left"></i> Back to Orders
                        </a>
                        <?php if ($selectedOrder['status'] === 'Pending' || $selectedOrder['status'] === 'Processing'): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="fulfill_order">
                                <input type="hidden" name="order_id" value="<?= esc($selectedOrder['id']) ?>">
                                <button class="btn btn-dark toolbar-btn">
                                    <i class="bi bi-check-circle"></i> Mark as Fulfilled
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <h1 class="page-title">Customer Orders</h1>
                            <div class="subtitle">Manage incoming orders and pickup status</div>
                        </div>
                        <form method="get" class="d-flex gap-2">
                            <input type="hidden" name="tab" value="orders">
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute" style="left:10px;top:10px;color:#a1a8b5;font-size:12px;"></i>
                                <input type="text" class="form-control ps-4" name="search" placeholder="Search order/customer" value="<?= esc($search) ?>">
                            </div>
                            <select class="form-select" name="status">
                                <option value="">Filter by Status</option>
                                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Processing" <?= $statusFilter === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="Delivered" <?= $statusFilter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button class="btn btn-outline-dark">Apply</button>
                        </form>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="stat-box"><div class="label">Total Orders</div><div class="value"><?= $totalOrders ?></div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box"><div class="label">Delivered</div><div class="value"><?= $deliveredOrders ?></div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box"><div class="label">Pending / Processing</div><div class="value"><?= $pendingOrProcessing ?></div></div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Customer Name</th>
                                    <th>Grade Level</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordersRows as $order): ?>
                                    <?php
                                    $statusClass = 'status-pending';
                                    $gradeDisplay = 'Grade 10';
                                    if (preg_match('/(\d+)/', (string) ($order['address'] ?? ''), $m)) {
                                        $gradeDisplay = 'Grade ' . $m[1];
                                    }
                                    if ($order['status'] === 'Delivered') {
                                        $statusClass = 'status-delivered';
                                    } elseif ($order['status'] === 'Processing') {
                                        $statusClass = 'status-processing';
                                    } elseif ($order['status'] === 'Cancelled') {
                                        $statusClass = 'status-cancelled';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= esc($order['id']) ?></td>
                                        <td><?= esc(date('M j, Y', strtotime($order['created_at']))) ?></td>
                                        <td><strong><?= esc($order['customer_name']) ?></strong></td>
                                        <td><?= esc($gradeDisplay) ?></td>
                                        <td><strong>₱<?= number_format((float) $order['total_amount'], 2) ?></strong></td>
                                        <td><span class="status-pill <?= $statusClass ?>"><?= esc($order['status']) ?></span></td>
                                        <td class="text-end">
                                            <a href="index.php?tab=orders&view=<?= urlencode($order['id']) ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($ordersRows) === 0): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No orders found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer">
                        <span>Showing 1-<?= min(8, $totalOrders) ?> of <?= $totalOrders ?> orders</span>
                        <span><button class="mini-btn" type="button">Previous</button><span class="mini-page">1</span><button class="mini-btn" type="button">Next</button></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Add New Item</h5>
                        <div class="text-muted small">Fill in the product details below</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="text-muted small mt-1">Optional. JPG/PNG/WebP/GIF</div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SKU</label>
                            <input type="text" class="form-control" placeholder="Auto-generated" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" value="Paper" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stock Qty</label>
                            <input type="number" min="0" name="stock" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Unit</label>
                            <input type="text" name="unit" class="form-control" value="pc">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-plus"></i> Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Edit Item</h5>
                        <div class="text-muted small">Update product information</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name</label>
                        <input type="text" name="name" id="edit-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Replace Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="text-muted small mt-1">Optional. Leave empty to keep current image.</div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SKU</label>
                            <input type="text" id="edit-sku" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" id="edit-category" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="price" id="edit-price" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stock Qty</label>
                            <input type="number" min="0" name="stock" id="edit-stock" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Unit</label>
                            <input type="text" name="unit" id="edit-unit" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-check2"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="id" id="delete-id">
                <div class="modal-body py-4 text-center">
                    <div class="mb-2"><i class="bi bi-trash fs-2 text-muted"></i></div>
                    <h5 class="fw-bold mb-2">Delete Product?</h5>
                    <p class="text-muted mb-1">You are about to delete <strong id="delete-name"></strong>.</p>
                    <p class="text-muted small mb-3">This action cannot be undone.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark"><i class="bi bi-trash"></i> Yes, Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Edit Profile</h5>
                        <div class="text-muted small">Update your name, email, and profile image</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= esc($sellerName) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= esc($sellerEmail) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Profile Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="text-muted small mt-1">Optional</div>
                    </div>
                    <?php if (!empty($sellerProfileImageUrl)): ?>
                        <div class="mb-0 text-center">
                            <div class="text-muted small fw-semibold mb-2">Current image</div>
                            <div style="width:160px;height:160px;margin:0 auto;border-radius:12px;overflow:hidden;border:1px solid #e7e8ec;">
                                <img src="<?= esc($sellerProfileImageUrl) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-check2"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));

    document.querySelectorAll('.edit-product-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = btn.dataset.name;
            document.getElementById('edit-category').value = btn.dataset.category;
            document.getElementById('edit-price').value = btn.dataset.price;
            document.getElementById('edit-stock').value = btn.dataset.stock;
            document.getElementById('edit-unit').value = btn.dataset.unit || 'pc';
            document.getElementById('edit-sku').value = 'SKU-' + String(id).padStart(3, '0');
            editModal.show();
        });
    });

    document.querySelectorAll('.delete-product-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete-id').value = btn.dataset.id;
            document.getElementById('delete-name').textContent = btn.dataset.name;
            deleteModal.show();
        });
    });
</script>
</body>
</html>
