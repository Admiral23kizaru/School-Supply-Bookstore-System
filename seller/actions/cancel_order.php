<?php
session_start();
require_once '../../config/db.php';
require_once '../../activity_logger.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../../index.php?action=login');
    exit;
}

$sellerId = (int) ($_SESSION['user_id'] ?? 0);
$orderId = trim((string) ($_POST['order_id'] ?? ''));

if ($orderId !== '') {
    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? FOR UPDATE");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order || !in_array($order['status'], ['Pending', 'Processing'], true)) {
            $conn->rollback();
            header('Location: ../dashboard.php?tab=orders&view=' . urlencode($orderId));
            exit;
        }

        $newStatus = 'Cancelled';
        $update = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $update->bind_param("ss", $newStatus, $orderId);
        $update->execute();

        $conn->commit();

        $sellerName = $_SESSION['name'] ?? ('Seller #' . $sellerId);
        logActivity($conn, $sellerId, $sellerName, 'seller', 'ORDER_CANCELLED', 'Seller cancelled order #' . $orderId);
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }
        header('Location: ../dashboard.php?tab=orders&view=' . urlencode($orderId) . '&fulfill_error=' . urlencode($e->getMessage()));
        exit;
    }
}

header('Location: ../dashboard.php?tab=orders&view=' . urlencode($orderId));
exit;
