<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../../index.php?action=login');
    exit;
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isValidReportDate(string $date): bool
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
}

$sellerId = (int) ($_SESSION['user_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

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
if ($dateFrom !== '' && isValidReportDate($dateFrom)) {
    $salesSql .= " AND DATE(o.created_at) >= '" . $conn->real_escape_string($dateFrom) . "'";
}
if ($dateTo !== '' && isValidReportDate($dateTo)) {
    $salesSql .= " AND DATE(o.created_at) <= '" . $conn->real_escape_string($dateTo) . "'";
}
$salesSql .= " ORDER BY o.created_at ASC, oi.id ASC";

$salesStmt = $conn->prepare($salesSql);
$salesRows = [];
$totalAmount = 0;
if ($salesStmt) {
    $salesStmt->bind_param("i", $sellerId);
    $salesStmt->execute();
    $salesRows = $salesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($salesRows as $row) {
        $totalAmount += (float) $row['line_total'];
    }
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
    <title>Seller Sales Report</title>
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
    <h1>School Supply — Seller Sales Report</h1>
    <div class="meta">
        Generated: <?= esc(date('M j, Y g:i A')) ?><br>
        Date range: <?= esc($rangeLabel) ?><br>
        Total transactions: <?= count($salesRows) ?>
    </div>
    <table>
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
        <?php if (count($salesRows) === 0): ?>
            <tr><td colspan="8">No transactions found for this date range.</td></tr>
        <?php else: ?>
            <?php foreach ($salesRows as $row): ?>
                <tr>
                    <td><?= esc($row['order_id']) ?></td>
                    <td><?= esc($row['order_date']) ?></td>
                    <td><?= esc($row['product_name']) ?></td>
                    <td><?= esc(ucfirst(strtolower($row['category']))) ?></td>
                    <td><?= (int) $row['quantity'] ?></td>
                    <td>₱<?= number_format((float) $row['unit_price'], 2) ?></td>
                    <td>₱<?= number_format((float) $row['line_total'], 2) ?></td>
                    <td><?= esc($row['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align:right;">Grand Total</td>
                <td colspan="2">₱<?= number_format($totalAmount, 2) ?></td>
            </tr>
        </tfoot>
    </table>
    <script>window.addEventListener('load', () => { setTimeout(() => window.print(), 300); });</script>
</body>
</html>
