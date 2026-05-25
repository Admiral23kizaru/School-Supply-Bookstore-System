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

$name = trim($_POST['name'] ?? '');
$category = trim($_POST['category'] ?? '');
$price = (float) ($_POST['price'] ?? 0);
$stock = (int) ($_POST['stock'] ?? 0);
$unit = trim($_POST['unit'] ?? 'pc');
$status = productStatusFromStock($stock);
$imageUrl = '';

if (isset($_FILES['image']) && is_array($_FILES['image'])) {
    $uploaded = handleProductImageUpload($_FILES['image'], $appBaseUrl);
    if ($uploaded) {
        $imageUrl = $uploaded;
    }
}

if ($name !== '' && $category !== '' && $price > 0) {
    $desc = 'Unit: ' . $unit;
    $existingId = 0;
    $check = $conn->prepare("SELECT id FROM products WHERE seller_id = ? AND LOWER(name) = LOWER(?) AND LOWER(category) = LOWER(?) LIMIT 1");
    if ($check) {
        $check->bind_param("iss", $sellerId, $name, $category);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $existingId = (int) ($existing['id'] ?? 0);
    }

    if ($existingId > 0) {
        $newStock = 0;
        $stockStmt = $conn->prepare("SELECT stock FROM products WHERE id = ? AND seller_id = ? LIMIT 1");
        if ($stockStmt) {
            $stockStmt->bind_param("ii", $existingId, $sellerId);
            $stockStmt->execute();
            $row = $stockStmt->get_result()->fetch_assoc();
            $newStock = (int) ($row['stock'] ?? 0) + $stock;
        }
        $status = productStatusFromStock($newStock);

        if ($imageUrl !== '') {
            $stmt = $conn->prepare("UPDATE products SET description = ?, price = ?, stock = ?, status = ?, image_url = ? WHERE id = ? AND seller_id = ?");
            if ($stmt) {
                $stmt->bind_param("sdissii", $desc, $price, $newStock, $status, $imageUrl, $existingId, $sellerId);
                $stmt->execute();
            }
        } else {
            $stmt = $conn->prepare("UPDATE products SET description = ?, price = ?, stock = ?, status = ? WHERE id = ? AND seller_id = ?");
            if ($stmt) {
                $stmt->bind_param("sdisii", $desc, $price, $newStock, $status, $existingId, $sellerId);
                $stmt->execute();
            }
        }
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO products (seller_id, name, category, description, price, stock, status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param("isssdiss", $sellerId, $name, $category, $desc, $price, $stock, $status, $imageUrl);
            $stmt->execute();
        }
    }

    if (isset($stmt) && $stmt) {
        $sellerName = $_SESSION['name'] ?? ('Seller #' . $sellerId);
        logActivity($conn, $sellerId, $sellerName, 'seller', 'PRODUCT_ADDED',
            'Saved product: ' . $name . ' - PHP ' . number_format($price, 2) . ', Added stock: ' . $stock);
    }
}

header('Location: ../dashboard.php?tab=inventory');
exit;
