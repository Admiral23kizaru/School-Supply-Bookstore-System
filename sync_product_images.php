<?php
require_once __DIR__ . '/db/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$uploadDir = __DIR__ . '/assets/uploads/products';
$publicPrefix = '../assets/uploads/products/';
$allowedExtensions = ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'gif', 'bmp', 'avif', 'svg', 'ico'];

function image_key(string $value): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value));
}

function is_renderable_image_file(string $path, array $allowedExtensions): bool
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return is_file($path) && in_array($extension, $allowedExtensions, true);
}

if (!is_dir($uploadDir)) {
    throw new RuntimeException('Missing upload directory: ' . $uploadDir);
}

$images = [];
foreach (scandir($uploadDir) ?: [] as $filename) {
    $path = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!is_renderable_image_file($path, $allowedExtensions)) {
        continue;
    }

    $nameKey = image_key(pathinfo($filename, PATHINFO_FILENAME));
    $images[$nameKey] = $filename;
}

$manualMap = [
    'yellow pad (80 leaves)' => 'Pad Paper (Long).jpg',
    'intermediate pad' => 'Pad Paper (Short).webp',
    'ballpoint pen (12pc)' => 'Gel Pen Black (5pc).jpg',
    'ballpoint pen blue (12pc)' => 'Gel Pen Assorted (10pc).jpg',
    'scotch tape (1 in)' => 'Masking Tape (1 in).jpg',
    'index cards (100pc)' => 'Graphing Paper (50 sheets).jpg',
    'notebook (80 leaves)' => 'Composition Notebook.jpg',
    'notebook (40 leaves)' => 'Composition Notebook.jpg',
    'spiral notebook (80 leaves)' => 'Composition Notebook.jpg',
    'staple wire #35 (1000pc)' => 'Stapler (Standard).jpg',
    'paper clips (100pc)' => 'binder.jpg',
    'metal paper clips' => 'binder.jpg',
    'scissors (7 inch)' => 'shopping.webp',
    'ruler (30cm plastic)' => 'graphing paper.jpg',
    'ruler (metal, 30cm)' => 'graphing paper.jpg',
    'pencil case (zipper)' => 'shopping.webp',
    'zipper pencil case' => 'shopping.webp',
    'folder (long, plastic)' => 'Envelope (Long, 10pc).jpg',
    'eraser (white vinyl)' => 'Pencil Set (12pc HB).jpg',
    'faber castell pencil' => '38000070a-Faber-Castell-Pencils-6s.webp',
    'faber-castell pencil' => '38000070a-Faber-Castell-Pencils-6s.webp',
    'correction tape' => 'coorection atpe.jpg',
    'binder clips (12pc, large)' => 'binder.jpg',
    'assorted binder clips' => 'binder.jpg',
    'calculator (12-digit)' => 'calculator.webp',
    'scientific calculator' => 'calculator.webp',
    'tracing paper (20 sheets)' => 'tracing paper.webp',
    'plastic pencil sharpener' => 'Mechanical Pencil 0.5mm.webp',
    'pink eraser pack' => 'Pencil Set (12pc HB).jpg',
    'large glue stick' => 'Glue Stick (Large).jpg',
    'school scissors' => 'shopping.webp',
    'ruler set (metric/imperial)' => 'graphing paper.jpg',
    'desktop stapler' => 'Stapler (Standard).jpg',
    'sticky notes pad' => 'Composition Notebook.jpg',
    'manila folder pack' => 'Envelope (Long, 10pc).jpg',
    'colored pencils (12 colors)' => '38000070a-Faber-Castell-Pencils-6s.webp',
    'crayons (24 colors)' => 'Construction Paper (10 colors).jpg',
    'watercolor paint set' => 'Construction Paper (10 colors).jpg',
];

$products = $conn->query("SELECT id, name, category FROM products ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$updateStmt = $conn->prepare("UPDATE products SET image_url = ? WHERE id = ?");

$updated = 0;
$missing = [];

foreach ($products as $product) {
    $productId = (int) $product['id'];
    $name = (string) $product['name'];
    $nameKey = image_key($name);
    $baseNameKey = image_key(preg_replace('/\s*\([^)]*\)/', '', $name));
    $filename = null;

    if (isset($manualMap[strtolower($name)]) && is_renderable_image_file($uploadDir . DIRECTORY_SEPARATOR . $manualMap[strtolower($name)], $allowedExtensions)) {
        $filename = $manualMap[strtolower($name)];
    } elseif (isset($images[$nameKey])) {
        $filename = $images[$nameKey];
    } elseif ($baseNameKey !== '' && isset($images[$baseNameKey])) {
        $filename = $images[$baseNameKey];
    } else {
        foreach ($images as $key => $candidate) {
            if ($key !== '' && ($key === $nameKey || strpos($key, $nameKey) !== false || strpos($nameKey, $key) !== false)) {
                $filename = $candidate;
                break;
            }
        }
    }

    if ($filename === null) {
        $missing[] = $name;
        continue;
    }

    $imageUrl = $publicPrefix . rawurlencode($filename);
    $updateStmt->bind_param("si", $imageUrl, $productId);
    $updateStmt->execute();
    $updated++;
}

echo "Updated product images: {$updated}\n";
if ($missing) {
    echo "Products still needing manual images: " . count($missing) . "\n";
    foreach ($missing as $name) {
        echo "- {$name}\n";
    }
}
