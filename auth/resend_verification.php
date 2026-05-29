<?php
session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../mailer_verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['email']) || !isset($_POST['role'])) {
    header('Location: ../index.php');
    exit;
}

$email = trim($_POST['email']);
$role = strtolower(trim($_POST['role']));
$table = '';

if ($role === 'seller') {
    $table = 'sellers';
} elseif ($role === 'customer') {
    $table = 'customers';
} else {
    header('Location: ../index.php');
    exit;
}

$stmt = $conn->prepare("SELECT id, is_verified FROM `$table` WHERE email = ?");
if ($stmt) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        if ((int)$user['is_verified'] !== 1) {
            $token = bin2hex(random_bytes(16));
            $update = $conn->prepare("UPDATE `$table` SET verification_token = ? WHERE id = ?");
            if ($update) {
                $update->bind_param("si", $token, $user['id']);
                $update->execute();
                
                sendVerificationMail($email, $token);
                header('Location: ../index.php?action=login&success=verification_resent');
                exit;
            }
        }
    }
}

// Fallback error
header('Location: ../index.php?action=login&error=invalid');
exit;
