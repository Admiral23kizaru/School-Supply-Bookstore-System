<?php
session_start();
require_once '../../config/db.php';
require_once '../../activity_logger.php';

if (empty($_SESSION['is_super_admin'])) {
    header('Location: ../../index.php?action=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = (int) ($_SESSION['user_id'] ?? 0);
    $adminNameUser = $_SESSION['name'] ?? 'Super Admin';
    
    $username = trim($_POST['username'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || strlen($password) < 8) {
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
        $stmt = $conn->prepare("INSERT INTO admins (name, first_name, last_name, email, password, account_status, is_super_admin) VALUES (?, ?, ?, ?, ?, 'Active', 0)");
        $stmt->bind_param("sssss", $username, $firstName, $lastName, $email, $hash);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            logActivity($conn, $adminId, $adminNameUser, 'admin', 'ADMIN_CREATED', "Super Admin created a new Admin account for $username ($email)");
            header('Location: ../dashboard.php?tab=users&success=admin_added');
            exit;
        }
    } catch (Throwable $e) {}

    header('Location: ../dashboard.php?tab=users&error=add_failed');
    exit;
}

header('Location: ../dashboard.php?tab=users');
exit;
