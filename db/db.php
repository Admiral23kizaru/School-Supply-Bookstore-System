<?php
// ============================================================
// DATABASE CONNECTION — Edit ONLY this file for hosting
// For InfinityFree:
//   DB_HOST → e.g. sql123.infinityfree.com
//   DB_USER → your InfinityFree DB username (e.g. if0_12345678)
//   DB_PASS → your InfinityFree DB password
//   DB_NAME → your InfinityFree DB name   (e.g. if0_12345678_school_supply_db)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_supply_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(503);
    die(json_encode(['error' => 'Database connection failed.']));
}

$conn->set_charset('utf8mb4');
?>
