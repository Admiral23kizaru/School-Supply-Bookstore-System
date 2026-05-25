<?php
session_start();
require_once '../../config/db.php';
require_once '../../activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?action=login');
    exit;
}

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['name'] ?? ('Admin #' . $adminId);
$targetType = (string) ($_POST['target_type'] ?? '');
$targetId = (int) ($_POST['target_id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$redirect = (string) ($_POST['redirect'] ?? '../dashboard.php?tab=users');

$table = null;
if ($targetType === 'customer') {
    $table = 'customers';
} elseif ($targetType === 'seller') {
    $table = 'sellers';
}

if ($table === null || $targetId <= 0 || $name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../dashboard.php?tab=users&error=invalid_edit');
    exit;
}

$dup = $conn->prepare("SELECT id FROM `$table` WHERE email = ? AND id != ? LIMIT 1");
if ($dup) {
    $dup->bind_param("si", $email, $targetId);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        header('Location: ../dashboard.php?tab=users&error=email_taken');
        exit;
    }
}

$stmt = $conn->prepare("UPDATE `$table` SET name = ?, email = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("ssi", $name, $email, $targetId);
    $stmt->execute();
    logActivity($conn, $adminId, $adminName, 'admin', 'USER_UPDATED',
        'Updated ' . ucfirst($targetType) . ' account (ID ' . $targetId . ')');
}

if (strpos($redirect, 'dashboard.php') === false) {
    $redirect = '../dashboard.php?tab=users';
}

header('Location: ' . $redirect . (str_contains($redirect, '?') ? '&' : '?') . 'success=user_updated');
exit;
