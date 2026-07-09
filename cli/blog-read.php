#!/usr/bin/env php
<?php

$root = $argv[1] ?? __DIR__ . '/..';
$slug = $argv[2] ?? '';
if ($slug === '') {
    echo "Usage: php cli/blog-read.php <slug>\n";
    echo "  Lists all posts if no slug given\n\n";
    $dir = $root . '/content/blog/posts';
    if (!is_dir($dir)) { echo "No blog posts directory.\n"; exit(1); }
    $files = glob($dir . '/*.md');
    if (!$files) { echo "No blog posts found.\n"; exit(0); }
    foreach ($files as $f) {
        $s = pathinfo($f, PATHINFO_FILENAME);
        $meta = parseFrontmatter($f);
        echo "  {$s}";
        if ($meta['title'] ?? '') echo "  — {$meta['title']}";
        if ($meta['published_at'] ?? '') echo "  ({$meta['published_at']})";
        echo "\n";
    }
    exit(0);
}

$file = $root . "/content/blog/posts/{$slug}.md";
if (!is_file($file)) {
    echo "Blog post not found: {$slug}.md\n";
    echo "Use: php cli/blog-read.php <slug>\n";
    exit(1);
}

$raw = file_get_contents($file);
$meta = [];
$content = $raw;
if (str_starts_with($raw, '---')) {
    $parts = explode('---', $raw, 3);
    if (count($parts) >= 3) {
        foreach (explode("\n", trim($parts[1])) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) continue;
            [$k, $v] = explode(':', $line, 2);
            $meta[trim($k)] = trim($v);
        }
        $content = trim($parts[2]);
    }
}

echo "────────────────────────────────────────\n";
echo "  Title:      " . ($meta['title'] ?? 'untitled') . "\n";
echo "  Slug:       {$slug}\n";
echo "  Category:   " . ($meta['category'] ?? '—') . "\n";
echo "  Published:  " . ($meta['published'] ?? 'true') . "\n";
echo "  Date:       " . ($meta['published_at'] ?? '—') . "\n";
echo "  Author:     " . ($meta['author'] ?? '—') . "\n";
echo "  Excerpt:    " . ($meta['excerpt'] ?? '—') . "\n";
echo "────────────────────────────────────────\n";
echo "\n" . $content . "\n";

function parseFrontmatter(string $file): array {
    $raw = file_get_contents($file);
    $meta = [];
    if (str_starts_with($raw, '---')) {
        $parts = explode('---', $raw, 3);
        if (count($parts) >= 3) {
            foreach (explode("\n", trim($parts[1])) as $line) {
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) continue;
                [$k, $v] = explode(':', $line, 2);
                $meta[trim($k)] = trim($v);
            }
        }
    }
    return $meta;
}
