#!/usr/bin/env php
<?php
$root = $argv[1] ?? dirname(__DIR__);
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($argv[2] ?? '')));
$dir = $root . '/content/blog/posts';
if ($slug === '') {
    foreach (glob($dir . '/*.md') ?: [] as $file) {
        $raw = (string)file_get_contents($file);
        if (!preg_match('/^category:\s*help\s*$/mi', $raw)) continue;
        preg_match('/^title:\s*(.+)$/m', $raw, $title);
        printf("  %-28s %s\n", pathinfo($file, PATHINFO_FILENAME), trim($title[1] ?? ''));
    }
    exit(0);
}
$file = $dir . '/' . $slug . '.md';
if (!is_file($file) || !preg_match('/^category:\s*help\s*$/mi', (string)file_get_contents($file))) {
    fwrite(STDERR, "Customer guide not found: {$slug}\n");
    exit(1);
}
echo file_get_contents($file);
