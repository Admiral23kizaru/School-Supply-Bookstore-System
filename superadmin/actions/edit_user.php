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

$targetId = (int) ($_POST['target_id'] ?? 0);
$targetType = (string) ($_POST['target_type'] ?? '');
$newName = trim((string) ($_POST['name'] ?? ''));
$newEmail = trim((string) ($_POST['email'] ?? ''));

if ($targetId <= 0 || empty($targetType) || empty($newName) || empty($newEmail)) {
    header('Location: ../dashboard.php?tab=users&error=missing_data');
    exit;
}

$table = null;
if ($targetType === 'customer') {
    $table = 'customers';
} elseif ($targetType === 'seller') {
    $table = 'sellers';
} elseif ($targetType === 'admin') {
    // Only superadmins or admins editing others (Super Admin can edit others)
    $table = 'admins';
}

if ($table === null) {
    header('Location: ../dashboard.php?tab=users&error=invalid_type');
    exit;
}

// 1. Check if email is already taken by others in the SAME table
$emailCheck = $conn->prepare("SELECT id FROM `$table` WHERE email = ? AND id != ? LIMIT 1");
$emailCheck->bind_param("si", $newEmail, $targetId);
$emailCheck->execute();
if ($emailCheck->get_result()->num_rows > 0) {
    header('Location: ../dashboard.php?tab=users&error=email_taken');
    exit;
}

$newPass = trim((string) ($_POST['new_password'] ?? ''));

// 2. Perform Update
if (!empty($newPass) && $targetType === 'admin') {
    if (strlen($newPass) < 8) {
        header('Location: ../dashboard.php?tab=users&error=invalid_password');
        exit;
    }
    $hashedPass = password_hash($newPass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE `$table` SET name = ?, email = ?, password = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sssi", $newName, $newEmail, $hashedPass, $targetId);
    }
} else {
    $stmt = $conn->prepare("UPDATE `$table` SET name = ?, email = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ssi", $newName, $newEmail, $targetId);
    }
}

if ($stmt && $stmt->execute()) {
    logActivity($conn, $adminId, $adminName, 'admin', 'USER_UPDATED', 
        'Updated ' . ucfirst($targetType) . ' details: ' . $newName . ' (' . $newEmail . ')' . (!empty($newPass) ? ' [Password Reset]' : ''));
    header('Location: ../dashboard.php?tab=users&success=user_updated');
    exit;
}

header('Location: ../dashboard.php?tab=users&error=update_failed');
exit;
