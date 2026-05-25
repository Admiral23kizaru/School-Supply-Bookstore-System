<?php
session_start();
require_once '../../config/db.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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

$newImageUrl = null;
if (isset($_FILES['image']) && is_array($_FILES['image'])) {
    $uploaded = handleProfileImageUpload($_FILES['image'], $appBaseUrl);
    if ($uploaded) {
        $newImageUrl = $uploaded;
    }
}

try {
    if ($newImageUrl) {
        $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, profile_image_url = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $name, $email, $newImageUrl, $adminId);
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
