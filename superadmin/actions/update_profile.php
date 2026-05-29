<?php
session_start();
require_once '../../config/db.php';
require_once '../../admin/includes/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || empty($_SESSION['is_super_admin'])) {
    header('Location: ../../index.php?action=login');
    exit;
}

ensureProfileImageColumn($conn, 'admins');

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = rtrim(dirname($scriptName, 3), '/');

if ($adminId <= 0 || $name === '') {
    header('Location: ../dashboard.php?tab=profile&error=invalid');
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../dashboard.php?tab=profile&error=invalid_email');
    exit;
}

$dup = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1");
if ($dup) {
    $dup->bind_param("si", $email, $adminId);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        header('Location: ../dashboard.php?tab=profile&error=email_taken');
        exit;
    }
}

// Password Change Logic
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmNewPassword = $_POST['confirm_new_password'] ?? '';
$passwordToUpdate = null;

if ($currentPassword !== '' || $newPassword !== '' || $confirmNewPassword !== '') {
    $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            if (!password_verify($currentPassword, $user['password'])) {
                header('Location: ../dashboard.php?tab=profile&error=wrong_password');
                exit;
            }
            if ($newPassword !== $confirmNewPassword) {
                header('Location: ../dashboard.php?tab=profile&error=password_mismatch');
                exit;
            }
            if (strlen($newPassword) < 8) {
                header('Location: ../dashboard.php?tab=profile&error=weak_password');
                exit;
            }
            $passwordToUpdate = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }
}

$newImageUrl = null;
if (isset($_FILES['image']) && is_array($_FILES['image'])) {
    $uploaded = handleProfileImageUpload($_FILES['image'], $appBaseUrl);
    if ($uploaded) {
        $newImageUrl = $uploaded;
    }
}

try {
    if ($newImageUrl && $passwordToUpdate) {
        $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, profile_image_url = ?, password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssssi", $name, $email, $newImageUrl, $passwordToUpdate, $adminId);
            $stmt->execute();
        }
    } elseif ($newImageUrl && !$passwordToUpdate) {
        $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, profile_image_url = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $name, $email, $newImageUrl, $adminId);
            $stmt->execute();
        }
    } elseif (!$newImageUrl && $passwordToUpdate) {
        $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $name, $email, $passwordToUpdate, $adminId);
            $stmt->execute();
        }
    } else {
        $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $name, $email, $adminId);
            $stmt->execute();
        }
    }
    $_SESSION['email'] = $email;
} catch (Throwable $e) {
    header('Location: ../dashboard.php?tab=profile&error=save_failed');
    exit;
}

header('Location: ../dashboard.php?tab=profile&saved=1');
exit;
