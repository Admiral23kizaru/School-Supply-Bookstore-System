<?php

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function productStatusFromStock(int $stock): string
{
    if ($stock <= 0) {
        return 'Out of Stock';
    }
    if ($stock <= 10) {
        return 'Low Stock';
    }
    return 'In Stock';
}

function ensureProfileImageColumn(mysqli $conn, string $table): void
{
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'profile_image_url'");
        if ($check && (int) $check->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` ADD profile_image_url varchar(255) DEFAULT NULL");
        }
    } catch (Throwable $e) {
        // Ignore to avoid breaking the portal.
    }
}

function handleProfileImageUpload(array $file, string $appBaseUrl): ?string
{
    if (empty($file['tmp_name']) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!$mime || !isset($allowed[$mime])) {
        return null;
    }

    $maxBytes = 2 * 1024 * 1024;
    if (!empty($file['size']) && (int) $file['size'] > $maxBytes) {
        return null;
    }

    $uploadDir = __DIR__ . '/../../assets/uploads/profiles';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_dir($uploadDir)) {
        return null;
    }

    $ext = $allowed[$mime];
    $filename = 'profile_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }

    return $appBaseUrl . '/assets/uploads/profiles/' . $filename;
}

function handleProductImageUpload(array $file, string $appBaseUrl): ?string
{
    if (empty($file['tmp_name']) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/avif' => 'avif',
        'image/svg+xml' => 'svg',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];
    $renderableExtensions = ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'gif', 'bmp', 'avif', 'svg', 'ico'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $originalExt = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    if (!$mime || strpos($mime, 'image/') !== 0 || !in_array($originalExt, $renderableExtensions, true)) {
        return null;
    }

    if ($mime === 'image/svg+xml') {
        $sample = file_get_contents($file['tmp_name'], false, null, 0, 2048);
        if ($sample === false || stripos($sample, '<svg') === false) {
            return null;
        }
    } elseif (!isset($mimeToExt[$mime]) && @getimagesize($file['tmp_name']) === false) {
        return null;
    }

    $uploadDir = __DIR__ . '/../../assets/uploads/products';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_dir($uploadDir)) {
        return null;
    }

    $ext = $mimeToExt[$mime] ?? $originalExt;
    if ($ext === 'jpeg' || $ext === 'jfif') {
        $ext = 'jpg';
    }
    $filename = 'product_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }

    return $appBaseUrl . '/assets/uploads/products/' . $filename;
}
