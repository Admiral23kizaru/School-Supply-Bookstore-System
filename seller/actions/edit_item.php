<?php
session_start();
require_once '../../config/db.php';
require_once '../includes/helpers.php';
require_once '../../activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../../index.php?action=login');
    exit;
}

$sellerId = (int) ($_SESSION['user_id'] ?? 0);
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = rtrim(dirname($scriptName, 3), '/');

$id = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$category = trim($_POST['category'] ?? '');
$price = (float) ($_POST['price'] ?? 0);
$stock = (int) ($_POST['stock'] ?? 0);
$unit = trim($_POST['unit'] ?? 'pc');
$status = productStatusFromStock($stock);
$newImageUrl = '';

if (isset($_FILES['image']) && is_array($_FILES['image'])) {
    $uploaded = handleProductImageUpload($_FILES['image'], $appBaseUrl);
    if ($uploaded) {
        $newImageUrl = $uploaded;
    }
}

if ($id > 0 && $name !== '' && $category !== '' && $price > 0) {
    $desc = 'Unit: ' . $unit;
    if ($newImageUrl !== '') {
        $stmt = $conn->prepare(
            "UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ?, status = ?, image_url = ? WHERE id = ? AND (seller_id = ? OR seller_id IS NULL)"
        );
        if ($stmt) {
            $stmt->bind_param("sssdissii", $name, $category, $desc, $price, $stock, $status, $newImageUrl, $id, $sellerId);
            $stmt->execute();
        }
    } else {
        $stmt = $conn->prepare(
            "UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ?, status = ? WHERE id = ? AND (seller_id = ? OR seller_id IS NULL)"
        );
        if ($stmt) {
            $stmt->bind_param("sssdisii", $name, $category, $desc, $price, $stock, $status, $id, $sellerId);
            $stmt->execute();
        }
    }
    $sellerName = $_SESSION['name'] ?? ('Seller #' . $sellerId);
    logActivity($conn, $sellerId, $sellerName, 'seller', 'PRODUCT_UPDATED',
        'Updated product: ' . $name . ' (ID ' . $id . ') — Price: ₱' . number_format($price, 2) . ', Stock: ' . $stock);
}

header('Location: ../dashboard.php?tab=inventory');
exit;
