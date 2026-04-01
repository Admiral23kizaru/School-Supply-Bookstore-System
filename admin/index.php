<?php
session_start();
require_once __DIR__ . '/../db/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php?action=login');
    exit;
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function product_status(int $stock): string
{
    if ($stock <= 0) {
        return 'Out of Stock';
    }
    if ($stock <= 10) {
        return 'Low Stock';
    }
    return 'In Stock';
}

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$adminName = 'Admin Account';
$adminEmail = $_SESSION['email'] ?? 'admin@gmail.com';
$adminStmt = $conn->prepare("SELECT name, email FROM admins WHERE id = ?");
if ($adminStmt) {
    $adminStmt->bind_param("i", $adminId);
    $adminStmt->execute();
    $adminRow = $adminStmt->get_result()->fetch_assoc();
    if ($adminRow) {
        $adminName = $adminRow['name'] ?: $adminName;
        $adminEmail = $adminRow['email'] ?: $adminEmail;
    }
}

$tab = $_GET['tab'] ?? 'dashboard';
$allowedTabs = ['dashboard', 'users', 'products', 'orders', 'reports'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'dashboard';
}

$roleFilter = trim($_GET['role'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$orderStatusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$viewUser = (int) ($_GET['user'] ?? 0);
$viewType = trim($_GET['type'] ?? 'customer');

$users = [];
$customerQ = $conn->query("SELECT id, name, email, created_at FROM customers ORDER BY id ASC");
while ($customerQ && ($row = $customerQ->fetch_assoc())) {
    $uid = 'U-' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT);
    $ordersQ = $conn->prepare("SELECT COUNT(*) c FROM orders WHERE customer_id = ?");
    $ordersQ->bind_param("i", $row['id']);
    $ordersQ->execute();
    $orderCount = (int) ($ordersQ->get_result()->fetch_assoc()['c'] ?? 0);
    $users[] = [
        'id' => (int) $row['id'],
        'uid' => $uid,
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => 'Customer',
        'joined' => date('M j, Y', strtotime($row['created_at'])),
        'activity' => $orderCount . ($orderCount === 1 ? ' order' : ' orders'),
        'status' => $orderCount > 0 ? 'Active' : 'Inactive',
        'type' => 'customer',
    ];
}

$sellerQ = $conn->query("SELECT id, name, email, created_at FROM sellers ORDER BY id ASC");
while ($sellerQ && ($row = $sellerQ->fetch_assoc())) {
    $uid = 'U-' . str_pad((string) (100 + $row['id']), 3, '0', STR_PAD_LEFT);
    $prodQ = $conn->prepare("SELECT COUNT(*) c FROM products WHERE seller_id = ?");
    $prodQ->bind_param("i", $row['id']);
    $prodQ->execute();
    $productCount = (int) ($prodQ->get_result()->fetch_assoc()['c'] ?? 0);
    $users[] = [
        'id' => (int) $row['id'],
        'uid' => $uid,
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => 'Seller',
        'joined' => date('M j, Y', strtotime($row['created_at'])),
        'activity' => $productCount . ($productCount === 1 ? ' product' : ' products'),
        'status' => $productCount > 0 ? 'Active' : 'Inactive',
        'type' => 'seller',
    ];
}

if ($roleFilter !== '' && in_array($roleFilter, ['Customer', 'Seller'], true)) {
    $users = array_values(array_filter($users, fn($u) => $u['role'] === $roleFilter));
}

$countCustomers = 0;
$countSellers = 0;
foreach ($users as $u) {
    if ($u['role'] === 'Customer') {
        $countCustomers++;
    } else {
        $countSellers++;
    }
}
$countUsers = count($users);

$products = [];
$productSql = "SELECT p.id, p.name, p.category, p.price, p.stock, s.name AS seller_name FROM products p LEFT JOIN sellers s ON s.id = p.seller_id";
$prodRows = $conn->query($productSql);
while ($prodRows && ($p = $prodRows->fetch_assoc())) {
    $products[] = [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'sku' => 'SKU-' . str_pad((string) $p['id'], 3, '0', STR_PAD_LEFT),
        'category' => ucfirst(strtolower($p['category'])),
        'price' => (float) $p['price'],
        'stock' => (int) $p['stock'],
        'status' => product_status((int) $p['stock']),
        'seller' => $p['seller_name'] ?: 'Seller One',
    ];
}
if ($categoryFilter !== '') {
    $products = array_values(array_filter($products, fn($p) => strcasecmp($p['category'], $categoryFilter) === 0));
}

$totalProducts = count($products);
$inStock = 0;
$lowOutStock = 0;
foreach ($products as $p) {
    if ($p['status'] === 'In Stock') {
        $inStock++;
    } else {
        $lowOutStock++;
    }
}

$orders = [];
$orderSql = "
SELECT o.id, o.created_at, o.total_amount, o.status, c.name customer_name, oi.quantity, p.name product_name
FROM orders o
INNER JOIN customers c ON c.id = o.customer_id
LEFT JOIN order_items oi ON oi.order_id = o.id
LEFT JOIN products p ON p.id = oi.product_id
ORDER BY o.created_at ASC, o.id ASC
";
$orderRows = $conn->query($orderSql);
while ($orderRows && ($o = $orderRows->fetch_assoc())) {
    $orders[] = [
        'id' => $o['id'],
        'customer' => $o['customer_name'],
        'date' => date('M j, Y', strtotime($o['created_at'])),
        'items' => (int) ($o['quantity'] ?? 0) . 'x ' . ($o['product_name'] ?? 'Item'),
        'total' => (float) $o['total_amount'],
        'payment' => (rand(0, 1) ? 'GCash' : 'Cash'),
        'status' => $o['status'],
    ];
}
if ($orderStatusFilter !== '' && in_array($orderStatusFilter, ['Pending', 'Processing', 'Delivered', 'Cancelled'], true)) {
    $orders = array_values(array_filter($orders, fn($o) => $o['status'] === $orderStatusFilter));
}
if ($search !== '') {
    $orders = array_values(array_filter($orders, fn($o) => stripos($o['id'], $search) !== false || stripos($o['customer'], $search) !== false));
}

$totalOrders = count($orders);
$deliveredCount = count(array_filter($orders, fn($o) => $o['status'] === 'Delivered'));
$processingCount = count(array_filter($orders, fn($o) => $o['status'] === 'Processing'));
$pendingCount = count(array_filter($orders, fn($o) => $o['status'] === 'Pending'));

$reportRows = [];
$reportSql = "
SELECT o.created_at, p.name product_name, p.category, oi.quantity, oi.price, (oi.quantity * oi.price) total, o.status, COALESCE(s.name, 'Seller One') seller_name
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
INNER JOIN products p ON p.id = oi.product_id
LEFT JOIN sellers s ON s.id = p.seller_id
ORDER BY o.created_at ASC
";
$rep = $conn->query($reportSql);
while ($rep && ($r = $rep->fetch_assoc())) {
    $reportRows[] = [
        'date' => date('M j, Y', strtotime($r['created_at'])),
        'product' => $r['product_name'],
        'category' => ucfirst(strtolower($r['category'])),
        'qty' => (int) $r['quantity'],
        'unit_price' => (float) $r['price'],
        'total' => (float) $r['total'],
        'status' => $r['status'] === 'Delivered' ? 'Completed' : $r['status'],
        'seller' => $r['seller_name'],
    ];
}

$totalRevenue = 0;
$completed = 0;
$cancelled = 0;
foreach ($reportRows as $r) {
    if ($r['status'] === 'Completed') {
        $totalRevenue += $r['total'];
        $completed++;
    }
    if ($r['status'] === 'Cancelled') {
        $cancelled++;
    }
}

if ($tab === 'reports' && (($_GET['export'] ?? '') === 'csv')) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="admin-reports.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Product', 'Category', 'Qty', 'Unit Price', 'Total', 'Status', 'Seller']);
    foreach ($reportRows as $r) {
        fputcsv($out, [$r['date'], $r['product'], $r['category'], $r['qty'], $r['unit_price'], $r['total'], $r['status'], $r['seller']]);
    }
    fclose($out);
    exit;
}

$selectedUser = null;
foreach ($users as $u) {
    if ($u['id'] === $viewUser && $u['type'] === $viewType) {
        $selectedUser = $u;
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { background: #f3f4f6; font-family: 'Inter', sans-serif; color: #12141a; }
        .layout { min-height: 100vh; display: flex; max-width: 1460px; margin: 10px auto; background: #f8f9fb; box-shadow: 0 4px 30px rgba(15,19,31,.08); }
        .sidebar { width: 210px; background: #13151a; color: #c6ccda; padding: 14px 12px; display: flex; flex-direction: column; }
        .brand { color: #fff; font-weight: 700; font-size: 15px; border-bottom: 1px solid #23262f; padding: 8px 8px 14px; margin-bottom: 14px; }
        .brand small { display: block; color: #7c8392; font-size: 10px; font-weight: 500; }
        .menu-label { color: #616877; font-size: 9px; margin: 0 8px 7px; text-transform: uppercase; letter-spacing: .1em; }
        .nav-link-custom { color: #adb4c2; text-decoration: none; font-size: 13px; border-radius: 8px; padding: 9px 10px; margin-bottom: 4px; display: flex; gap: 8px; align-items: center; }
        .nav-link-custom.active { background: #f5f6f8; color: #12141a; font-weight: 600; }
        .nav-link-custom:hover { background: #1c2029; color: #fff; }
        .sidebar-bottom { margin-top: auto; border-top: 1px solid #23262f; padding: 12px 8px 6px; }
        .acc-name { color: #fff; font-size: 12px; font-weight: 600; }
        .acc-email { color: #7f8696; font-size: 10px; }
        .logout-link { color: #aab1bf; text-decoration: none; font-size: 12px; margin-top: 10px; display: inline-flex; }
        .content { flex: 1; padding: 10px; }
        .panel { background: #fff; border: 1px solid #e9ebf0; min-height: calc(100vh - 22px); }
        .header { border-bottom: 1px solid #eaedf2; padding: 12px 18px 10px; }
        .page-title { margin: 0; font-size: 28px; font-weight: 700; line-height: 1; }
        .subtitle { color: #9ba2b0; font-size: 12px; margin-top: 3px; }
        .inner { padding: 14px 18px; }
        .stat-box { border: 1px solid #e7eaf0; border-radius: 8px; min-height: 84px; padding: 12px 14px; }
        .stat-box .label { color: #9ba2b0; font-size: 10px; text-transform: uppercase; letter-spacing: .1em; font-weight: 700; }
        .stat-box .value { font-size: 38px; font-weight: 700; line-height: 1; margin-top: 8px; }
        .table-wrap { border: 1px solid #e7eaf0; border-radius: 8px; overflow: hidden; background: #fff; }
        .table thead th { background: #f9fafc; font-size: 10px; color: #9aa1ae; letter-spacing: .1em; text-transform: uppercase; padding: 9px 10px; border-bottom: 1px solid #eceff4; }
        .table td { font-size: 13px; color: #4a5160; padding: 10px; vertical-align: middle; }
        .status-pill { font-size: 11px; border-radius: 6px; border: 1px solid #d5d8df; padding: 2px 8px; display: inline-block; background: #fff; }
        .status-delivered, .status-active, .status-completed, .status-in-stock { border-color: #111; color: #111; }
        .status-pending, .status-inactive, .status-cancelled, .status-out-of-stock { background: #f3f4f7; color: #9aa2af; border-color: #e0e3e8; }
        .status-processing, .status-low-stock { background: #f7f8fa; color: #8e96a4; border-color: #dde1e7; }
        .footer { display: flex; justify-content: space-between; align-items: center; border: 1px solid #e7eaf0; border-top: 0; border-radius: 0 0 8px 8px; color: #a0a7b4; font-size: 12px; padding: 10px 12px; }
        .mini-btn { border: 1px solid #e5e8ee; color: #a2a8b5; background: #fff; border-radius: 6px; padding: 2px 8px; font-size: 12px; }
        .mini-page { border-radius: 5px; border: 1px solid #0f1115; background: #0f1115; color: #fff; padding: 1px 8px; font-size: 11px; margin: 0 5px; }
        .btn-slim { font-size: 12px; border-radius: 8px; padding: 6px 11px; font-weight: 600; }
        .form-control, .form-select { min-height: 36px; border-color: #e4e7ee; border-radius: 8px; font-size: 13px; }
        .detail-card { border: 1px solid #e7eaf0; border-radius: 10px; padding: 14px; min-height: 175px; }
        .detail-title { color: #9ba2b0; font-size: 10px; text-transform: uppercase; letter-spacing: .1em; font-weight: 700; margin-bottom: 12px; }
        .meta { display: flex; gap: 10px; margin-bottom: 10px; }
        .meta-icon { width: 27px; height: 27px; border: 1px solid #e5e8ee; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; color: #9aa1ae; font-size: 12px; }
        .meta-k { color: #8f97a4; font-size: 10px; line-height: 1; margin-bottom: 2px; }
        .meta-v { font-size: 13px; color: #141820; font-weight: 600; }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">School Supply<small>Admin Panel</small></div>
        <div class="menu-label">Menu</div>
        <a class="nav-link-custom <?= $tab === 'dashboard' ? 'active' : '' ?>" href="index.php?tab=dashboard"><i class="bi bi-grid"></i>Dashboard</a>
        <a class="nav-link-custom <?= $tab === 'users' ? 'active' : '' ?>" href="index.php?tab=users"><i class="bi bi-people"></i>Manage Users</a>
        <a class="nav-link-custom <?= $tab === 'products' ? 'active' : '' ?>" href="index.php?tab=products"><i class="bi bi-box-seam"></i>Products</a>
        <a class="nav-link-custom <?= $tab === 'orders' ? 'active' : '' ?>" href="index.php?tab=orders"><i class="bi bi-bag"></i>All Orders</a>
        <a class="nav-link-custom <?= $tab === 'reports' ? 'active' : '' ?>" href="index.php?tab=reports"><i class="bi bi-bar-chart"></i>Reports</a>
        <div class="sidebar-bottom">
            <div class="acc-name"><?= esc($adminName) ?></div>
            <div class="acc-email"><?= esc($adminEmail) ?></div>
            <a class="logout-link" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
    </aside>

    <main class="content">
        <section class="panel">
            <?php if ($tab === 'dashboard'): ?>
                <div class="header"><h1 class="page-title">Dashboard</h1><div class="subtitle">System overview and key metrics</div></div>
                <div class="inner">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Users</div><div class="value"><?= $countUsers ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Products</div><div class="value"><?= $totalProducts ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Orders</div><div class="value"><?= $totalOrders ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Revenue</div><div class="value">₱<?= number_format($totalRevenue, 0) ?></div></div></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><div class="stat-box"><div class="label">Sellers</div><div class="value"><?= $countSellers ?></div></div></div>
                        <div class="col-md-6"><div class="stat-box"><div class="label">Customers</div><div class="value"><?= $countCustomers ?></div></div></div>
                    </div>
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead><tr><th colspan="7" style="text-transform:none;letter-spacing:0;font-size:13px;color:#202430;">Recent Orders<span style="float:right;color:#9ba2b0;font-size:12px;">Last 5 transactions</span></th></tr></thead>
                            <thead><tr><th>Order ID</th><th>Customer</th><th>Role</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($orders, -5) as $o): ?>
                                <tr>
                                    <td><?= esc($o['id']) ?></td><td><strong><?= esc($o['customer']) ?></strong></td><td>Customer</td><td><?= esc($o['items']) ?></td><td><strong>₱<?= number_format($o['total'], 2) ?></strong></td>
                                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $o['status'])) ?>"><?= esc($o['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'users'): ?>
                <?php if ($selectedUser): ?>
                    <div class="header d-flex justify-content-between align-items-start">
                        <div><h1 class="page-title">User Detail</h1><div class="subtitle">Full profile and activity</div></div>
                        <div class="d-flex gap-2 align-items-center">
                            <a href="index.php?tab=users" class="btn btn-outline-dark btn-slim"><i class="bi bi-arrow-left"></i> Back to Users</a>
                            <span class="status-pill"><?= esc($selectedUser['role']) ?></span>
                        </div>
                    </div>
                    <div class="inner">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><div class="detail-card">
                                <div class="detail-title">Account Information</div>
                                <div class="meta"><span class="meta-icon"><i class="bi bi-person"></i></span><div><div class="meta-k">Full Name</div><div class="meta-v"><?= esc($selectedUser['name']) ?></div></div></div>
                                <div class="meta"><span class="meta-icon"><i class="bi bi-envelope"></i></span><div><div class="meta-k">Email Address</div><div class="meta-v"><?= esc($selectedUser['email']) ?></div></div></div>
                                <div class="meta"><span class="meta-icon"><i class="bi bi-shield"></i></span><div><div class="meta-k">Role</div><div class="meta-v"><?= esc($selectedUser['role']) ?></div></div></div>
                                <div class="meta mb-0"><span class="meta-icon"><i class="bi bi-calendar3"></i></span><div><div class="meta-k">Date Joined</div><div class="meta-v"><?= esc($selectedUser['joined']) ?></div></div></div>
                            </div></div>
                            <div class="col-md-6"><div class="detail-card">
                                <div class="detail-title">Activity Summary</div>
                                <div class="row g-2">
                                    <div class="col-6"><div class="stat-box"><div class="label"><?= $selectedUser['role'] === 'Seller' ? 'Products Listed' : 'Orders Placed' ?></div><div class="value"><?= (int) preg_replace('/\D+/', '', $selectedUser['activity']) ?></div></div></div>
                                    <div class="col-6"><div class="stat-box"><div class="label">Orders Fulfilled</div><div class="value"><?= $selectedUser['role'] === 'Seller' ? max(0, (int) preg_replace('/\D+/', '', $selectedUser['activity']) - 1) : 0 ?></div></div></div>
                                    <div class="col-6"><div class="stat-box"><div class="label">Total Revenue</div><div class="value">₱<?= number_format($totalRevenue, 0) ?></div></div></div>
                                    <div class="col-6"><div class="stat-box"><div class="label">Account Status</div><div class="value" style="font-size:30px;"><?= esc($selectedUser['status']) ?></div></div></div>
                                </div>
                            </div></div>
                        </div>
                        <div class="table-wrap mb-3">
                            <table class="table mb-0">
                                <thead><tr><th colspan="7">Listed Products</th></tr></thead>
                                <thead><tr><th>#</th><th>Product Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach (array_slice($products, 0, 3) as $idx => $p): ?>
                                    <tr><td><?= $idx + 1 ?></td><td><strong><?= esc($p['name']) ?></strong></td><td><?= esc($p['sku']) ?></td><td><?= esc($p['category']) ?></td><td><strong>₱<?= number_format($p['price'], 2) ?></strong></td><td><?= $p['stock'] ?></td><td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $p['status'])) ?>"><?= esc($p['status']) ?></span></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php?tab=users" class="btn btn-outline-dark btn-slim"><i class="bi bi-arrow-left"></i> Back to Users</a>
                            <button class="btn btn-outline-secondary btn-slim"><i class="bi bi-slash-circle"></i> Suspend Account</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="header d-flex justify-content-between align-items-start">
                        <div><h1 class="page-title">Manage Users</h1><div class="subtitle">All registered sellers and customers</div></div>
                        <form method="get" class="d-flex gap-2"><input type="hidden" name="tab" value="users"><select name="role" class="form-select"><option value="">Filter by Role</option><option value="Customer" <?= $roleFilter === 'Customer' ? 'selected' : '' ?>>Customer</option><option value="Seller" <?= $roleFilter === 'Seller' ? 'selected' : '' ?>>Seller</option></select><button class="btn btn-outline-dark btn-slim">Apply</button></form>
                    </div>
                    <div class="inner">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="stat-box"><div class="label">Total Users</div><div class="value"><?= $countUsers ?></div></div></div>
                            <div class="col-md-4"><div class="stat-box"><div class="label">Sellers</div><div class="value"><?= $countSellers ?></div></div></div>
                            <div class="col-md-4"><div class="stat-box"><div class="label">Customers</div><div class="value"><?= $countCustomers ?></div></div></div>
                        </div>
                        <div class="table-wrap">
                            <table class="table mb-0">
                                <thead><tr><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Activity</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= esc($u['uid']) ?></td><td><strong><?= esc($u['name']) ?></strong></td><td><?= esc($u['email']) ?></td><td><span class="status-pill"><?= esc($u['role']) ?></span></td><td><?= esc($u['joined']) ?></td><td><?= esc($u['activity']) ?></td><td><span class="status-pill status-<?= strtolower($u['status']) ?>"><?= esc($u['status']) ?></span></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary btn-slim" href="index.php?tab=users&type=<?= esc($u['type']) ?>&user=<?= $u['id'] ?>"><i class="bi bi-eye"></i> View</a> <button class="btn btn-sm btn-outline-secondary btn-slim"><i class="bi bi-slash-circle"></i> Ban</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="footer"><span>Showing 1-<?= min(7, $countUsers) ?> of <?= $countUsers ?> users</span><span><button class="mini-btn">Previous</button><span class="mini-page">1</span><button class="mini-btn">2</button><button class="mini-btn">3</button><button class="mini-btn">Next</button></span></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($tab === 'products'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div><h1 class="page-title">Products</h1><div class="subtitle">All products across all sellers</div></div>
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="products">
                        <input type="text" class="form-control" name="search" placeholder="Search">
                        <select name="category" class="form-select"><option value="">Filter by Category</option><option>Paper</option><option>Writing</option><option>Supply</option></select>
                        <button class="btn btn-outline-dark btn-slim">Apply</button>
                    </form>
                </div>
                <div class="inner">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><div class="stat-box"><div class="label">Total Products</div><div class="value"><?= $totalProducts ?></div></div></div>
                        <div class="col-md-4"><div class="stat-box"><div class="label">In Stock</div><div class="value"><?= $inStock ?></div></div></div>
                        <div class="col-md-4"><div class="stat-box"><div class="label">Low / Out of Stock</div><div class="value"><?= $lowOutStock ?></div></div></div>
                    </div>
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead><tr><th>#</th><th>Product Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Seller</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($products as $idx => $p): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td><td><strong><?= esc($p['name']) ?></strong></td><td><?= esc($p['sku']) ?></td><td><?= esc($p['category']) ?></td><td><strong>₱<?= number_format($p['price'], 2) ?></strong></td><td><?= $p['stock'] ?></td>
                                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $p['status'])) ?>"><?= esc($p['status']) ?></span></td><td><?= esc($p['seller']) ?></td><td class="text-end"><button class="btn btn-sm btn-outline-secondary btn-slim"><i class="bi bi-eye"></i> View</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="footer"><span>Showing 1-<?= min(7, $totalProducts) ?> of <?= $totalProducts ?> products</span><span><button class="mini-btn">Previous</button><span class="mini-page">1</span><button class="mini-btn">Next</button></span></div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'orders'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div><h1 class="page-title">All Orders</h1><div class="subtitle">System-wide order management</div></div>
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="orders">
                        <input type="text" class="form-control" name="search" placeholder="Search order/customer" value="<?= esc($search) ?>">
                        <select name="status" class="form-select"><option value="">Filter by Status</option><option value="Delivered">Delivered</option><option value="Processing">Processing</option><option value="Pending">Pending</option><option value="Cancelled">Cancelled</option></select>
                        <button class="btn btn-outline-dark btn-slim">Apply</button>
                    </form>
                </div>
                <div class="inner">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Orders</div><div class="value"><?= $totalOrders ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Delivered</div><div class="value"><?= $deliveredCount ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Processing</div><div class="value"><?= $processingCount ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Pending</div><div class="value"><?= $pendingCount ?></div></div></div>
                    </div>
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><?= esc($o['id']) ?></td><td><strong><?= esc($o['customer']) ?></strong></td><td><?= esc($o['date']) ?></td><td><?= esc($o['items']) ?></td><td><strong>₱<?= number_format($o['total'], 2) ?></strong></td><td><?= esc($o['payment']) ?></td>
                                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $o['status'])) ?>"><?= esc($o['status']) ?></span></td><td class="text-end"><button class="btn btn-sm btn-outline-secondary btn-slim"><i class="bi bi-eye"></i> View</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="footer"><span>Showing 1-<?= min(8, $totalOrders) ?> of <?= $totalOrders ?> orders</span><span><button class="mini-btn">Previous</button><span class="mini-page">1</span><button class="mini-btn">Next</button></span></div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'reports'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div><h1 class="page-title">Reports</h1><div class="subtitle">Revenue and performance overview</div></div>
                    <a href="index.php?tab=reports&export=csv" class="btn btn-outline-dark btn-slim"><i class="bi bi-download"></i> Export CSV</a>
                </div>
                <div class="inner">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Revenue</div><div class="value">₱<?= number_format($totalRevenue, 0) ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Transactions</div><div class="value"><?= count($reportRows) ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Completed</div><div class="value"><?= $completed ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Cancelled</div><div class="value"><?= $cancelled ?></div></div></div>
                    </div>
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead><tr><th>Date</th><th>Product</th><th>Category</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Seller</th></tr></thead>
                            <tbody>
                            <?php foreach ($reportRows as $r): ?>
                                <tr>
                                    <td><?= esc($r['date']) ?></td><td><strong><?= esc($r['product']) ?></strong></td><td><?= esc($r['category']) ?></td><td><?= $r['qty'] ?></td><td>₱<?= number_format($r['unit_price'], 2) ?></td><td><strong>₱<?= number_format($r['total'], 2) ?></strong></td>
                                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $r['status'])) ?>"><?= esc($r['status']) ?></span></td><td><?= esc($r['seller']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="footer"><span>Showing 1-<?= min(8, count($reportRows)) ?> of <?= count($reportRows) ?> transactions</span><span><button class="mini-btn">Previous</button><span class="mini-page">1</span><button class="mini-btn">Next</button></span></div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
