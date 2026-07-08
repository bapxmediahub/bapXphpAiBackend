#!/usr/bin/env php
<?php

$root = $argv[1] ?? __DIR__ . '/..';
$mode = $argv[2] ?? 'new';
$editSlug = $argv[3] ?? '';

$postsDir = $root . '/content/blog/posts';
$catsFile = $root . '/content/blog/categories.yaml';
if (!is_dir($postsDir)) mkdir($postsDir, 0775, true);

// ── Load categories ──
$categories = [];
if (is_file($catsFile)) {
    $yaml = file_get_contents($catsFile);
    foreach (explode("\n", $yaml) as $line) {
        if (preg_match('/^\s*-\s*slug:\s*(.+)$/', $line, $m)) {
            $categories[] = trim($m[1]);
        }
    }
}

// ── Load existing post if editing ──
$existing = [];
if ($editSlug !== '') {
    $f = "{$postsDir}/{$editSlug}.md";
    if (!is_file($f)) { echo "Post not found: {$editSlug}.md\n"; exit(1); }
    $raw = file_get_contents($f);
    if (str_starts_with($raw, '---')) {
        $parts = explode('---', $raw, 3);
        if (count($parts) >= 3) {
            foreach (explode("\n", trim($parts[1])) as $line) {
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) continue;
                [$k, $v] = explode(':', $line, 2);
                $existing[trim($k)] = trim($v);
            }
            $existing['content'] = trim($parts[2]);
        }
    }
}

// ── Interactive form ──
echo "━━━ Blog Post Writer ━━━━━━━━━━━━━━━━━━━━━━━━━\n";
if ($editSlug) echo "  Editing: {$editSlug}\n";
echo "\n";

$title = readline("  Title [" . ($existing['title'] ?? '') . "]: ");
$title = $title !== '' ? $title : ($existing['title'] ?? '');
if ($title === '') { echo "  Title is required.\n"; exit(1); }

$autoSlug = strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', preg_replace('/[^\w\s-]/u', '', $title)), '-'));
$slugDefault = $editSlug ?: $autoSlug;
$slug = readline("  Slug [{$slugDefault}]: ");
$slug = $slug !== '' ? strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $slug))) : $slugDefault;

if ($editSlug !== '' && $editSlug !== $slug) {
    // Rename file
    $oldFile = "{$postsDir}/{$editSlug}.md";
    $newFile = "{$postsDir}/{$slug}.md";
    if (is_file($newFile) && $editSlug !== $slug) {
        echo "  File {$slug}.md already exists. Aborting.\n";
        exit(1);
    }
    rename($oldFile, $newFile);
}

echo "\n  Categories: " . ($categories ? implode(', ', $categories) : '(none)') . "\n";
$catDefault = $existing['category'] ?? ($categories[0] ?? '');
$category = readline("  Category [{$catDefault}]: ");
$category = $category !== '' ? $category : $catDefault;

$authorDefault = $existing['author'] ?? '';
$author = readline("  Author [{$authorDefault}]: ");
$author = $author !== '' ? $author : $authorDefault;

$dateDefault = $existing['published_at'] ?? date('Y-m-d');
$date = readline("  Date (YYYY-MM-DD) [{$dateDefault}]: ");
$date = $date !== '' ? $date : $dateDefault;

echo "\n  Excerpt (1-2 sentence summary):\n";
$excerptDefault = $existing['excerpt'] ?? '';
$excerpt = readline("  [{$excerptDefault}]: ");
$excerpt = $excerpt !== '' ? $excerpt : $excerptDefault;

$publishedDefault = isset($existing['published']) ? ($existing['published'] === 'true' ? 'y' : 'n') : 'y';
$publishedRaw = readline("  Published? (y/n) [{$publishedDefault}]: ");
$publishedRaw = $publishedRaw !== '' ? $publishedRaw : $publishedDefault;
$published = $publishedRaw === 'y' || $publishedRaw === 'true' || $publishedRaw === '1';

echo "\n  Content (Markdown body). Enter '.' on its own line to finish:\n";
echo "  ────────────────────────────────────────────\n";
$contentDefault = $existing['content'] ?? '';
if ($contentDefault) {
    echo "  (Existing content loaded — type '.' to keep it as-is)\n";
}
$contentLines = [];
while (true) {
    $line = readline('');
    if ($line === '.') break;
    $contentLines[] = $line;
}
$content = implode("\n", $contentLines);
if (!$content && $contentDefault) {
    $content = $contentDefault;
}
if (!$content) {
    echo "  Content is required.\n";
    exit(1);
}

// ── Build frontmatter ──
$frontmatter = "---\n";
$frontmatter .= "title: {$title}\n";
$frontmatter .= "slug: {$slug}\n";
if ($category) $frontmatter .= "category: {$category}\n";
if ($author) $frontmatter .= "author: {$author}\n";
$frontmatter .= "published: " . ($published ? 'true' : 'false') . "\n";
$frontmatter .= "published_at: {$date}\n";
if ($excerpt) $frontmatter .= "excerpt: {$excerpt}\n";
$frontmatter .= "---\n\n";

// ── Generate URL ──
$url = "/blog/{$slug}";
$adminUrl = "/admin/blog?edit={$slug}";

$md = $frontmatter . $content;
$file = "{$postsDir}/{$slug}.md";
file_put_contents($file, $md, LOCK_EX);

echo "\n  ────────────────────────────────────────────\n";
echo "  ✅ Written: {$file}\n";
echo "  URL:       {$url}\n";
echo "  Admin:     {$adminUrl}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
