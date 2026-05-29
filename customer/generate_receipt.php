<?php
session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    http_response_code(403);
    die('Unauthorized access.');
}

$customerId = (int) ($_SESSION['user_id'] ?? 0);
$sessionEmail = trim((string) ($_SESSION['email'] ?? ''));
$orderId = trim((string) ($_GET['id'] ?? ''));

if ($customerId <= 0 && $sessionEmail !== '') {
    $customerStmt = $conn->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
    $customerStmt->bind_param("s", $sessionEmail);
    $customerStmt->execute();
    $customer = $customerStmt->get_result()->fetch_assoc();
    if ($customer) {
        $customerId = (int) $customer['id'];
        $_SESSION['user_id'] = $customerId;
    }
}

if ($customerId <= 0 || $orderId === '') {
    http_response_code(400);
    die('Invalid receipt request.');
}

$orderStmt = $conn->prepare("
    SELECT o.id, o.created_at, o.total_amount, o.status, o.payment_method,
           c.name AS customer_name, c.email AS customer_email, c.address
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    WHERE o.id = ? AND o.customer_id = ?
    LIMIT 1
");
$orderStmt->bind_param("si", $orderId, $customerId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(404);
    die('Receipt not found.');
}

$itemsStmt = $conn->prepare("
    SELECT p.name AS product_name, oi.quantity, oi.price, (oi.quantity * oi.price) AS subtotal
    FROM order_items oi
    INNER JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$itemsStmt->bind_param("s", $orderId);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($items)) {
    http_response_code(404);
    die('No items found for this receipt.');
}

function receipt_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$receiptNo = 'REC-' . strtoupper(substr(md5($orderId), 0, 8));
$orderDate = date('M j, Y g:i A', strtotime($order['created_at']));
$paymentMethod = $order['payment_method'] ?: 'Cash';
$itemsHtml = '';

foreach ($items as $item) {
    $itemsHtml .= '<tr>
        <td>' . receipt_esc($item['product_name']) . '</td>
        <td style="text-align:center;">' . (int) $item['quantity'] . '</td>
        <td style="text-align:right;">&#8369;' . number_format((float) $item['price'], 2) . '</td>
        <td style="text-align:right;">&#8369;' . number_format((float) $item['subtotal'], 2) . '</td>
    </tr>';
}

$html = '<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #111; font-size: 13px; line-height: 1.45; padding: 22px; }
        .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 16px; margin-bottom: 24px; }
        .title { font-size: 23px; font-weight: bold; text-transform: uppercase; }
        .sub { color: #555; font-size: 15px; margin-top: 4px; }
        .details { width: 100%; margin-bottom: 28px; border-collapse: collapse; }
        .details td { width: 50%; vertical-align: top; padding: 4px 0; }
        .label { color: #666; font-size: 10px; text-transform: uppercase; font-weight: bold; margin-top: 10px; }
        .value { font-weight: bold; font-size: 13px; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .items th { background: #f2f3f5; border-bottom: 1px solid #111; padding: 9px; text-align: left; font-size: 10px; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #eceef1; padding: 9px; }
        .total { text-align: right; font-size: 24px; font-weight: bold; border-top: 2px solid #111; padding-top: 7px; display: inline-block; }
        .status { display: inline-block; border: 1px solid #111; border-radius: 4px; padding: 4px 9px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">School Supply Bookstore</div>
        <div class="sub">Customer Order Receipt</div>
    </div>
    <table class="details">
        <tr>
            <td>
                <div class="label">Receipt Number</div>
                <div class="value">' . receipt_esc($receiptNo) . '</div>
                <div class="label">Order ID</div>
                <div class="value">' . receipt_esc($order['id']) . '</div>
                <div class="label">Order Date</div>
                <div class="value">' . receipt_esc($orderDate) . '</div>
            </td>
            <td>
                <div class="label">Customer</div>
                <div class="value">' . receipt_esc($order['customer_name']) . '</div>
                <div class="label">Email</div>
                <div class="value">' . receipt_esc($order['customer_email']) . '</div>
                <div class="label">Address</div>
                <div class="value">' . receipt_esc($order['address']) . '</div>
            </td>
        </tr>
    </table>
    <table class="items">
        <thead>
            <tr>
                <th>Product</th>
                <th style="text-align:center;">Qty</th>
                <th style="text-align:right;">Unit Price</th>
                <th style="text-align:right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>' . $itemsHtml . '</tbody>
    </table>
    <table style="width:100%;">
        <tr>
            <td>
                <div class="label">Payment Method</div>
                <div class="value">' . receipt_esc($paymentMethod) . '</div>
                <div class="label">Order Status</div>
                <div class="status">' . receipt_esc($order['status']) . '</div>
            </td>
            <td style="text-align:right;">
                <div class="label">Order Total</div>
                <div class="total">&#8369;' . number_format((float) $order['total_amount'], 2) . '</div>
            </td>
        </tr>
    </table>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('Receipt_' . preg_replace('/[^A-Za-z0-9_-]/', '', $orderId) . '.pdf', ['Attachment' => true]);
