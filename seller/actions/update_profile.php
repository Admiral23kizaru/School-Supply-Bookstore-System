<?php
session_start();
require_once '../../config/db.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../../index.php?action=login');
    exit;
}

$sellerId = (int) ($_SESSION['user_id'] ?? 0);
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = rtrim(dirname($scriptName, 3), '/');
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($sellerId > 0 && $name !== '' && $email !== '') {
    $newImageUrl = null;
    if (isset($_FILES['image']) && is_array($_FILES['image'])) {
        $uploaded = handleProfileImageUpload($_FILES['image'], $appBaseUrl);
        if ($uploaded) {
            $newImageUrl = $uploaded;
        }
    }

    try {
        if ($newImageUrl) {
            $stmt = $conn->prepare("UPDATE sellers SET name = ?, email = ?, profile_image_url = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sssi", $name, $email, $newImageUrl, $sellerId);
                $stmt->execute();
            }
        } else {
            $stmt = $conn->prepare("UPDATE sellers SET name = ?, email = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $name, $email, $sellerId);
                $stmt->execute();
            }
        }
        $_SESSION['email'] = $email;
    } catch (Throwable $e) {
        // Keep session values if update fails (e.g., duplicate email).
    }
}

header('Location: ../dashboard.php?tab=inventory');
exit;
