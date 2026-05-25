<?php
require_once __DIR__ . '/db/db.php';

$baseDir = realpath(__DIR__);
$missing = [];
$result = $conn->query("SELECT id, name, image_url FROM products ORDER BY id ASC");

while ($result && ($row = $result->fetch_assoc())) {
    $imageUrl = trim((string) ($row['image_url'] ?? ''));
    if ($imageUrl === '') {
        $missing[] = $row['id'] . ' | ' . $row['name'] . ' | EMPTY';
        continue;
    }

    $path = parse_url($imageUrl, PHP_URL_PATH) ?: $imageUrl;
    $path = urldecode($path);
    $assetPos = strpos($path, '/assets/');
    if ($assetPos !== false) {
        $path = substr($path, $assetPos + 1);
    } else {
        $path = ltrim(str_replace('../', '', $path), '/\\');
    }

    $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    if (!is_file($fullPath)) {
        $missing[] = $row['id'] . ' | ' . $row['name'] . ' | ' . $imageUrl;
    }
}

if ($missing) {
    echo "Missing product image files: " . count($missing) . PHP_EOL;
    echo implode(PHP_EOL, $missing) . PHP_EOL;
    exit(1);
}

echo "All product image URLs point to existing local files." . PHP_EOL;
