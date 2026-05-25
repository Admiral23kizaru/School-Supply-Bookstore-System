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
$redirect = (string) ($_POST['redirect'] ?? '../dashboard.php?tab=users');

$isSuperAdmin = !empty($_SESSION['is_super_admin']);

if ($targetType === 'customer') {
    $table = 'customers';
} elseif ($targetType === 'seller') {
    $table = 'sellers';
}

if ($table === null || $targetId <= 0) {
    header('Location: ../dashboard.php?tab=users&error=delete_failed');
    exit;
}

$name = ucfirst($targetType) . ' #' . $targetId;
$row = $conn->query("SELECT name FROM `$table` WHERE id = $targetId LIMIT 1");
if ($row && ($r = $row->fetch_assoc())) {
    $name = (string) ($r['name'] ?? $name);
}

try {
    $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $targetId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            logActivity($conn, $adminId, $adminName, 'admin', 'USER_DELETED',
                'Deleted ' . ucfirst($targetType) . ' "' . $name . '" (ID ' . $targetId . ')');
            if (strpos($redirect, 'dashboard.php') === false) {
                $redirect = '../dashboard.php?tab=users';
            }
            header('Location: ' . $redirect . (str_contains($redirect, '?') ? '&' : '?') . 'success=user_deleted');
            exit;
        }
    }
} catch (Throwable $e) {
    // likely FK restriction (e.g. customer has orders)
}

header('Location: ../dashboard.php?tab=users&error=delete_failed');
exit;
