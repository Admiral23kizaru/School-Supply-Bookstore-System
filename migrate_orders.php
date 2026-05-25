<?php
require 'config/db.php';
try {
    $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'Cash'");
    echo "Migration complete.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
