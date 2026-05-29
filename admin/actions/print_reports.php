<?php
session_start();
require_once '../../config/db.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?action=login');
    exit;
}

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$reportSql = "
SELECT o.created_at, p.name product_name, p.category, oi.quantity, oi.price, (oi.quantity * oi.price) total, o.status, COALESCE(s.name, 'Seller One') seller_name
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
INNER JOIN products p ON p.id = oi.product_id
LEFT JOIN sellers s ON s.id = p.seller_id
WHERE 1=1
";
if ($dateFrom !== '' && isValidReportDate($dateFrom)) {
    $reportSql .= " AND DATE(o.created_at) >= '" . $conn->real_escape_string($dateFrom) . "'";
}
if ($dateTo !== '' && isValidReportDate($dateTo)) {
    $reportSql .= " AND DATE(o.created_at) <= '" . $conn->real_escape_string($dateTo) . "'";
}
$reportSql .= " ORDER BY o.created_at ASC";

$reportRows = [];
$totalAmount = 0;
$rep = $conn->query($reportSql);
while ($rep && ($r = $rep->fetch_assoc())) {
    $lineTotal = (float) $r['total'];
    $totalAmount += $lineTotal;
    $reportRows[] = [
        'date' => date('M j, Y', strtotime($r['created_at'])),
        'product' => $r['product_name'],
        'category' => ucfirst(strtolower($r['category'])),
        'qty' => (int) $r['quantity'],
        'unit_price' => (float) $r['price'],
        'total' => $lineTotal,
        'status' => $r['status'] === 'Delivered' ? 'Completed' : $r['status'],
        'seller' => $r['seller_name'],
    ];
}

$rangeLabel = 'All dates';
if ($dateFrom !== '' && $dateTo !== '') {
    $rangeLabel = $dateFrom . ' to ' . $dateTo;
} elseif ($dateFrom !== '') {
    $rangeLabel = 'From ' . $dateFrom;
} elseif ($dateTo !== '') {
    $rangeLabel = 'Up to ' . $dateTo;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Sales Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 24px; font-size: 12px; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .meta { color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        tfoot td { font-weight: bold; background: #f9fafb; }
        .actions { margin-bottom: 16px; }
        @media print { .actions { display: none; } body { margin: 12px; } }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>
    <h1>School Supply — Sales Report</h1>
    <div class="meta">
        Generated: <?= esc(date('M j, Y g:i A')) ?><br>
        Date range: <?= esc($rangeLabel) ?><br>
        Total transactions: <?= count($reportRows) ?>
    </div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
                <th>Status</th>
                <th>Seller</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($reportRows) === 0): ?>
            <tr><td colspan="8">No transactions found for this date range.</td></tr>
        <?php else: ?>
            <?php foreach ($reportRows as $r): ?>
                <tr>
                    <td><?= esc($r['date']) ?></td>
                    <td><?= esc($r['product']) ?></td>
                    <td><?= esc($r['category']) ?></td>
                    <td><?= $r['qty'] ?></td>
                    <td>₱<?= number_format($r['unit_price'], 2) ?></td>
                    <td>₱<?= number_format($r['total'], 2) ?></td>
                    <td><?= esc($r['status']) ?></td>
                    <td><?= esc($r['seller']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right;">Grand Total</td>
                <td colspan="3">₱<?= number_format($totalAmount, 2) ?></td>
            </tr>
        </tfoot>
    </table>
    <script>window.addEventListener('load', () => { setTimeout(() => window.print(), 300); });</script>
</body>
</html>
