<?php

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function product_status(int $stock): string
{
    if ($stock <= 0) {
        return 'Out of Stock';
    }
    if ($stock <= 10) {
        return 'Low Stock';
    }
    return 'In Stock';
}

function ensureAccountStatusColumns(mysqli $conn): void
{
    foreach (['customers', 'sellers', 'admins'] as $table) {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'account_status'");
        if ($check && (int) $check->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` ADD account_status ENUM('Active','Suspended','Banned') NOT NULL DEFAULT 'Active'");
        }
    }
}

function ensureProfileImageColumn(mysqli $conn, string $table): void
{
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'profile_image_url'");
        if ($check && (int) $check->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` ADD profile_image_url varchar(255) DEFAULT NULL");
        }
    } catch (Throwable $e) {
        // Ignore to avoid breaking the panel.
    }
}

function ensureApprovalStatusColumn(mysqli $conn, string $table): void
{
    if (!in_array($table, ['customers', 'sellers'], true)) {
        return;
    }
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'approval_status'");
        if ($check && (int) $check->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` ADD approval_status VARCHAR(20) NOT NULL DEFAULT 'Approved'");
        }
    } catch (Throwable $e) {
        // Ignore to avoid breaking the panel.
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
