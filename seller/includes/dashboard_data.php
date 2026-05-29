<?php

$sellerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$sellerEmail = $_SESSION['email'] ?? 'seller@gmail.com';
$appBaseUrl = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

ensureProfileImageColumn($conn, 'sellers');

$tab = $_GET['tab'] ?? 'inventory';
$allowedTabs = ['inventory', 'sales', 'orders'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'inventory';
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

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$salesDateFrom = trim($_GET['date_from'] ?? '');
$salesDateTo = trim($_GET['date_to'] ?? '');
$viewOrderId = trim($_GET['view'] ?? '');

function isValidReportDate(string $date): bool
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
}

function sortOrdersByStatus(array $orders): array
{
    $priority = ['Pending' => 1, 'Processing' => 2, 'Delivered' => 3, 'Fulfilled' => 4, 'Cancelled' => 5];
    usort($orders, function ($a, $b) use ($priority) {
        $pa = $priority[$a['status'] ?? ''] ?? 99;
        $pb = $priority[$b['status'] ?? ''] ?? 99;
        return $pa <=> $pb;
    });
    return $orders;
}

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
$seenInventoryNames = [];
$inventoryRows = array_values(array_filter($inventoryRows, function ($item) use (&$seenInventoryNames) {
    $productKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', $item['name']));
    if (isset($seenInventoryNames[$productKey])) {
        return false;
    }
    $seenInventoryNames[$productKey] = true;
    return true;
}));

$totalProducts = count($inventoryRows);
$inventoryPage = (int) ($_GET['inventory_page'] ?? 1);
$inventoryPager = paginateRows($inventoryRows, $inventoryPage, 10);
$inventoryPageRows = $inventoryPager['rows'];
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
";
if ($salesDateFrom !== '' && isValidReportDate($salesDateFrom)) {
    $salesSql .= " AND DATE(o.created_at) >= '" . $conn->real_escape_string($salesDateFrom) . "'";
}
if ($salesDateTo !== '' && isValidReportDate($salesDateTo)) {
    $salesSql .= " AND DATE(o.created_at) <= '" . $conn->real_escape_string($salesDateTo) . "'";
}
$salesSql .= " ORDER BY o.created_at ASC, oi.id ASC";
$salesStmt = $conn->prepare($salesSql);
$salesRows = [];
if ($salesStmt) {
    $salesStmt->bind_param("i", $sellerId);
    $salesStmt->execute();
    $salesRows = $salesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
$salesPage = (int) ($_GET['sales_page'] ?? 1);
$salesPager = paginateRows($transactions, $salesPage, 10);
$salesPageRows = $salesPager['rows'];

$ordersSql = "
SELECT
    o.id,
    o.created_at,
    o.total_amount,
    o.status,
    o.payment_method,
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

$ordersSql .= " ORDER BY FIELD(o.status, 'Pending', 'Processing', 'Delivered', 'Fulfilled', 'Cancelled'), o.created_at DESC ";
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
$ordersPage = (int) ($_GET['orders_page'] ?? 1);
$ordersPager = paginateRows($ordersRows, $ordersPage, 10);
$ordersPageRows = $ordersPager['rows'];

$selectedOrder = null;
$selectedOrderItems = [];
if ($viewOrderId !== '') {
    $detailStmt = $conn->prepare("
        SELECT
            o.id,
            o.created_at,
            o.total_amount,
            o.status,
            o.payment_method,
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
