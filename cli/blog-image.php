#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__);
$slug = trim((string)($argv[2] ?? ''));
$source = trim((string)($argv[3] ?? ''));
$dryRun = in_array('--dry-run', $argv, true);
$sourceUrl = '';
$imageAlt = '';
foreach ($argv as $index => $argument) {
    if ($argument === '--source-url') $sourceUrl = trim((string)($argv[$index + 1] ?? ''));
    if ($argument === '--alt') $imageAlt = trim((string)($argv[$index + 1] ?? ''));
}

if ($slug === '' || $source === '') {
    fwrite(STDERR, "Usage: bapXphp blog:image <slug> <screenshot-or-image> [--source-url /path] [--alt text] [--dry-run]\n");
    exit(1);
}
if (!preg_match('/^[a-z0-9-]+$/', $slug) || !is_file($source)) {
    fwrite(STDERR, "Provide a valid blog slug and local screenshot/image path.\n");
    exit(1);
}
if (!extension_loaded('gd')) {
    fwrite(STDERR, "The PHP GD extension is required for screenshot cropping.\n");
    exit(1);
}

require $root . '/app/bootstrap.php';

$bytes = file_get_contents($source);
$image = $bytes === false ? false : @imagecreatefromstring($bytes);
if ($image === false) {
    fwrite(STDERR, "Unsupported or unreadable image. Use PNG, JPEG, or WebP.\n");
    exit(1);
}

$sourceWidth = imagesx($image);
$sourceHeight = imagesy($image);
$targetWidth = 1200;
$targetHeight = 675;
$sourceRatio = $sourceWidth / $sourceHeight;
$targetRatio = $targetWidth / $targetHeight;
if ($sourceRatio > $targetRatio) {
    $cropHeight = $sourceHeight;
    $cropWidth = (int)round($sourceHeight * $targetRatio);
    $sourceX = (int)round(($sourceWidth - $cropWidth) / 2);
    $sourceY = 0;
} else {
    $cropWidth = $sourceWidth;
    $cropHeight = (int)round($sourceWidth / $targetRatio);
    $sourceX = 0;
    $sourceY = (int)round(($sourceHeight - $cropHeight) / 2);
}

$relative = "/assets/images/blog/{$slug}.webp";
$target = $root . $relative;
printf("Source: %dx%d\nCrop: %dx%d at %d,%d\nTarget: %s\n", $sourceWidth, $sourceHeight, $cropWidth, $cropHeight, $sourceX, $sourceY, $relative);
if ($dryRun) {
    echo "DRY RUN: no files changed.\n";
    exit(0);
}

if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
$canvas = imagecreatetruecolor($targetWidth, $targetHeight);
imagecopyresampled($canvas, $image, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
if (!imagewebp($canvas, $target, 88)) {
    fwrite(STDERR, "Unable to write cropped WebP image.\n");
    exit(1);
}
$blog = new \App\Services\BlogService();
$post = $blog->find($slug);
if ($post === null) {
    fwrite(STDERR, "Image written, but blog post {$slug} was not found.\n");
    exit(1);
}
$post['og_image'] = $relative;
if ($sourceUrl !== '') $post['source_url'] = $sourceUrl;
if ($imageAlt !== '') $post['image_alt'] = $imageAlt;
$blog->save($post);
echo "Updated {$slug} to use the cropped image for its card and article hero.\n";
