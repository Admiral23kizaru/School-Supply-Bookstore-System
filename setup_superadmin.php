<?php
require 'db/db.php';
require 'admin/includes/helpers.php';

echo "Starting Super Admin Setup...\n";

// 1. Ensure Columns exist (fixes the Fatal Error)
try {
    ensureAccountStatusColumns($conn);
    
    // Also ensure is_super_admin column exists
    $check = $conn->query("SHOW COLUMNS FROM admins LIKE 'is_super_admin'");
    if ($check && (int) $check->num_rows === 0) {
        $conn->query("ALTER TABLE admins ADD is_super_admin TINYINT(1) DEFAULT 0");
    }
    echo "Database columns verified.\n";
} catch (Exception $e) {
    die("Error verifying columns: " . $e->getMessage());
}

// 2. Clear existing super admin flags to avoid conflicts as requested
try {
    $conn->query("UPDATE admins SET is_super_admin = 0");
    echo "Existing super admin flags reset.\n";
} catch (Exception $e) {
    die("Error resetting super admins: " . $e->getMessage());
}

// 3. Setup the new Super Admin
$newEmail = 'superadmin@gmail.com';
$newPass = 'admin123';
$hashedPass = password_hash($newPass, PASSWORD_DEFAULT);

try {
    // Check if the email already exists
    $check = $conn->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
    $check->bind_param("s", $newEmail);
    $check->execute();
    $res = $check->get_result();
    
    if ($row = $res->fetch_assoc()) {
        // Update existing account
        $id = $row['id'];
        $stmt = $conn->prepare("UPDATE admins SET password = ?, is_super_admin = 1, account_status = 'Active' WHERE id = ?");
        $stmt->bind_param("si", $hashedPass, $id);
        $stmt->execute();
        echo "Updated existing account $newEmail to Super Admin status.\n";
    } else {
        // Create new account
        $stmt = $conn->prepare("INSERT INTO admins (name, email, password, is_super_admin, account_status) VALUES ('Super Admin', ?, ?, 1, 'Active')");
        $stmt->bind_param("ss", $newEmail, $hashedPass);
        $stmt->execute();
        echo "Created new Super Admin account: $newEmail.\n";
    }
} catch (Exception $e) {
    die("Error setting up super admin account: " . $e->getMessage());
}

echo "Setup complete. You can now log in with:\n";
echo "Email: $newEmail\n";
echo "Password: $newPass\n";
?>
