<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    die('Unauthorized access.');
}

$sellerId = (int) $_SESSION['user_id'];
$orderId = $_GET['id'] ?? '';

if (empty($orderId)) {
    die('Order ID is required.');
}

// 1. Fetch Order Details to verify ownership and status
$orderQuery = $conn->prepare("
    SELECT o.*, c.name as customer_name, c.email as customer_email, c.address 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    WHERE o.id = ? 
    LIMIT 1
");
$orderQuery->bind_param("s", $orderId);
$orderQuery->execute();
$order = $orderQuery->get_result()->fetch_assoc();

if (!$order) {
    die('Order not found.');
}

// Check status requirements: "Delivered" or "Fulfilled" (The system uses 'Delivered' for completed orders)
if ($order['status'] !== 'Delivered' && $order['status'] !== 'Fulfilled') {
    die('Receipt can only be generated for Delivered or Fulfilled orders.');
}

// 2. Fetch Order Items for this seller
// (In a multi-seller system, a seller should only see their items, but the receipt usually shows the whole order if it's per order)
// The prompt says "Generate Receipt" on the Seller order detail page. Usually, an order belongs to one seller or contains items from the seller.
$itemsQuery = $conn->prepare("
    SELECT p.name as product_name, oi.quantity, oi.price, (oi.quantity * oi.price) as subtotal, s.name as seller_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN sellers s ON p.seller_id = s.id
    WHERE oi.order_id = ? AND (p.seller_id = ? OR p.seller_id IS NULL)
");
$itemsQuery->bind_param("si", $orderId, $sellerId);
$itemsQuery->execute();
$items = $itemsQuery->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($items)) {
    die('No items found for this order in your shop.');
}

// 3. Prepare Receipt Details
$receiptNo = 'REC-' . strtoupper(substr(md5($orderId), 0, 8));
$sellerName = $items[0]['seller_name'] ?? 'School Supply Bookstore';
$orderDate = date('M j, Y · g:i A', strtotime($order['created_at']));
$paymentMethod = $order['payment_method'] ?? 'Cash';
$orderTotal = 0;
foreach ($items as $item) {
    $orderTotal += $item['subtotal'];
}

// 4. Generate HTML
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Helvetica", "Arial", sans-serif; color: #111; font-size: 14px; line-height: 1.5; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
        .system-name { font-size: 24px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
        .document-title { font-size: 18px; color: #555; }
        .details-table { width: 100%; margin-bottom: 40px; }
        .details-table td { vertical-align: top; padding: 5px 0; }
        .label { color: #666; font-size: 11px; text-transform: uppercase; font-weight: bold; }
        .val { font-size: 14px; font-weight: bold; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .items-table th { background: #f0f0f0; border-bottom: 1px solid #000; text-align: left; padding: 10px; font-size: 11px; text-transform: uppercase; }
        .items-table td { border-bottom: 1px solid #eee; padding: 10px; font-size: 13px; }
        .footer-table { width: 100%; margin-top: 20px; }
        .total-row td { padding-top: 20px; text-align: right; }
        .total-label { font-size: 16px; font-weight: bold; }
        .total-val { font-size: 24px; font-weight: bold; border-top: 2px solid #000; display: inline-block; padding-top: 5px; }
        .status-badge { display: inline-block; padding: 4px 10px; border: 1px solid #000; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        <div class="system-name">School Supply Bookstore</div>
        <div class="document-title">Official Receipt</div>
    </div>

    <table class="details-table">
        <tr>
            <td style="width: 50%;">
                <div class="label">Receipt Number</div>
                <div class="val">' . $receiptNo . '</div>
                <div class="label" style="margin-top:15px;">Order Date</div>
                <div class="val">' . $orderDate . '</div>
            </td>
            <td style="width: 50%;">
                <div class="label">Customer</div>
                <div class="val">' . esc_pdf($order['customer_name']) . '</div>
                <div class="label" style="margin-top:15px;">Seller</div>
                <div class="val">' . esc_pdf($sellerName) . '</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Unit Price</th>
                <th style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>';

foreach ($items as $item) {
    $html .= '
            <tr>
                <td>' . esc_pdf($item['product_name']) . '</td>
                <td style="text-align: center;">' . $item['quantity'] . '</td>
                <td style="text-align: right;">₱' . number_format($item['price'], 2) . '</td>
                <td style="text-align: right;">₱' . number_format($item['subtotal'], 2) . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td style="width: 50%;">
                <div class="label">Payment Method</div>
                <div class="val">' . $paymentMethod . '</div>
                <div class="label" style="margin-top:15px;">Status</div>
                <div class="status-badge">' . $order['status'] . '</div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="total-label">Order Total</div>
                <div class="total-val">₱' . number_format($orderTotal, 2) . '</div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 60px; text-align: center; font-size: 11px; color: #888;">
        Thank you for your purchase from ' . esc_pdf($sellerName) . '!
    </div>
</body>
</html>';

function esc_pdf($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// 5. Initialize DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 6. Stream PDF
$filename = 'Receipt_' . $orderId . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
