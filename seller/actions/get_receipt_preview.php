<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    http_response_code(403);
    die('Unauthorized access.');
}

$sellerId = (int) $_SESSION['user_id'];
$orderId = $_GET['id'] ?? '';

if (empty($orderId)) {
    http_response_code(400);
    die('Order ID is required.');
}

// 1. Fetch Order Details
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
    http_response_code(404);
    die('Order not found.');
}

// 2. Fetch Order Items for this seller
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
    http_response_code(404);
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

function esc_preview($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// 4. Output the HTML Fragment (matching the PDF design)
?>
<div class="receipt-preview-container" style="font-family: 'Inter', Helvetica, Arial, sans-serif; color: #111; font-size: 14px; line-height: 1.5; padding: 10px; background: #fff; max-width: 800px; margin: 0 auto;">
    <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px;">
        <div style="font-size: 20px; font-weight: 800; text-transform: uppercase; margin-bottom: 2px;">School Supply Bookstore</div>
        <div style="font-size: 14px; color: #666; font-weight: 600;">Official Receipt Preview</div>
    </div>

    <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding: 2px 0;">
                <div style="color: #666; font-size: 10px; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Receipt Number</div>
                <div style="font-size: 14px; font-weight: 700;"><?= $receiptNo ?></div>
                <div style="color: #666; font-size: 10px; text-transform: uppercase; font-weight: 700; margin-top: 12px; margin-bottom: 2px;">Order Date</div>
                <div style="font-size: 14px; font-weight: 700;"><?= $orderDate ?></div>
            </td>
            <td style="width: 50%; vertical-align: top; padding: 2px 0;">
                <div style="color: #666; font-size: 10px; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Customer</div>
                <div style="font-size: 14px; font-weight: 700;"><?= esc_preview($order['customer_name']) ?></div>
                <div style="color: #666; font-size: 10px; text-transform: uppercase; font-weight: 700; margin-top: 12px; margin-bottom: 2px;">Seller</div>
                <div style="font-size: 14px; font-weight: 700;"><?= esc_preview($sellerName) ?></div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="border-bottom: 1px solid #000; text-align: left; padding: 10px; font-size: 10px; text-transform: uppercase; font-weight: 700;">Product Name</th>
                <th style="border-bottom: 1px solid #000; text-align: center; padding: 10px; font-size: 10px; text-transform: uppercase; font-weight: 700;">Qty</th>
                <th style="border-bottom: 1px solid #000; text-align: right; padding: 10px; font-size: 10px; text-transform: uppercase; font-weight: 700;">Unit Price</th>
                <th style="border-bottom: 1px solid #000; text-align: right; padding: 10px; font-size: 10px; text-transform: uppercase; font-weight: 700;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td style="border-bottom: 1px solid #f1f3f5; padding: 10px; font-size: 13px;"><?= esc_preview($item['product_name']) ?></td>
                <td style="border-bottom: 1px solid #f1f3f5; padding: 10px; font-size: 13px; text-align: center;"><?= $item['quantity'] ?></td>
                <td style="border-bottom: 1px solid #f1f3f5; padding: 10px; font-size: 13px; text-align: right;">₱<?= number_format($item['price'], 2) ?></td>
                <td style="border-bottom: 1px solid #f1f3f5; padding: 10px; font-size: 13px; text-align: right; font-weight: 600;">₱<?= number_format($item['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table style="width: 100%;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div style="color: #666; font-size: 10px; text-transform: uppercase; font-weight: 700; margin-bottom: 2px;">Payment Method</div>
                <div style="font-size: 14px; font-weight: 700;"><?= $paymentMethod ?></div>
                <div style="color: #666; font-size: 10px; text-transform: uppercase; font-weight: 700; margin-top: 12px; margin-bottom: 2px;">Status</div>
                <div style="display: inline-block; padding: 3px 8px; border: 1px solid #111; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase;"><?= $order['status'] ?></div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <div style="color: #666; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px;">Order Total</div>
                <div style="font-size: 28px; font-weight: 900; border-top: 2px solid #111; display: inline-block; padding-top: 5px; color: #111;">₱<?= number_format($orderTotal, 2) ?></div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 40px; text-align: center; font-size: 11px; color: #888; font-style: italic;">
        This is a digital preview. Click "Download PDF" to generate the official document.
    </div>
</div>
