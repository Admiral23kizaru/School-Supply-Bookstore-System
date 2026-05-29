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

    // Password Change Logic
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmNewPassword = $_POST['confirm_new_password'] ?? '';
    $passwordToUpdate = null;

    if ($currentPassword !== '' || $newPassword !== '' || $confirmNewPassword !== '') {
        $stmt = $conn->prepare("SELECT password FROM sellers WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $sellerId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($user = $res->fetch_assoc()) {
                if (!password_verify($currentPassword, $user['password'])) {
                    header('Location: ../dashboard.php?tab=inventory&error=wrong_password');
                    exit;
                }
                if ($newPassword !== $confirmNewPassword) {
                    header('Location: ../dashboard.php?tab=inventory&error=password_mismatch');
                    exit;
                }
                if (strlen($newPassword) < 8) {
                    header('Location: ../dashboard.php?tab=inventory&error=weak_password');
                    exit;
                }
                $passwordToUpdate = password_hash($newPassword, PASSWORD_DEFAULT);
            }
        }
    }

    try {
        if ($newImageUrl && $passwordToUpdate) {
            $stmt = $conn->prepare("UPDATE sellers SET name = ?, email = ?, profile_image_url = ?, password = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssssi", $name, $email, $newImageUrl, $passwordToUpdate, $sellerId);
                $stmt->execute();
            }
        } elseif ($newImageUrl && !$passwordToUpdate) {
            $stmt = $conn->prepare("UPDATE sellers SET name = ?, email = ?, profile_image_url = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sssi", $name, $email, $newImageUrl, $sellerId);
                $stmt->execute();
            }
        } elseif (!$newImageUrl && $passwordToUpdate) {
            $stmt = $conn->prepare("UPDATE sellers SET name = ?, email = ?, password = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sssi", $name, $email, $passwordToUpdate, $sellerId);
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
