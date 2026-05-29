<?php

ensureAccountStatusColumns($conn);
ensureProfileImageColumn($conn, 'admins');
ensureApprovalStatusColumn($conn, 'customers');
ensureApprovalStatusColumn($conn, 'sellers');

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$adminName = 'Admin Account';
$adminEmail = $_SESSION['email'] ?? 'admin@gmail.com';
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
$allowedTabs = ['dashboard', 'profile', 'approvals', 'users', 'products', 'orders', 'reports'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'dashboard';
}

function paginateRows(array $rows, int $page, int $perPage = 10): array
{
    $total = count($rows);
    $lastPage = max(1, (int) ceil($total / $perPage));
    $current = min(max(1, $page), $lastPage);
    $offset = ($current - 1) * $perPage;

    return [
        'rows' => array_slice($rows, $offset, $perPage),
        'current' => $current,
        'last' => $lastPage,
        'total' => $total,
        'from' => $total === 0 ? 0 : ($offset + 1),
        'to' => $total === 0 ? 0 : min($offset + $perPage, $total),
    ];
}

$roleFilter = trim($_GET['role'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$orderStatusFilter = trim($_GET['status'] ?? '');
$reportDateFrom = trim($_GET['date_from'] ?? '');
$reportDateTo = trim($_GET['date_to'] ?? '');
$search = trim($_GET['search'] ?? '');
$viewUser = (int) ($_GET['user'] ?? 0);
$viewType = trim($_GET['type'] ?? 'customer');

$users = [];
$customerQ = $conn->query("SELECT id, name, email, created_at, account_status, approval_status FROM customers ORDER BY id ASC");
while ($customerQ && ($row = $customerQ->fetch_assoc())) {
    $uid = 'U-' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT);
    $ordersQ = $conn->prepare("SELECT COUNT(*) c FROM orders WHERE customer_id = ?");
    $ordersQ->bind_param("i", $row['id']);
    $ordersQ->execute();
    $orderCount = (int) ($ordersQ->get_result()->fetch_assoc()['c'] ?? 0);
    $approvalStatus = trim((string) ($row['approval_status'] ?? 'Approved'));
    $displayStatus = strcasecmp($approvalStatus, 'Approved') === 0
        ? ($row['account_status'] ?: 'Active')
        : ('Pending Approval');
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

$sellerQ = $conn->query("SELECT id, name, email, created_at, account_status, approval_status FROM sellers ORDER BY id ASC");
while ($sellerQ && ($row = $sellerQ->fetch_assoc())) {
    $uid = 'U-' . str_pad((string) (100 + $row['id']), 3, '0', STR_PAD_LEFT);
    $prodQ = $conn->prepare("SELECT COUNT(*) c FROM products WHERE seller_id = ? OR seller_id IS NULL");
    $prodQ->bind_param("i", $row['id']);
    $prodQ->execute();
    $productCount = (int) ($prodQ->get_result()->fetch_assoc()['c'] ?? 0);
    $approvalStatus = trim((string) ($row['approval_status'] ?? 'Approved'));
    $displayStatus = strcasecmp($approvalStatus, 'Approved') === 0
        ? ($row['account_status'] ?: 'Active')
        : ('Pending Approval');
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

$allUsers = $users;
$pendingApprovals = array_values(array_filter(
    $allUsers,
    fn($u) => strcasecmp((string) ($u['approval_status'] ?? 'Approved'), 'Approved') !== 0
));
$pendingApprovalCount = count($pendingApprovals);
$approvalsPage = (int) ($_GET['approvals_page'] ?? 1);
$approvalsPager = paginateRows($pendingApprovals, $approvalsPage, 10);
$pendingApprovalsPageRows = $approvalsPager['rows'];

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
$usersPage = (int) ($_GET['users_page'] ?? 1);
$usersPager = paginateRows($users, $usersPage, 10);
$usersPageRows = $usersPager['rows'];

$products = [];
$seenProductNames = [];
$productSql = "SELECT p.id, p.name, p.category, p.price, p.stock, s.name AS seller_name FROM products p LEFT JOIN sellers s ON s.id = p.seller_id ORDER BY p.name ASC, p.id ASC";
$prodRows = $conn->query($productSql);
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
if ($categoryFilter !== '') {
    $products = array_values(array_filter($products, fn($p) => strcasecmp($p['category'], $categoryFilter) === 0));
}

$totalProducts = count($products);
$productsPage = (int) ($_GET['products_page'] ?? 1);
$productsPager = paginateRows($products, $productsPage, 10);
$productsPageRows = $productsPager['rows'];
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
$orders = sortOrdersByStatus($orders);
$ordersPage = (int) ($_GET['orders_page'] ?? 1);
$ordersPager = paginateRows($orders, $ordersPage, 10);
$ordersPageRows = $ordersPager['rows'];
$dashboardOrdersPage = (int) ($_GET['dashboard_orders_page'] ?? 1);
$dashboardRecentOrders = array_values(array_reverse($orders));
$dashboardOrdersPager = paginateRows($dashboardRecentOrders, $dashboardOrdersPage, 10);
$dashboardOrdersPageRows = $dashboardOrdersPager['rows'];

$viewOrderId = trim($_GET['view'] ?? '');
$selectedOrder = null;
$selectedOrderItems = [];

if ($viewOrderId !== '') {
    $detailStmt = $conn->prepare("
        SELECT o.id, o.created_at, o.total_amount, o.status, c.name AS customer_name, c.address 
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
            SELECT p.name, p.category, oi.quantity, oi.price, (oi.quantity * oi.price) AS subtotal, COALESCE(s.name, 'Seller One') AS seller_name
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            LEFT JOIN sellers s ON s.id = p.seller_id
            WHERE oi.order_id = ?
        ");
        if ($itemStmt) {
            $itemStmt->bind_param("s", $viewOrderId);
            $itemStmt->execute();
            $selectedOrderItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}
$orderItemsPage = (int) ($_GET['order_items_page'] ?? 1);
$orderItemsPager = paginateRows($selectedOrderItems, $orderItemsPage, 10);
$orderItemsPageRows = $orderItemsPager['rows'];

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
WHERE 1=1
";
if ($reportDateFrom !== '' && isValidReportDate($reportDateFrom)) {
    $reportSql .= " AND DATE(o.created_at) >= '" . $conn->real_escape_string($reportDateFrom) . "'";
}
if ($reportDateTo !== '' && isValidReportDate($reportDateTo)) {
    $reportSql .= " AND DATE(o.created_at) <= '" . $conn->real_escape_string($reportDateTo) . "'";
}
$reportSql .= " ORDER BY o.created_at ASC";
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
$reportsPage = (int) ($_GET['reports_page'] ?? 1);
$reportsPager = paginateRows($reportRows, $reportsPage, 10);
$reportPageRows = $reportsPager['rows'];

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

$selectedUser = null;
foreach ($users as $u) {
    if ($u['id'] === $viewUser && $u['type'] === $viewType) {
        $selectedUser = $u;
        break;
    }
}

$userDetailProducts = [];
if ($selectedUser) {
    if ($selectedUser['type'] === 'seller') {
        $sellerDetailStmt = $conn->prepare("
            SELECT p.id, p.name, p.category, p.price, p.stock
            FROM products p
            WHERE p.seller_id = ? OR p.seller_id IS NULL
            ORDER BY p.id DESC
            LIMIT 6
        ");
        if ($sellerDetailStmt) {
            $sellerDetailStmt->bind_param("i", $selectedUser['id']);
            $sellerDetailStmt->execute();
            $res = $sellerDetailStmt->get_result();
            while ($p = $res->fetch_assoc()) {
                $userDetailProducts[] = [
                    'id' => (int) $p['id'],
                    'name' => $p['name'],
                    'sku' => 'SKU-' . str_pad((string) $p['id'], 3, '0', STR_PAD_LEFT),
                    'category' => ucfirst(strtolower($p['category'])),
                    'price' => (float) $p['price'],
                    'stock' => (int) $p['stock'],
                    'status' => product_status((int) $p['stock']),
                ];
            }
        }
    } elseif ($selectedUser['type'] === 'customer') {
        $customerDetailStmt = $conn->prepare("
            SELECT p.id, p.name, p.category, p.price, p.stock, o.created_at
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            INNER JOIN products p ON p.id = oi.product_id
            WHERE o.customer_id = ?
            ORDER BY o.created_at DESC, oi.id DESC
            LIMIT 6
        ");
        if ($customerDetailStmt) {
            $customerDetailStmt->bind_param("i", $selectedUser['id']);
            $customerDetailStmt->execute();
            $res = $customerDetailStmt->get_result();
            while ($p = $res->fetch_assoc()) {
                $userDetailProducts[] = [
                    'id' => (int) $p['id'],
                    'name' => $p['name'],
                    'sku' => 'SKU-' . str_pad((string) $p['id'], 3, '0', STR_PAD_LEFT),
                    'category' => ucfirst(strtolower($p['category'])),
                    'price' => (float) $p['price'],
                    'stock' => (int) $p['stock'],
                    'status' => product_status((int) $p['stock']),
                ];
            }
        }
    }
}
$userDetailPage = (int) ($_GET['user_items_page'] ?? 1);
$userDetailPager = paginateRows($userDetailProducts, $userDetailPage, 10);
$userDetailPageRows = $userDetailPager['rows'];
