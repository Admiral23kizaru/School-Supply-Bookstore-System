<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../../index.php?action=login');
    exit;
}

$sellerId = (int) ($_SESSION['user_id'] ?? 0);

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
