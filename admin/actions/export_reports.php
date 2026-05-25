<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?action=login');
    exit;
}

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

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="admin-reports.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Product', 'Category', 'Qty', 'Unit Price', 'Total', 'Status', 'Seller']);
foreach ($reportRows as $r) {
    fputcsv($out, [$r['date'], $r['product'], $r['category'], $r['qty'], $r['unit_price'], $r['total'], $r['status'], $r['seller']]);
}
fclose($out);
exit;
