#!/usr/bin/env php
<?php

$root = $argv[1] ?? __DIR__ . '/..';
$editSlug = $argv[2] ?? '';

require_once $root . '/app/bootstrap.php';

// Load MySQL config
$config = require $root . '/config/database.php';
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo "MySQL connection failed. Check config/database.php.\n";
    exit(1);
}

// Load all products
$products = [];
$stmt = $pdo->query("SELECT id, _data FROM products ORDER BY _created_at DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data = json_decode($row['_data'], true);
    $data['id'] = $row['id'];
    $products[] = $data;
}

// Load categories
$categories = [];
$stmt = $pdo->query("SELECT _data FROM categories ORDER BY _created_at DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categories[] = json_decode($row['_data'], true);
}

// Find existing
$existing = [];
$existingIdx = -1;
if ($editSlug !== '') {
    foreach ($products as $i => $p) {
        if (($p['slug'] ?? '') === $editSlug) { $existing = $p; $existingIdx = $i; break; }
    }
    if (!$existing) { echo "Product not found: {$editSlug}\n"; exit(1); }
}

echo "━━━ Product Writer ━━━━━━━━━━━━━━━━━━━━━━━━━\n";
if ($editSlug) echo "  Editing: {$editSlug}\n";
echo "\n";

// ── Name ──
$name = readline("  Name [" . ($existing['name'] ?? '') . "]: ");
$name = $name !== '' ? $name : ($existing['name'] ?? '');
if ($name === '') { echo "  Name is required.\n"; exit(1); }

$autoSlug = strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', preg_replace('/[^\w\s-]/u', '', $name)), '-'));
$slugDefault = $editSlug ?: $autoSlug;
$newSlug = readline("  Slug [{$slugDefault}]: ");
$newSlug = $newSlug !== '' ? strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $newSlug))) : $slugDefault;

// ── Category ──
echo "  Available categories: ";
foreach ($categories as $c) { echo ($c['slug'] ?? '—') . ' '; }
echo "\n";
$catDefault = $existing['category'] ?? ($categories[0]['slug'] ?? '');
$category = readline("  Category [{$catDefault}]: ");
$category = $category !== '' ? $category : $catDefault;

// ── Price ──
$priceDefault = (string)($existing['price'] ?? '');
$priceRaw = readline("  Price (₹) [{$priceDefault}]: ");
$price = $priceRaw !== '' ? (float)$priceRaw : ($existing['price'] ?? 0);

$offerDefault = (string)($existing['offer_price'] ?? '');
$offerRaw = readline("  Offer Price (₹) [{$offerDefault}]: ");
$offerPrice = $offerRaw !== '' ? (float)$offerRaw : ($existing['offer_price'] ?? 0);

// ── Stock ──
echo "  Stock: in_stock, active, inactive, draft, out_of_stock\n";
$stockDefault = $existing['stock_status'] ?? 'in_stock';
$stock = readline("  Status [{$stockDefault}]: ");
$stock = $stock !== '' ? $stock : $stockDefault;

// ── Image URL ──
$imgDefault = $existing['image_url'] ?? '';
$imageUrl = readline("  Image URL [{$imgDefault}]: ");
$imageUrl = $imageUrl !== '' ? $imageUrl : $imgDefault;

// ── Description ──
echo "  Description (Markdown, enter '.' on its own line to finish):\n";
$descDefault = $existing['description'] ?? '';
if ($descDefault) echo "  (Existing: {$descDefault})\n";
$descLines = [];
while (true) {
    $line = readline('  ');
    if ($line === '.') break;
    $descLines[] = $line;
}
$description = $descLines ? implode("\n", $descLines) : $descDefault;

// ── Highlights ──
echo "  Highlights (one per line, enter '.' to finish):\n";
$highlights = $existing['highlights'] ?? [];
if ($highlights) echo "  Existing: " . implode(', ', $highlights) . "\n";
$hlLines = [];
while (true) {
    $line = readline('  ');
    if ($line === '.') break;
    if ($line !== '') $hlLines[] = $line;
}
$highlights = $hlLines ?: $highlights;

// ── Build record ──
$record = [
    'id' => $existing['id'] ?? 'prod_' . bin2hex(random_bytes(6)),
    'slug' => $newSlug,
    'name' => $name,
    'category' => $category,
    'categories' => [$category],
    'price' => $price,
    'offer_price' => $offerPrice,
    'stock_status' => $stock,
    'image_url' => $imageUrl,
    'description' => $description,
    'highlights' => $highlights,
];
if (!empty($existing['image_urls'])) $record['image_urls'] = $existing['image_urls'];
if (!empty($existing['description_points'])) $record['description_points'] = $existing['description_points'];
if (!empty($existing['specifications'])) $record['specifications'] = $existing['specifications'];

// ── Save to MySQL ──
$id = $record['id'];
unset($record['id']);
$stmt = $pdo->prepare("REPLACE INTO products (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, NULL, NULL, NOW(), NOW())");
$stmt->execute([$id, json_encode($record)]);

// If slug changed and editing, update the old entry
if ($editSlug !== '' && $editSlug !== $newSlug && $existingIdx >= 0) {
    echo "  Slug changed: {$editSlug} → {$newSlug}\n";
}

$adminUrl = "/admin/products?edit={$newSlug}";
$publicUrl = "/product/{$newSlug}";

echo "\n  ✅ Saved.\n";
echo "  Admin: {$adminUrl}\n";
echo "  URL:   {$publicUrl}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
