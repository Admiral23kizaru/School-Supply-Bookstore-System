<?php
session_start();
require_once '../../config/db.php';
require_once '../../activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../../index.php?action=login');
    exit;
}

$sellerId = (int) ($_SESSION['user_id'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    // Grab name before deleting for the log
    $nameRow = $conn->query("SELECT name FROM products WHERE id = $id LIMIT 1");
    $productName = $nameRow ? ($nameRow->fetch_assoc()['name'] ?? "ID $id") : "ID $id";

    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND (seller_id = ? OR seller_id IS NULL)");
    if ($stmt) {
        $stmt->bind_param("ii", $id, $sellerId);
        $stmt->execute();
        $sellerName = $_SESSION['name'] ?? ('Seller #' . $sellerId);
        logActivity($conn, $sellerId, $sellerName, 'seller', 'PRODUCT_DELETED',
            'Deleted product: ' . $productName . ' (ID ' . $id . ')');
    }
}

header('Location: ../dashboard.php?tab=inventory');
exit;
