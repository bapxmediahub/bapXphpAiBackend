<?php
/**
 * Batch image optimizer — WebP conversion + resize
 * Usage: php cli/optimize-images.php [--dry-run] [--force]
 */

$dryRun = in_array('--dry-run', $argv ?? []);
$force = in_array('--force', $argv ?? []);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ImageOptimizerService;

$opt = new ImageOptimizerService();
$totalBefore = 0;
$totalAfter = 0;
$converted = 0;

function convertDir(ImageOptimizerService $opt, string $dir, array $exts, array $opts = []): void {
    global $totalBefore, $totalAfter, $converted, $dryRun, $force;
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*.{' . implode(',', $exts) . '}', GLOB_BRACE) ?: [] as $file) {
        $webpPath = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '.webp';
        if (is_file($webpPath) && !$force) {
            echo "  SKIP (exists): " . basename($file) . "\n";
            continue;
        }
        $size = filesize($file);
        if ($dryRun) {
            echo "  WOULD CONVERT: " . basename($file) . " (" . number_format($size / 1024, 1) . " KB)\n";
            $totalBefore += $size;
            continue;
        }
        $result = $opt->optimize($file, dirname($file), $opts);
        if ($result) {
            $afterSize = filesize($result);
            $totalBefore += $size;
            $totalAfter += $afterSize;
            $converted++;
            $saved = $size - $afterSize;
            $pct = $size > 0 ? round((1 - $afterSize / $size) * 100, 1) : 0;
            echo "  OK: " . basename($file) . " -> " . basename($result) . " (" . number_format($size/1024,1) . "KB -> " . number_format($afterSize/1024,1) . "KB, -{$pct}%)\n";
        } else {
            echo "  FAIL: " . basename($file) . "\n";
        }
    }
}

echo "=== HERO IMAGES (resize 480x640) ===\n";
$heroDir = __DIR__ . '/../assets/images/hero/varahi';
convertDir($opt, $heroDir, ['png', 'jpg', 'jpeg'], ['max_width' => 480, 'max_height' => 640, 'quality' => 80]);

echo "\n=== PRODUCT IMAGES (resize 800x800) ===\n";
$prodDir = __DIR__ . '/../assets/images/products';
convertDir($opt, $prodDir, ['png', 'jpg', 'jpeg'], ['max_width' => 800, 'max_height' => 800, 'quality' => 80]);

echo "\n=== TEMPLE IMAGES (resize 1200x800) ===\n";
$templeDir = __DIR__ . '/../assets/images/temples';
convertDir($opt, $templeDir, ['png', 'jpg', 'jpeg'], ['max_width' => 1200, 'max_height' => 800, 'quality' => 80]);

echo "\n=== ASTROLOGER CLIENT IMAGES (resize 480x640) ===\n";
$astroDir = __DIR__ . '/../assets/images/astrologers/client';
convertDir($opt, $astroDir, ['png', 'jpg', 'jpeg'], ['max_width' => 480, 'max_height' => 640, 'quality' => 80]);

echo "\n=== MEDIA LIBRARY IMAGES (resize 1200x1200) ===\n";
$mediaDir = __DIR__ . '/../assets/images/media';
if (is_dir($mediaDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaDir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg'], true)) {
            $webpPath = $file->getPath() . '/' . $file->getBasename('.' . $file->getExtension()) . '.webp';
            if (is_file($webpPath) && !$force) {
                continue;
            }
            $size = $file->getSize();
            if ($dryRun) {
                echo "  WOULD CONVERT: " . $file->getFilename() . "\n";
                $totalBefore += $size;
                continue;
            }
            $result = $opt->optimize($file->getRealPath(), $file->getPath(), ['max_width' => 1200, 'max_height' => 1200, 'quality' => 80]);
            if ($result) {
                $afterSize = filesize($result);
                $totalBefore += $size;
                $totalAfter += $afterSize;
                $converted++;
            }
        }
    }
}

echo "\n=== LARGE ROOT IMAGES ===\n";
$rootImages = [
    __DIR__ . '/../assets/images/hero-temple-bg.png',
    __DIR__ . '/../assets/images/varahi-amman.png',
    __DIR__ . '/../assets/images/logo.jpeg',
    __DIR__ . '/../assets/images/logo-square.jpeg',
];
foreach ($rootImages as $img) {
    if (!is_file($img)) continue;
    $webpPath = dirname($img) . '/' . pathinfo($img, PATHINFO_FILENAME) . '.webp';
    if (is_file($webpPath) && !$force) {
        echo "  SKIP (exists): " . basename($img) . "\n";
        continue;
    }
    $size = filesize($img);
    if ($dryRun) {
        echo "  WOULD CONVERT: " . basename($img) . " (" . number_format($size/1024, 1) . " KB)\n";
        $totalBefore += $size;
        continue;
    }
    $result = $opt->optimize($img, dirname($img), ['max_width' => 1920, 'max_height' => 1920, 'quality' => 80]);
    if ($result) {
        $afterSize = filesize($result);
        $totalBefore += $size;
        $totalAfter += $afterSize;
        $converted++;
        $pct = $size > 0 ? round((1 - $afterSize / $size) * 100, 1) : 0;
        echo "  OK: " . basename($img) . " -> " . basename($result) . " (" . number_format($size/1024,1) . "KB -> " . number_format($afterSize/1024,1) . "KB, -{$pct}%)\n";
    } else {
        echo "  FAIL: " . basename($img) . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
if ($dryRun) {
    echo "DRY RUN — no files changed.\n";
    echo "Estimated total before: " . number_format($totalBefore / 1024 / 1024, 2) . " MB\n";
} else {
    $savedMB = ($totalBefore - $totalAfter) / 1024 / 1024;
    $pctAll = $totalBefore > 0 ? round((1 - $totalAfter / $totalBefore) * 100, 1) : 0;
    echo "Converted: {$converted} files\n";
    echo "Total before: " . number_format($totalBefore / 1024 / 1024, 2) . " MB\n";
    echo "Total after:  " . number_format($totalAfter / 1024 / 1024, 2) . " MB\n";
    echo "Saved:        " . number_format($savedMB, 2) . " MB (-{$pctAll}%)\n";
}
