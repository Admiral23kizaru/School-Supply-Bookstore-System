<?php
session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../activity_logger.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized request.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$orderId = trim((string) ($data['order_id'] ?? ''));
$customerId = (int) ($_SESSION['user_id'] ?? 0);
$sessionEmail = trim((string) ($_SESSION['email'] ?? ''));

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

if ($orderId === '' || $customerId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order.']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND customer_id = ? FOR UPDATE");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Order not found.');
    }
    if (!in_array($order['status'], ['Pending', 'Processing'], true)) {
        throw new Exception('Only Pending or Processing orders can be cancelled.');
    }

    $newStatus = 'Cancelled';
    $update = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND customer_id = ?");
    $update->bind_param("ssi", $newStatus, $orderId, $customerId);
    $update->execute();

    $conn->commit();

    $customerName = $_SESSION['name'] ?? ('Customer #' . $customerId);
    logActivity($conn, $customerId, $customerName, 'customer', 'ORDER_CANCELLED', 'Customer cancelled order #' . $orderId);

    echo json_encode(['success' => true, 'status' => $newStatus]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
