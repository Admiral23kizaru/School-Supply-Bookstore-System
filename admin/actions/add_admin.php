<?php
session_start();
require_once '../../config/db.php';
require_once '../../activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?action=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php?tab=users');
    exit;
}

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['name'] ?? ('Admin #' . $adminId);
$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    header('Location: ../dashboard.php?tab=users&error=invalid_add');
    exit;
}

try {
    $check = $conn->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        header('Location: ../dashboard.php?tab=users&error=email_taken');
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (name, email, password, account_status, is_super_admin) VALUES (?, ?, ?, 'Active', 0)");
    $stmt->bind_param("sss", $username, $email, $hash);
    $stmt->execute();

    logActivity($conn, $adminId, $adminName, 'admin', 'ADMIN_CREATED', 'Admin created a new Admin account for ' . $username . ' (' . $email . ')');
    header('Location: ../dashboard.php?tab=users&success=admin_added');
    exit;
} catch (Throwable $e) {
    header('Location: ../dashboard.php?tab=users&error=add_failed');
    exit;
}
