<?php
require 'db/db.php';
$res = $conn->query("SELECT email FROM admins WHERE is_super_admin = 1 LIMIT 1");
if ($row = $res->fetch_assoc()) {
    echo "Super Admin Email: " . $row['email'];
} else {
    echo "No Super Admin found.";
}
