#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__);
$source = $argv[2] ?? '';
$dryRun = in_array('--dry-run', $argv, true);
$assetsOnly = in_array('--assets-only', $argv, true);
$databaseOnly = in_array('--database-only', $argv, true);

require_once $root . '/app/bootstrap.php';

use App\Services\DatabaseService;
use App\Services\ImageOptimizerService;

if ($source === '' || (!is_file($source) && !is_dir($source))) {
    fwrite(STDERR, "Usage: bapXphp product:images <archive.zip|folder> [--dry-run]\n");
    exit(1);
}

$store = new DatabaseService();
$products = $store->read('products');
$bySlug = [];
foreach ($products as $product) $bySlug[(string)($product['slug'] ?? '')] = $product;

$aliases = [
    'karuppasami-dollar' => 'karuppasami-dollar',
    'lakshmi-dollar' => 'lakshmi-dollar',
    'lingam-dollar' => 'lingam-dollar',
    'murugar-vel-mayil-dollar' => 'murugar-vel-mayil-dollar',
    'varahi-amman-dollar' => 'varahi-amman-dollar',
    'varahi-amman-ring' => 'varahi-amman-ring',
    'yamthiram-dollar' => 'yamthiram-dollar',
];

$normalize = static fn(string $value): string => trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
$entries = [];
$zip = null;
if (is_file($source)) {
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZipArchive is required for archive imports.');
    $zip = new ZipArchive();
    if ($zip->open($source) !== true) throw new RuntimeException('Unable to open image archive.');
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $name)) continue;
        $entries[] = ['name' => $name, 'zip_index' => $i];
    }
} else {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) continue;
        $entries[] = ['name' => $file->getPathname(), 'path' => $file->getPathname()];
    }
}

$grouped = [];
foreach ($entries as $entry) {
    $parts = preg_split('~[\\\\/]~', (string)$entry['name']) ?: [];
    $folder = count($parts) > 1 ? $parts[count($parts) - 2] : pathinfo((string)$entry['name'], PATHINFO_FILENAME);
    $normalizedFolder = $normalize($folder);
    $normalizedFile = $normalize(pathinfo((string)$entry['name'], PATHINFO_FILENAME));
    $slug = $aliases[$normalizedFolder] ?? '';
    if ($slug === '') {
        foreach (array_keys($bySlug) as $candidate) {
            if (str_starts_with($normalizedFile, $candidate . '-')) { $slug = $candidate; break; }
        }
    }
    if ($slug === '' || !isset($bySlug[$slug])) {
        echo "SKIP  {$entry['name']} (no matching product)\n";
        continue;
    }
    $grouped[$slug][] = $entry;
}

$rank = static function(array $entry): array {
    $name = strtolower(basename((string)$entry['name']));
    $position = str_contains($name, 'front') ? 0 : (str_contains($name, 'back') ? 1 : 2);
    return [$position, $name];
};

$optimizer = new ImageOptimizerService();
$destination = $root . '/assets/images/products';
if (!$dryRun && !is_dir($destination)) mkdir($destination, 0775, true);

foreach ($grouped as $slug => $images) {
    usort($images, static fn(array $a, array $b): int => $rank($a) <=> $rank($b));
    $paths = [];
    foreach ($images as $index => $entry) {
        $original = basename((string)$entry['name']);
        $label = str_contains(strtolower($original), 'front') ? 'front' : (str_contains(strtolower($original), 'back') ? 'back' : 'side-' . max(1, $index - 1));
        $publicPath = '/assets/images/products/' . $slug . '-' . $label . '.webp';
        $paths[] = $publicPath;
        echo ($dryRun ? 'PLAN  ' : 'WRITE ') . $slug . ' <- ' . $original . ' => ' . $publicPath . "\n";
        if ($dryRun || $databaseOnly) continue;
        $temp = tempnam(sys_get_temp_dir(), 'bapx-product-');
        if ($temp === false) throw new RuntimeException('Unable to allocate temporary image file.');
        $bytes = $zip instanceof ZipArchive ? $zip->getFromIndex((int)$entry['zip_index']) : file_get_contents((string)$entry['path']);
        if ($bytes === false) throw new RuntimeException('Unable to read ' . $entry['name']);
        file_put_contents($temp, $bytes);
        $optimized = $optimizer->optimize($temp, $destination, ['max_width' => 1600, 'max_height' => 1600, 'quality' => 84]);
        @unlink($temp);
        if (!$optimized) throw new RuntimeException('Unable to optimize ' . $entry['name']);
        $target = $destination . '/' . basename($publicPath);
        if ($optimized !== $target && !rename($optimized, $target)) throw new RuntimeException('Unable to place ' . $target);
    }
    if (!$dryRun && !$assetsOnly) {
        $product = $bySlug[$slug];
        $product['image_url'] = $paths[0];
        $product['image_urls'] = $paths;
        $store->upsert('products', $product);
    }
}

if ($zip instanceof ZipArchive) $zip->close();
echo $dryRun ? "Dry run complete. No files or database records changed.\n" : ($assetsOnly ? "Product assets imported; database was not changed.\n" : "Product image import complete.\n");
