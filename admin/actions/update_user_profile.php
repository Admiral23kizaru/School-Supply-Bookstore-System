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
$targetRole = strtolower(trim((string) ($_POST['target_role'] ?? $targetType)));
$targetId = (int) ($_POST['target_id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$newPassword = (string) ($_POST['new_password'] ?? '');
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

if (!in_array($targetRole, ['customer', 'seller'], true)) {
    header('Location: ../dashboard.php?tab=users&error=invalid_role');
    exit;
}

function tableColumns(mysqli $conn, string $table): array
{
    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($res && ($row = $res->fetch_assoc())) {
        $columns[] = (string) ($row['Field'] ?? '');
    }
    return $columns;
}

if ($newPassword !== '' && strlen($newPassword) < 8) {
    header('Location: ../dashboard.php?tab=users&error=invalid_password');
    exit;
}

if ($targetRole === $targetType) {
    $dup = $conn->prepare("SELECT id FROM `$table` WHERE email = ? AND id != ? LIMIT 1");
    if ($dup) {
        $dup->bind_param("si", $email, $targetId);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            header('Location: ../dashboard.php?tab=users&error=email_taken');
            exit;
        }
    }

    if ($newPassword !== '') {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE `$table` SET name = ?, email = ?, password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $name, $email, $hashedPassword, $targetId);
            $stmt->execute();
            logActivity($conn, $adminId, $adminName, 'admin', 'USER_PASSWORD_RESET',
                'Updated ' . ucfirst($targetType) . ' account and reset password (ID ' . $targetId . ')');
        }
    } else {
        $stmt = $conn->prepare("UPDATE `$table` SET name = ?, email = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $name, $email, $targetId);
            $stmt->execute();
            logActivity($conn, $adminId, $adminName, 'admin', 'USER_UPDATED',
                'Updated ' . ucfirst($targetType) . ' account (ID ' . $targetId . ')');
        }
    }
} else {
    $sourceTable = $table;
    $destinationTable = $targetRole === 'customer' ? 'customers' : 'sellers';

    $sourceColumns = tableColumns($conn, $sourceTable);
    $destinationColumns = tableColumns($conn, $destinationTable);

    $sourceStmt = $conn->prepare("SELECT * FROM `$sourceTable` WHERE id = ? LIMIT 1");
    if (!$sourceStmt) {
        header('Location: ../dashboard.php?tab=users&error=save_failed');
        exit;
    }
    $sourceStmt->bind_param("i", $targetId);
    $sourceStmt->execute();
    $sourceUser = $sourceStmt->get_result()->fetch_assoc();
    if (!$sourceUser) {
        header('Location: ../dashboard.php?tab=users&error=invalid_edit');
        exit;
    }

    if ($sourceTable === 'customers') {
        $check = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE customer_id = ?");
    } else {
        $check = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE seller_id = ?");
    }
    if ($check) {
        $check->bind_param("i", $targetId);
        $check->execute();
        $linkedCount = (int) ($check->get_result()->fetch_assoc()['c'] ?? 0);
        if ($linkedCount > 0) {
            header('Location: ../dashboard.php?tab=users&error=role_change_blocked');
            exit;
        }
    }

    $dup = $conn->prepare("SELECT id FROM `$destinationTable` WHERE email = ? LIMIT 1");
    if ($dup) {
        $dup->bind_param("s", $email);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            header('Location: ../dashboard.php?tab=users&error=email_taken');
            exit;
        }
    }

    $copyCandidates = ['name', 'email', 'password', 'address', 'account_status', 'approval_status', 'created_at', 'profile_image_url'];
    $insertColumns = [];
    $insertValues = [];
    foreach ($copyCandidates as $column) {
        if (in_array($column, $sourceColumns, true) && in_array($column, $destinationColumns, true)) {
            $insertColumns[] = $column;
            $insertValues[] = $sourceUser[$column] ?? null;
        }
    }

    foreach ($insertColumns as $idx => $column) {
        if ($column === 'name') {
            $insertValues[$idx] = $name;
        } elseif ($column === 'email') {
            $insertValues[$idx] = $email;
        } elseif ($column === 'password' && $newPassword !== '') {
            $insertValues[$idx] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }

    if (count($insertColumns) === 0) {
        header('Location: ../dashboard.php?tab=users&error=save_failed');
        exit;
    }

    $conn->begin_transaction();
    try {
        $columnsSql = '`' . implode('`, `', $insertColumns) . '`';
        $escapedValues = [];
        foreach ($insertValues as $value) {
            if ($value === null) {
                $escapedValues[] = 'NULL';
            } else {
                $escapedValues[] = "'" . $conn->real_escape_string((string) $value) . "'";
            }
        }
        $valuesSql = implode(', ', $escapedValues);
        $insertSql = "INSERT INTO `$destinationTable` ($columnsSql) VALUES ($valuesSql)";
        if (!$conn->query($insertSql)) {
            throw new RuntimeException('insert_failed');
        }

        $deleteStmt = $conn->prepare("DELETE FROM `$sourceTable` WHERE id = ?");
        if (!$deleteStmt) {
            throw new RuntimeException('delete_prepare_failed');
        }
        $deleteStmt->bind_param("i", $targetId);
        if (!$deleteStmt->execute()) {
            throw new RuntimeException('delete_failed');
        }

        $conn->commit();
        logActivity(
            $conn,
            $adminId,
            $adminName,
            'admin',
            'USER_ROLE_CHANGED',
            'Changed user role from ' . ucfirst($targetType) . ' to ' . ucfirst($targetRole) . ' (ID ' . $targetId . ')'
        );
    } catch (Throwable $e) {
        $conn->rollback();
        header('Location: ../dashboard.php?tab=users&error=save_failed');
        exit;
    }
}

if (strpos($redirect, 'dashboard.php') === false) {
    $redirect = '../dashboard.php?tab=users';
}

header('Location: ' . $redirect . (str_contains($redirect, '?') ? '&' : '?') . 'success=user_updated');
exit;
