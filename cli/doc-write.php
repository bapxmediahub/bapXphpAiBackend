#!/usr/bin/env php
<?php
$root = $argv[1] ?? dirname(__DIR__);
$editSlug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($argv[2] ?? '')));
$dir = $root . '/content/blog/posts';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$existing = $editSlug !== '' && is_file("{$dir}/{$editSlug}.md") ? (string)file_get_contents("{$dir}/{$editSlug}.md") : '';
$title = trim((string)readline('Title: '));
if ($title === '' && preg_match('/^title:\s*(.+)$/m', $existing, $match)) $title = trim($match[1]);
if ($title === '') { fwrite(STDERR, "Title is required.\n"); exit(1); }
$defaultSlug = $editSlug ?: trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $title)), '-');
$slug = trim((string)readline("Slug [{$defaultSlug}]: ")) ?: $defaultSlug;
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
$summary = trim((string)readline('Summary: '));
if ($summary === '' && preg_match('/^(?:excerpt|summary):\s*(.+)$/m', $existing, $match)) $summary = trim($match[1]);
echo "Markdown body; enter a single . to finish:\n";
$lines = [];
while (($line = readline()) !== false && $line !== '.') $lines[] = $line;
$body = trim(implode("\n", $lines));
if ($body === '' && $existing !== '') $body = trim((string)(explode('---', $existing, 3)[2] ?? ''));
if ($body === '') { fwrite(STDERR, "Body is required.\n"); exit(1); }
$document = "---\ntitle: {$title}\nslug: {$slug}\ncategory: help\npublished: true\npublished_at: " . date('Y-m-d') . "\nauthor: Sri Panchami Spiritual\nexcerpt: {$summary}\n---\n\n{$body}\n";
file_put_contents("{$dir}/{$slug}.md", $document, LOCK_EX);
if ($editSlug !== '' && $editSlug !== $slug) @unlink("{$dir}/{$editSlug}.md");
echo "Written: content/blog/posts/{$slug}.md\nURL: /blog/{$slug}\n";
