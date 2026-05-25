<?php
require 'db/db.php';
try {
    $conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS is_super_admin TINYINT(1) DEFAULT 0");
    $conn->query("UPDATE admins SET is_super_admin = 1 ORDER BY id ASC LIMIT 1");
    echo "Migration complete.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
