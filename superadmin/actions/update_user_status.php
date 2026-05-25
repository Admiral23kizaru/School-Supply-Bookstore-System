<?php
session_start();
require_once '../../config/db.php';
require_once '../../activity_logger.php';
require_once '../../mailer_approval.php';
require_once '../../admin/includes/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?action=login');
    exit;
}

$adminId    = (int) ($_SESSION['user_id'] ?? 0);
$adminName  = $_SESSION['name'] ?? ('Admin #' . $adminId);
$targetType = (string) ($_POST['target_type'] ?? '');
$targetId   = (int) ($_POST['target_id'] ?? 0);
$adminAction = (string) ($_POST['admin_action'] ?? '');
$redirect   = (string) ($_POST['redirect'] ?? '../dashboard.php?tab=users');

$status = null;
if ($adminAction === 'ban_user')      { $status = 'Banned'; }
elseif ($adminAction === 'unban_user')   { $status = 'Active'; }
elseif ($adminAction === 'suspend_user') { $status = 'Suspended'; }
elseif ($adminAction === 'activate_user') { $status = 'Active'; }

$table = null;
if ($targetType === 'customer')      { $table = 'customers'; }
elseif ($targetType === 'seller')    { $table = 'sellers'; }
elseif ($targetType === 'admin')     {
    if ($targetId === $adminId) {
        header('Location: ../dashboard.php?tab=users&error=invalid_edit');
        exit;
    }
    $table = 'admins';
}

if ($table !== null) {
    ensureApprovalStatusColumn($conn, $table);
}

if ($adminAction === 'approve_user' && $table !== null && $targetId > 0) {
    $email = '';
    $nameRow = $conn->query("SELECT name, email FROM `$table` WHERE id = $targetId LIMIT 1");
    $targetName = "$targetType #$targetId";
    if ($nameRow) {
        $row = $nameRow->fetch_assoc();
        if ($row) {
            $targetName = $row['name'] ?? $targetName;
            $email = (string) ($row['email'] ?? '');
        }
    }
    $stmt = $conn->prepare("UPDATE `$table` SET approval_status = 'Approved', account_status = 'Active' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $targetId);
        $stmt->execute();
        logActivity($conn, $adminId, $adminName, 'admin', 'USER_APPROVED',
            'Approved ' . ucfirst($targetType) . ' "' . $targetName . '" (ID ' . $targetId . ')');

        if ($email !== '') {
            $mailSent = sendApprovalMail($email, ucfirst($targetType));
            if ($mailSent) {
                logActivity($conn, $adminId, $adminName, 'admin', 'APPROVAL_EMAIL_SENT',
                    'Approval email sent to ' . ucfirst($targetType) . ' "' . $targetName . '"');
            } else {
                logActivity($conn, $adminId, $adminName, 'admin', 'APPROVAL_EMAIL_FAILED',
                    'Approval email failed for ' . ucfirst($targetType) . ' "' . $targetName . '"', 'Failed');
            }
        }
    }
}

if ($status !== null && $table !== null && $targetId > 0) {
    // Grab target name for log
    $nameRow = $conn->query("SELECT name FROM `$table` WHERE id = $targetId LIMIT 1");
    $targetName = $nameRow ? ($nameRow->fetch_assoc()['name'] ?? "$targetType #$targetId") : "$targetType #$targetId";

    $stmt = $conn->prepare("UPDATE `$table` SET account_status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $status, $targetId);
        $stmt->execute();
        logActivity($conn, $adminId, $adminName, 'admin', 'USER_STATUS_CHANGED',
            'Set ' . ucfirst($targetType) . ' "' . $targetName . '" (ID ' . $targetId . ') to ' . $status);
    }
}

if (strpos($redirect, 'dashboard.php') === false) {
    $redirect = '../dashboard.php?tab=users';
}

header('Location: ' . $redirect);
exit;
