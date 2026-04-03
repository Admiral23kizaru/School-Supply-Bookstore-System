<?php
session_start();
require_once __DIR__ . '/../db/db.php';

header('Content-Type: application/json');

// Make mysqli throw exceptions so we can return the real error.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => 'Preflight']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
$data = json_decode(file_get_contents('php://input'), true);
    $cart = $data['cart'] ?? [];
    
    if (empty($cart)) {
        echo json_encode(['success' => false, 'error' => 'Cart is empty.']);
        exit;
    }

    $customer_id = (int) $_SESSION['user_id'];
    $originalCustomerId = $customer_id;
    $sessionEmail = $_SESSION['email'] ?? '';

    // Verify that the session user_id actually exists in `customers`.
    // If not, fall back to resolving by session email (prevents FK failures).
    try {
        $isValidCustomer = false;
        if ($customer_id > 0) {
            $check = $conn->prepare("SELECT id FROM customers WHERE id = ? LIMIT 1");
            if ($check) {
                $check->bind_param("i", $customer_id);
                $check->execute();
                $res = $check->get_result();
                $isValidCustomer = (bool) $res->fetch_assoc();
            }
        }

        if (!$isValidCustomer && $sessionEmail !== '') {
            $checkByEmail = $conn->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
            if ($checkByEmail) {
                $checkByEmail->bind_param("s", $sessionEmail);
                $checkByEmail->execute();
                $res2 = $checkByEmail->get_result();
                $row2 = $res2->fetch_assoc();
                if ($row2 && isset($row2['id'])) {
                    $customer_id = (int) $row2['id'];
                    $isValidCustomer = $customer_id > 0;
                }
            }
        }

        if (!$isValidCustomer) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid customer session (customer not found).',
                'debug' => [
                    'customer_id_from_session' => (int) ($_SESSION['user_id'] ?? 0),
                    'session_email' => $sessionEmail,
                ],
            ]);
            exit;
        }

        // Keep session in sync so "My Orders" loads correctly after checkout.
        if ($customer_id > 0 && $customer_id !== $originalCustomerId) {
            $_SESSION['user_id'] = $customer_id;
        }
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Checkout failed while validating customer.',
            'debug' => [
                'message' => $e->getMessage(),
            ],
        ]);
        exit;
    }
    
    // Generate a unique short ID like ORD-A1B2C3
    $order_id = 'ORD-' . substr(strtoupper(md5(uniqid(rand(), true))), 0, 6);
    
    // Calculate accurate total directly in PHP
    $total = 0;
    foreach ($cart as $item) {
        $total += ((float)$item['price'] * (int)$item['qty']);
    }

    $conn->begin_transaction();
    $stage = 'unknown';
    try {
        // Create Order Base
        $stage = 'create_order';
        $stmt = $conn->prepare("INSERT INTO orders (id, customer_id, total_amount, status) VALUES (?, ?, ?, 'Pending')");
        if (!$stmt) {
            throw new Exception('Failed to prepare orders insert: ' . $conn->error);
        }
        $stmt->bind_param("sid", $order_id, $customer_id, $total);
        $stmt->execute();
        
        // Prepare Item Insert
        $stage = 'prepare_item_insert';
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        if (!$stmt_item) {
            throw new Exception('Failed to prepare order_items insert: ' . $conn->error);
        }
        
        foreach ($cart as $item) {
            $stage = 'insert_item';
            $pid = (int)$item['id'];
            $qty = (int)$item['qty'];
            $price = (float)$item['price'];
            
            $stmt_item->bind_param("siid", $order_id, $pid, $qty, $price);
            $stmt_item->execute();
            
            // Deduct from stock safely
            $stage = 'deduct_stock';
            $conn->query("UPDATE products SET stock = IF(stock >= $qty, stock - $qty, 0) WHERE id = $pid");
            // If stock evaluates precisely to zero, switch its state
            $stage = 'update_out_of_stock_status';
            $conn->query("UPDATE products SET status = 'Out of Stock' WHERE id = $pid AND stock <= 0");
        }
        
        $stage = 'commit';
        $conn->commit();
        echo json_encode(['success' => true]);
        
    } catch (Throwable $e) {
        $conn->rollback();
        // Return real error message for debugging.
        error_log('Checkout failed stage=' . $stage . ' message=' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Database error. Checkout failed.',
            'debug' => [
                'stage' => $stage,
                'message' => $e->getMessage(),
            ],
        ]);
    }
} else {
    $role = $_SESSION['role'] ?? null;
    $uid = $_SESSION['user_id'] ?? null;
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: login session missing or invalid.',
        'debug' => [
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'session_role' => $role,
            'session_user_id_set' => $uid ? true : false,
        ],
    ]);
}
?>
