<?php
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/seller/includes/helpers.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$supplierEmail = 'aaronryo.gemang@nmsc.edu.ph';

$products = [
    ['Crayons (24 Colors)', 'ART', 'Unit: box', 85.00, 60, 'https://commons.wikimedia.org/wiki/File:Crayola_Gold_Medal_No_8_School_Crayons_(7297477734).jpg'],
    ['Colored Pencils (12 Colors)', 'ART', 'Unit: box', 95.00, 55, 'https://commons.wikimedia.org/wiki/Category:Colored_pencils'],
    ['Watercolor Paint Set', 'ART', 'Unit: set', 120.00, 40, 'https://commons.wikimedia.org/wiki/Category:Watercolor_paint'],
    ['Plastic Pencil Sharpener', 'WRITING', 'Unit: pc', 15.00, 100, 'https://commons.wikimedia.org/wiki/Category:Pencil_sharpeners'],
    ['Pink Eraser Pack', 'WRITING', 'Unit: pack', 25.00, 90, 'https://commons.wikimedia.org/wiki/Category:Erasers'],
    ['Large Glue Stick', 'SUPPLY', 'Unit: pc', 28.00, 85, 'https://commons.wikimedia.org/wiki/Category:Glue_sticks'],
    ['School Scissors', 'SUPPLY', 'Unit: pc', 55.00, 45, 'https://commons.wikimedia.org/wiki/Category:Scissors'],
    ['Ruler Set (Metric/Imperial)', 'SUPPLY', 'Unit: set', 35.00, 75, 'https://commons.wikimedia.org/wiki/Category:Rulers'],
    ['Desktop Stapler', 'SUPPLY', 'Unit: pc', 110.00, 35, 'https://commons.wikimedia.org/wiki/Category:Staplers'],
    ['Metal Paper Clips', 'SUPPLY', 'Unit: box', 22.00, 120, 'https://commons.wikimedia.org/wiki/Category:Paperclips'],
    ['Assorted Binder Clips', 'SUPPLY', 'Unit: pack', 48.00, 70, 'https://commons.wikimedia.org/wiki/Category:Binder_clips'],
    ['Scientific Calculator', 'SUPPLY', 'Unit: pc', 250.00, 25, 'https://commons.wikimedia.org/wiki/Category:Calculators'],
    ['Zipper Pencil Case', 'SUPPLY', 'Unit: pc', 75.00, 50, 'https://commons.wikimedia.org/wiki/Category:Pencil_cases'],
    ['Sticky Notes Pad', 'PAPER', 'Unit: pad', 32.00, 100, 'https://commons.wikimedia.org/wiki/Category:Sticky_notes'],
    ['Manila Folder Pack', 'PAPER', 'Unit: pack', 45.00, 80, 'https://en.wikipedia.org/wiki/Manila_folder'],
];

function product_key(string $name): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
}

$sellerStmt = $conn->prepare("SELECT id FROM sellers WHERE email = ? LIMIT 1");
$sellerStmt->bind_param("s", $supplierEmail);
$sellerStmt->execute();
$seller = $sellerStmt->get_result()->fetch_assoc();
if (!$seller) {
    throw new RuntimeException('Supplier account was not found: ' . $supplierEmail);
}
$sellerId = (int) $seller['id'];

$conn->begin_transaction();

try {
    $rows = [];
    $result = $conn->query("
        SELECT p.id, p.name, p.image_url, COUNT(oi.id) AS order_refs
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        GROUP BY p.id, p.name, p.image_url
        ORDER BY p.id ASC
    ");
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    $groups = [];
    foreach ($rows as $row) {
        $groups[product_key($row['name'])][] = $row;
    }

    $deleteStmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $deletedDuplicates = 0;
    $keptDuplicateRefs = 0;

    foreach ($groups as $groupRows) {
        if (count($groupRows) < 2) {
            continue;
        }

        usort($groupRows, function ($a, $b) {
            $aRefs = (int) $a['order_refs'];
            $bRefs = (int) $b['order_refs'];
            if ($aRefs !== $bRefs) {
                return $bRefs <=> $aRefs;
            }

            $aHasImage = trim((string) $a['image_url']) !== '';
            $bHasImage = trim((string) $b['image_url']) !== '';
            if ($aHasImage !== $bHasImage) {
                return $bHasImage <=> $aHasImage;
            }

            return (int) $a['id'] <=> (int) $b['id'];
        });

        array_shift($groupRows);
        foreach ($groupRows as $duplicate) {
            if ((int) $duplicate['order_refs'] > 0) {
                $keptDuplicateRefs++;
                continue;
            }
            $duplicateId = (int) $duplicate['id'];
            $deleteStmt->bind_param("i", $duplicateId);
            $deleteStmt->execute();
            $deletedDuplicates++;
        }
    }

    $checkStmt = $conn->prepare("SELECT id FROM products WHERE seller_id = ? AND LOWER(name) = LOWER(?) LIMIT 1");
    $insertStmt = $conn->prepare("
        INSERT INTO products (seller_id, name, category, description, price, stock, status, image_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, '')
    ");
    $updateStmt = $conn->prepare("
        UPDATE products
        SET category = ?, description = ?, price = ?, stock = ?, status = ?, image_url = ''
        WHERE id = ? AND seller_id = ?
    ");

    $added = 0;
    $updated = 0;

    foreach ($products as $product) {
        [$name, $category, $description, $price, $stock, $source] = $product;
        $status = productStatusFromStock((int) $stock);

        $checkStmt->bind_param("is", $sellerId, $name);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        if ($existing) {
            $productId = (int) $existing['id'];
            $updateStmt->bind_param("ssdisii", $category, $description, $price, $stock, $status, $productId, $sellerId);
            $updateStmt->execute();
            $updated++;
        } else {
            $insertStmt->bind_param("isssdis", $sellerId, $name, $category, $description, $price, $stock, $status);
            $insertStmt->execute();
            $added++;
        }
    }

    $conn->commit();

    echo "Supplier seller_id: {$sellerId}\n";
    echo "Deleted unreferenced duplicate products: {$deletedDuplicates}\n";
    echo "Kept duplicates used by existing orders: {$keptDuplicateRefs}\n";
    echo "Added products: {$added}\n";
    echo "Updated products: {$updated}\n";
    echo "Manual image reference links:\n";
    foreach ($products as $product) {
        echo $product[0] . ' => ' . $product[5] . "\n";
    }
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
