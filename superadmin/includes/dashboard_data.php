<?php
// Gated by session checking in the parent dashboard.php

ensureAccountStatusColumns($conn);
ensureProfileImageColumn($conn, 'admins');
ensureApprovalStatusColumn($conn, 'customers');
ensureApprovalStatusColumn($conn, 'sellers');

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$adminName = 'Super Admin';
$adminEmail = $_SESSION['email'] ?? 'superadmin@gmail.com';
$adminProfileImageUrl = '';

$adminStmt = $conn->prepare("SELECT name, email, profile_image_url FROM admins WHERE id = ?");
if ($adminStmt) {
    $adminStmt->bind_param("i", $adminId);
    $adminStmt->execute();
    $adminRow = $adminStmt->get_result()->fetch_assoc();
    if ($adminRow) {
        $adminName = $adminRow['name'] ?: $adminName;
        $adminEmail = $adminRow['email'] ?: $adminEmail;
        $adminProfileImageUrl = trim((string) ($adminRow['profile_image_url'] ?? ''));
    }
}

$tab = $_GET['tab'] ?? 'dashboard';
$allowedTabs = ['dashboard', 'profile', 'approvals', 'users', 'products', 'orders', 'reports', 'activity_logs'];
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

// 1. Fetch Customers
$customerQ = $conn->query("SELECT id, name, email, created_at, account_status, approval_status FROM customers ORDER BY id ASC");
while ($customerQ && ($row = $customerQ->fetch_assoc())) {
    $uid = 'U-' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT);
    $ordersQ = $conn->prepare("SELECT COUNT(*) c FROM orders WHERE customer_id = ?");
    $ordersQ->bind_param("i", $row['id']);
    $ordersQ->execute();
    $orderCount = (int) ($ordersQ->get_result()->fetch_assoc()['c'] ?? 0);
    $approvalStatus = trim((string) ($row['approval_status'] ?? 'Approved'));
    $displayStatus = strcasecmp($approvalStatus, 'Approved') === 0 ? ($row['account_status'] ?: 'Active') : 'Pending Approval';
    $users[] = [
        'id' => (int) $row['id'],
        'uid' => $uid,
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => 'Customer',
        'joined' => date('M j, Y', strtotime($row['created_at'])),
        'activity' => $orderCount . ($orderCount === 1 ? ' order' : ' orders'),
        'status' => $displayStatus,
        'approval_status' => $approvalStatus === '' ? 'Approved' : $approvalStatus,
        'type' => 'customer',
    ];
}

// 2. Fetch Sellers
$sellerQ = $conn->query("SELECT id, name, email, created_at, account_status, approval_status FROM sellers ORDER BY id ASC");
while ($sellerQ && ($row = $sellerQ->fetch_assoc())) {
    $uid = 'U-' . str_pad((string) (100 + $row['id']), 3, '0', STR_PAD_LEFT);
    $prodQ = $conn->prepare("SELECT COUNT(*) c FROM products WHERE seller_id = ? OR seller_id IS NULL");
    $prodQ->bind_param("i", $row['id']);
    $prodQ->execute();
    $productCount = (int) ($prodQ->get_result()->fetch_assoc()['c'] ?? 0);
    $approvalStatus = trim((string) ($row['approval_status'] ?? 'Approved'));
    $displayStatus = strcasecmp($approvalStatus, 'Approved') === 0 ? ($row['account_status'] ?: 'Active') : 'Pending Approval';
    $users[] = [
        'id' => (int) $row['id'],
        'uid' => $uid,
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => 'Seller',
        'joined' => date('M j, Y', strtotime($row['created_at'])),
        'activity' => $productCount . ($productCount === 1 ? ' product' : ' products'),
        'status' => $displayStatus,
        'approval_status' => $approvalStatus === '' ? 'Approved' : $approvalStatus,
        'type' => 'seller',
    ];
}

// 3. Fetch Admins (EXCLUSIVE to Super Admin)
$countAdmins = 0;
$adminQuery = $conn->query("SELECT id, name, email, created_at, account_status, is_super_admin FROM admins ORDER BY id ASC");
while ($adminQuery && ($row = $adminQuery->fetch_assoc())) {
    $uid = 'A-' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT);
    $displayStatus = $row['account_status'] ?: 'Active';
    $isAdminSuper = !empty($row['is_super_admin']);
    $users[] = [
        'id' => (int) $row['id'],
        'uid' => $uid,
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $isAdminSuper ? 'Super Admin' : 'Admin',
        'joined' => date('M j, Y', strtotime($row['created_at'])),
        'activity' => 'System Management',
        'status' => $displayStatus,
        'approval_status' => 'Approved',
        'type' => 'admin',
    ];
    if (!$isAdminSuper) {
        $countAdmins++;
    }
}

$allUsers = $users;
$pendingApprovals = array_values(array_filter($allUsers, fn($u) => strcasecmp((string)($u['approval_status'] ?? 'Approved'), 'Approved') !== 0));
$pendingApprovalCount = count($pendingApprovals);

if ($roleFilter !== '' && in_array($roleFilter, ['Customer', 'Seller', 'Admin'], true)) {
    $users = array_values(array_filter($users, fn($u) => $u['role'] === $roleFilter));
}

$countCustomers = 0; $countSellers = 0;
foreach ($allUsers as $u) {
    if ($u['role'] === 'Customer') $countCustomers++;
    elseif ($u['role'] === 'Seller') $countSellers++;
}
$countUsers = count($allUsers);

// --- Products & Orders Logic (Same as Admin) ---
$products = [];
$seenProductNames = [];
$prodRows = $conn->query("SELECT p.id, p.name, p.category, p.price, p.stock, s.name AS seller_name FROM products p LEFT JOIN sellers s ON s.id = p.seller_id ORDER BY p.name ASC, p.id ASC");
while ($prodRows && ($p = $prodRows->fetch_assoc())) {
    $productKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', $p['name']));
    if (isset($seenProductNames[$productKey])) {
        continue;
    }
    $seenProductNames[$productKey] = true;
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
if ($categoryFilter !== '') $products = array_values(array_filter($products, fn($p) => strcasecmp($p['category'], $categoryFilter) === 0));
$totalProducts = count($products); $inStock = 0; $lowOutStock = 0;
foreach ($products as $p) { $p['status'] === 'In Stock' ? $inStock++ : $lowOutStock++; }

$orders = [];
$orderRows = $conn->query("SELECT o.id, o.created_at, o.total_amount, o.status, c.name customer_name, oi.quantity, p.name product_name FROM orders o INNER JOIN customers c ON c.id = o.customer_id LEFT JOIN order_items oi ON oi.order_id = o.id LEFT JOIN products p ON p.id = oi.product_id ORDER BY o.created_at ASC, o.id ASC");
while ($orderRows && ($o = $orderRows->fetch_assoc())) {
    $orders[] = ['id' => $o['id'], 'customer' => $o['customer_name'], 'date' => date('M j, Y', strtotime($o['created_at'])), 'items' => (int) ($o['quantity'] ?? 0) . 'x ' . ($o['product_name'] ?? 'Item'), 'total' => (float) $o['total_amount'], 'status' => $o['status']];
}
if ($orderStatusFilter !== '') $orders = array_values(array_filter($orders, fn($o) => $o['status'] === $orderStatusFilter));
$totalOrders = count($orders); $deliveredCount = count(array_filter($orders, fn($o) => $o['status'] === 'Delivered'));

$reportRows = [];
$rep = $conn->query("SELECT o.created_at, p.name product_name, p.category, oi.quantity, oi.price, (oi.quantity * oi.price) total, o.status, COALESCE(s.name, 'Seller One') seller_name FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id INNER JOIN products p ON p.id = oi.product_id LEFT JOIN sellers s ON s.id = p.seller_id ORDER BY o.created_at ASC");
while ($rep && ($r = $rep->fetch_assoc())) {
    $reportRows[] = ['date' => date('M j, Y', strtotime($r['created_at'])), 'product' => $r['product_name'], 'category' => ucfirst(strtolower($r['category'])), 'qty' => (int) $r['quantity'], 'unit_price' => (float) $r['price'], 'total' => (float) $r['total'], 'status' => $r['status'] === 'Delivered' ? 'Completed' : $r['status'], 'seller' => $r['seller_name']];
}
$totalRevenue = 0; 
$completed = 0;
$cancelled = 0;
foreach ($reportRows as $r) { 
    if ($r['status'] === 'Completed') {
        $totalRevenue += $r['total']; 
        $completed++;
    } elseif ($r['status'] === 'Cancelled') {
        $cancelled++;
    }
}

$selectedUser = null;
foreach ($allUsers as $u) { if ($u['id'] === $viewUser && $u['type'] === $viewType) { $selectedUser = $u; break; } }
$userDetailProducts = [];
if ($selectedUser) {
    $table = $selectedUser['type'];
    if ($table === 'seller') {
        $stmt = $conn->prepare("SELECT p.id, p.name, p.category, p.price, p.stock FROM products p WHERE p.seller_id = ? OR p.seller_id IS NULL ORDER BY p.id DESC LIMIT 6");
    } else {
        $stmt = $conn->prepare("SELECT p.id, p.name, p.category, p.price, p.stock FROM orders o INNER JOIN order_items oi ON oi.order_id = o.id INNER JOIN products p ON p.id = oi.product_id WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT 6");
    }
    if ($stmt) {
        $stmt->bind_param("i", $selectedUser['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($p = $res->fetch_assoc()) {
            $userDetailProducts[] = ['id' => (int) $p['id'], 'name' => $p['name'], 'sku' => 'SKU-'.str_pad($p['id'],3,'0',STR_PAD_LEFT), 'category' => ucfirst(strtolower($p['category'])), 'price' => (float) $p['price'], 'stock' => (int) $p['stock'], 'status' => product_status((int) $p['stock'])];
        }
    }
}

// Prepare Chart Data
$revenueByDate = [];
foreach ($reportRows as $r) {
    if ($r['status'] === 'Completed') {
        $date = $r['date'];
        $revenueByDate[$date] = ($revenueByDate[$date] ?? 0) + $r['total'];
    }
}
// Sort by date key (M j, Y format needs conversion for proper sorting)
uksort($revenueByDate, fn($a, $b) => strtotime($a) - strtotime($b));

$revenueChartLabels = array_keys($revenueByDate);
$revenueChartValues = array_values($revenueByDate);

$userDistLabels = ['Customers', 'Sellers', 'Admins'];
$userDistValues = [$countCustomers, $countSellers, count(array_filter($allUsers, fn($u) => $u['type'] === 'admin'))];

// --- Dashboard Card Percentage Changes (Current Month vs Last Month) ---
$currentMonthStart = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

// Revenue % change
$cmRevQ = $conn->query("SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as total FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.status = 'Delivered' AND o.created_at >= '$currentMonthStart'");
$currentMonthRevenue = $cmRevQ ? (float) $cmRevQ->fetch_assoc()['total'] : 0;
$lmRevQ = $conn->query("SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as total FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.status = 'Delivered' AND o.created_at >= '$lastMonthStart' AND o.created_at <= '$lastMonthEnd 23:59:59'");
$lastMonthRevenue = $lmRevQ ? (float) $lmRevQ->fetch_assoc()['total'] : 0;
$revenueChangePercent = $lastMonthRevenue > 0 ? round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100) : ($currentMonthRevenue > 0 ? 100 : 0);

// Orders % change
$cmOrdQ = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE created_at >= '$currentMonthStart'");
$currentMonthOrders = $cmOrdQ ? (int) $cmOrdQ->fetch_assoc()['cnt'] : 0;
$lmOrdQ = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE created_at >= '$lastMonthStart' AND created_at <= '$lastMonthEnd 23:59:59'");
$lastMonthOrders = $lmOrdQ ? (int) $lmOrdQ->fetch_assoc()['cnt'] : 0;
$ordersChangePercent = $lastMonthOrders > 0 ? round((($currentMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100) : ($currentMonthOrders > 0 ? 100 : 0);

// Users % change (new registrations)
$cmUsrCust = $conn->query("SELECT COUNT(*) as cnt FROM customers WHERE created_at >= '$currentMonthStart'");
$cmUsrSell = $conn->query("SELECT COUNT(*) as cnt FROM sellers WHERE created_at >= '$currentMonthStart'");
$currentMonthNewUsers = ($cmUsrCust ? (int) $cmUsrCust->fetch_assoc()['cnt'] : 0) + ($cmUsrSell ? (int) $cmUsrSell->fetch_assoc()['cnt'] : 0);
$lmUsrCust = $conn->query("SELECT COUNT(*) as cnt FROM customers WHERE created_at >= '$lastMonthStart' AND created_at <= '$lastMonthEnd 23:59:59'");
$lmUsrSell = $conn->query("SELECT COUNT(*) as cnt FROM sellers WHERE created_at >= '$lastMonthStart' AND created_at <= '$lastMonthEnd 23:59:59'");
$lastMonthNewUsers = ($lmUsrCust ? (int) $lmUsrCust->fetch_assoc()['cnt'] : 0) + ($lmUsrSell ? (int) $lmUsrSell->fetch_assoc()['cnt'] : 0);
$usersChangePercent = $lastMonthNewUsers > 0 ? round((($currentMonthNewUsers - $lastMonthNewUsers) / $lastMonthNewUsers) * 100) : ($currentMonthNewUsers > 0 ? 100 : 0);

// Products % change (newly added)
$cmProdQ = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE created_at >= '$currentMonthStart'");
$currentMonthProducts = $cmProdQ ? (int) $cmProdQ->fetch_assoc()['cnt'] : 0;
$lmProdQ = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE created_at >= '$lastMonthStart' AND created_at <= '$lastMonthEnd 23:59:59'");
$lastMonthProducts = $lmProdQ ? (int) $lmProdQ->fetch_assoc()['cnt'] : 0;
$productsChangePercent = $lastMonthProducts > 0 ? round((($currentMonthProducts - $lastMonthProducts) / $lastMonthProducts) * 100) : ($currentMonthProducts > 0 ? 100 : 0);

// --- Latest Orders (for dashboard panel) ---
$latestOrders = [];
$latestOrdQ = $conn->query("SELECT o.id, o.created_at, o.total_amount, o.status, c.name customer_name, oi.quantity, p.name product_name FROM orders o INNER JOIN customers c ON c.id = o.customer_id LEFT JOIN order_items oi ON oi.order_id = o.id LEFT JOIN products p ON p.id = oi.product_id ORDER BY o.created_at DESC LIMIT 5");
while ($latestOrdQ && ($lo = $latestOrdQ->fetch_assoc())) {
    $latestOrders[] = [
        'id' => $lo['id'],
        'customer' => $lo['customer_name'],
        'items' => (int) ($lo['quantity'] ?? 0) . 'x ' . ($lo['product_name'] ?? 'Item'),
        'date' => date('M j, Y \a\t g:i A', strtotime($lo['created_at'])),
        'total' => (float) $lo['total_amount'],
        'status' => $lo['status'],
    ];
}

// --- Order List for Dashboard Table (with period filter) ---
$orderListPeriod = $_GET['period'] ?? 'all';
$orderListSql = "SELECT o.id, o.created_at, o.total_amount, o.status, c.name customer_name, oi.quantity, p.name product_name FROM orders o INNER JOIN customers c ON c.id = o.customer_id LEFT JOIN order_items oi ON oi.order_id = o.id LEFT JOIN products p ON p.id = oi.product_id";
if ($orderListPeriod === 'today') {
    $orderListSql .= " WHERE DATE(o.created_at) = CURDATE()";
} elseif ($orderListPeriod === 'weekly') {
    $orderListSql .= " WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($orderListPeriod === 'monthly') {
    $orderListSql .= " WHERE o.created_at >= '$currentMonthStart'";
}
$orderListSql .= " ORDER BY o.created_at DESC LIMIT 20";
$dashboardOrderList = [];
$olQ = $conn->query($orderListSql);
while ($olQ && ($ol = $olQ->fetch_assoc())) {
    $dashboardOrderList[] = [
        'id' => $ol['id'],
        'customer' => $ol['customer_name'],
        'items' => (int) ($ol['quantity'] ?? 0) . 'x ' . ($ol['product_name'] ?? 'Item'),
        'date' => date('M j, Y', strtotime($ol['created_at'])),
        'total' => (float) $ol['total_amount'],
        'status' => $ol['status']
    ];
}
