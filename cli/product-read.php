#!/usr/bin/env php
<?php

$root = $argv[1] ?? __DIR__ . '/..';
$slug = $argv[2] ?? '';

// Load MySQL config
$config = require $root . '/config/database.php';
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo "MySQL connection failed. Use bapXphp to check DB config.\n";
    exit(1);
}

if ($slug === '') {
    echo "━━━ Products ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $stmt = $pdo->query("SELECT _data FROM products ORDER BY _created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($products as $json) {
        $p = json_decode($json, true);
        $price = $p['offer_price'] ?? $p['price'] ?? '—';
        $name = $p['name'] ?? $p['slug'] ?? 'untitled';
        printf("  %-30s ₹%-8s  %s\n", $name, $price, $p['slug'] ?? '');
    }
    echo "\nUse: php cli/product-read.php <slug>\n";
    exit(0);
}

$stmt = $pdo->prepare("SELECT _data FROM products WHERE id = ?");
$stmt->execute([$slug]);
$json = $stmt->fetchColumn();
if (!$json) {
    // Try by slug
    $stmt = $pdo->prepare("SELECT _data FROM products WHERE JSON_EXTRACT(_data, '$.slug') = ?");
    $stmt->execute([$slug]);
    $json = $stmt->fetchColumn();
}
if (!$json) {
    echo "Product not found: {$slug}\n";
    echo "Use: php cli/product-read.php <slug>\n";
    exit(1);
}
$found = json_decode($json, true);

echo "━━━ Product ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Name:           " . ($found['name'] ?? '—') . "\n";
echo "  Slug:           " . ($found['slug'] ?? '—') . "\n";
echo "  Category:       " . ($found['category'] ?? '—') . "\n";
echo "  Price:          ₹" . ($found['price'] ?? '—') . "\n";
echo "  Offer Price:    ₹" . ($found['offer_price'] ?? '—') . "\n";
echo "  Stock:          " . ($found['stock_status'] ?? '—') . "\n";
echo "  Image:          " . ($found['image_url'] ?? '—') . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
if (!empty($found['highlights'])) {
    echo "\n  Highlights:\n";
    foreach ($found['highlights'] as $h) echo "    • {$h}\n";
}
if (!empty($found['description'])) {
    echo "\n  Description: {$found['description']}\n";
}
if (!empty($found['description_points'])) {
    echo "\n  Description Points:\n";
    foreach ($found['description_points'] as $dp) echo "    • {$dp}\n";
}
if (!empty($found['specifications'])) {
    echo "\n  Specifications:\n";
    foreach ($found['specifications'] as $k => $v) echo "    {$k}: {$v}\n";
}
if (!empty($found['image_urls'])) {
    echo "\n  Images:\n";
    foreach ($found['image_urls'] as $img) echo "    • {$img}\n";
}
echo "\n────────────────────────────────────────\n";
