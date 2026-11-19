#!/usr/bin/env php
<?php

use App\Services\DatabaseService;

$root = $argv[1] ?? __DIR__ . '/..';
$editSlug = $argv[2] ?? '';
require_once $root . '/app/bootstrap.php';

function productPrompt(string $prompt): string {
    echo $prompt;
    $line = fgets(STDIN);
    if ($line === false) {
        fwrite(STDERR, "\nInput ended before the product was saved.\n");
        exit(1);
    }
    return rtrim($line, "\r\n");
}

function productLines(string $label): array {
    echo "  $label (one line at a time; enter '.' to finish):\n";
    $lines = [];
    while (($line = productPrompt('  ')) !== '.') $lines[] = $line;
    return $lines;
}

$store = new DatabaseService(true);
$existing = $editSlug === '' ? [] : $store->find('products', $editSlug, 'slug');
if ($existing === null) {
    fwrite(STDERR, "Product not found: $editSlug\n");
    exit(1);
}

echo "━━━ Product Writer ━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$editing = $editSlug !== '';
if ($editing) echo "  Editing: $editSlug\n";
$record = $editing ? ['id'=>$existing['id']] : ['id'=>'prod_' . bin2hex(random_bytes(6))];
$name = trim(productPrompt('  Name [' . ($existing['name'] ?? '') . ']: '));
if (!$editing && $name === '') { fwrite(STDERR, "Name is required.\n"); exit(1); }
if ($name !== '') $record['name'] = $name;

$slugDefault = $editing ? $editSlug : trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
$slug = trim(productPrompt("  Slug [$slugDefault]: "));
if (!$editing || $slug !== '') {
    $record['slug'] = $slug === '' ? $slugDefault : trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)), '-');
    if ($record['slug'] === '') { fwrite(STDERR, "A product slug is required.\n"); exit(1); }
}

$categories = $store->query('SELECT _data FROM categories ORDER BY _created_at DESC, id');
$available = array_map(static fn(array $row): string => json_decode($row['_data'], true)['slug'] ?? '', $categories);
echo '  Available categories: ' . implode(' ', $available) . "\n";
$categoryDefault = $existing['category'] ?? ($available[0] ?? '');
$category = trim(productPrompt("  Category [$categoryDefault]: "));
if ($category !== '' || !$editing) $record['category'] = $category !== '' ? $category : $categoryDefault;
if (!$editing) $record['categories'] = $record['category'] === '' ? [] : [$record['category']];

foreach (['price'=>'Price', 'offer_price'=>'Offer price'] as $field => $label) {
    $value = trim(productPrompt("  $label (₹) [" . ($existing[$field] ?? '') . ']: '));
    if ($value === '' && $editing) continue;
    if ($value !== '' && (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0)) {
        fwrite(STDERR, "$label must be a non-negative number.\n"); exit(1);
    }
    $record[$field] = (float)$value;
}

echo "  Stock: in_stock, active, inactive, draft, out_of_stock\n";
$stockDefault = $existing['stock_status'] ?? 'in_stock';
$stock = trim(productPrompt("  Stock status [$stockDefault]: "));
if ($stock !== '' || !$editing) $record['stock_status'] = $stock !== '' ? $stock : $stockDefault;
$image = trim(productPrompt('  Image URL [' . ($existing['image_url'] ?? '') . ']: '));
if ($image !== '' || !$editing) $record['image_url'] = $image;

$description = productLines('Description');
if ($description !== [] || !$editing) $record['description'] = implode("\n", $description);
$highlights = array_values(array_filter(productLines('Highlights'), static fn(string $line): bool => $line !== ''));
if ($highlights !== [] || !$editing) $record['highlights'] = $highlights;

try {
    // An edit carries only the supplied fields. The locked save merges them with
    // the latest row, including fields the CLI does not expose.
    $saved = $store->saveCatalogProduct($record, !$editing, requireExactTarget: true);
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . "\n"); exit(1);
}
echo "\n  Saved.\n";
echo '  Admin: /admin/products?edit=' . rawurlencode($saved['id']) . "\n";
echo '  URL:   /product/' . rawurlencode($saved['slug'] ?? $editSlug) . "\n";
