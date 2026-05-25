<?php
session_start();
require_once '../../config/db.php';
require_once '../includes/helpers.php';
require_once '../../activity_logger.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../../index.php?action=login');
    exit;
}

$sellerId  = (int) ($_SESSION['user_id'] ?? 0);
$orderId   = trim($_POST['order_id'] ?? '');

if ($orderId !== '') {
    try {
        $conn->begin_transaction();

        $orderStmt = $conn->prepare("SELECT status FROM orders WHERE id = ? FOR UPDATE");
        $orderStmt->bind_param("s", $orderId);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();

        if (!$order || !in_array($order['status'], ['Pending', 'Processing'], true)) {
            $conn->rollback();
            header('Location: ../dashboard.php?tab=orders&view=' . urlencode($orderId));
            exit;
        }

        $itemsStmt = $conn->prepare("
            SELECT p.id, p.name, p.stock, oi.quantity
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
            FOR UPDATE
        ");
        $itemsStmt->bind_param("s", $orderId);
        $itemsStmt->execute();
        $itemRows = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $items = [];
        foreach ($itemRows as $item) {
            $productId = (int) $item['id'];
            if (!isset($items[$productId])) {
                $items[$productId] = [
                    'id' => $productId,
                    'name' => $item['name'],
                    'stock' => (int) $item['stock'],
                    'quantity' => 0,
                ];
            }
            $items[$productId]['quantity'] += (int) $item['quantity'];
        }

        if (empty($items)) {
            throw new Exception('This order has no items to fulfill.');
        }

        foreach ($items as $item) {
            $stock = (int) $item['stock'];
            $qty = (int) $item['quantity'];
            if ($stock < $qty) {
                throw new Exception($item['name'] . ' has only ' . $stock . ' in stock.');
            }
        }

        $updateProduct = $conn->prepare("UPDATE products SET stock = ?, status = ? WHERE id = ?");
        foreach ($items as $item) {
            $newStock = (int) $item['stock'] - (int) $item['quantity'];
            $newProductStatus = productStatusFromStock($newStock);
            $productId = (int) $item['id'];
            $updateProduct->bind_param("isi", $newStock, $newProductStatus, $productId);
            $updateProduct->execute();
        }

        $newStatus = 'Delivered';
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("ss", $newStatus, $orderId);
        $stmt->execute();

        $conn->commit();

        $sellerName = $_SESSION['name'] ?? ('Seller #' . $sellerId);
        logActivity($conn, $sellerId, $sellerName, 'seller', 'ORDER_UPDATED',
            'Order #' . $orderId . ' status updated to Delivered and stock deducted');
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Ignore rollback failure and show the original fulfillment error.
        }
        header('Location: ../dashboard.php?tab=orders&view=' . urlencode($orderId) . '&fulfill_error=' . urlencode($e->getMessage()));
        exit;
    }
}

header('Location: ../dashboard.php?tab=orders&view=' . urlencode($orderId));
exit;
