<?php
session_start();
require_once __DIR__ . '/../db/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer') {
    $data = json_decode(file_get_contents('php://input'), true);
    $cart = $data['cart'] ?? [];
    
    if (empty($cart)) {
        echo json_encode(['success' => false, 'error' => 'Cart is empty.']);
        exit;
    }

    $customer_id = $_SESSION['user_id'];
    
    // Generate a unique short ID like ORD-A1B2C3
    $order_id = 'ORD-' . substr(strtoupper(md5(uniqid(rand(), true))), 0, 6);
    
    // Calculate accurate total directly in PHP
    $total = 0;
    foreach ($cart as $item) {
        $total += ((float)$item['price'] * (int)$item['qty']);
    }

    $conn->begin_transaction();
    try {
        // Create Order Base
        $stmt = $conn->prepare("INSERT INTO orders (id, customer_id, total_amount, status) VALUES (?, ?, ?, 'Pending')");
        $stmt->bind_param("sid", $order_id, $customer_id, $total);
        $stmt->execute();
        
        // Prepare Item Insert
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        
        foreach ($cart as $item) {
            $pid = (int)$item['id'];
            $qty = (int)$item['qty'];
            $price = (float)$item['price'];
            
            $stmt_item->bind_param("siid", $order_id, $pid, $qty, $price);
            $stmt_item->execute();
            
            // Deduct from stock safely
            $conn->query("UPDATE products SET stock = IF(stock >= $qty, stock - $qty, 0) WHERE id = $pid");
            // If stock evaluates precisely to zero, switch its state
            $conn->query("UPDATE products SET status = 'Out of Stock' WHERE id = $pid AND stock <= 0");
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Database error. Checkout failed.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Unauthorized or invalid method']);
}
?>
