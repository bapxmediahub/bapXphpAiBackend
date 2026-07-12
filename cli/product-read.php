#!/usr/bin/env php
<?php

$root = $argv[1] ?? __DIR__ . '/..';
$slug = $argv[2] ?? '';

require_once $root . '/app/bootstrap.php';

$store = new App\Services\DatabaseService();
$products = $store->read('products');

if ($slug === '') {
    echo "━━━ Products ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    foreach ($products as $p) {
        $price = $p['offer_price'] ?? $p['price'] ?? '—';
        $name = $p['name'] ?? $p['slug'] ?? 'untitled';
        printf("  %-30s ₹%-8s  %s\n", $name, $price, $p['slug'] ?? '');
    }
    echo "\nUse: php cli/product-read.php <slug>\n";
    exit(0);
}

$found = null;
foreach ($products as $product) {
    if (($product['id'] ?? '') === $slug || ($product['slug'] ?? '') === $slug) {
        $found = $product;
        break;
    }
}
if (!$found) {
    echo "Product not found: {$slug}\n";
    echo "Use: php cli/product-read.php <slug>\n";
    exit(1);
}

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
