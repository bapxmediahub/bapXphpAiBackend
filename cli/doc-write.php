#!/usr/bin/env php
<?php
$root = $argv[1] ?? dirname(__DIR__);
$editSlug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($argv[2] ?? '')));
$dir = $root . '/content/docs';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$existing = $editSlug !== '' && is_file("{$dir}/{$editSlug}.md") ? (string)file_get_contents("{$dir}/{$editSlug}.md") : '';
$title = trim((string)readline('Title: '));
if ($title === '' && preg_match('/^title:\s*(.+)$/m', $existing, $match)) $title = trim($match[1]);
if ($title === '') { fwrite(STDERR, "Title is required.\n"); exit(1); }
$defaultSlug = $editSlug ?: trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $title)), '-');
$slug = trim((string)readline("Slug [{$defaultSlug}]: ")) ?: $defaultSlug;
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
$summary = trim((string)readline('Summary: '));
if ($summary === '' && preg_match('/^summary:\s*(.+)$/m', $existing, $match)) $summary = trim($match[1]);
$order = (int)(trim((string)readline('Order [100]: ')) ?: 100);
echo "Markdown body; enter a single . to finish:\n";
$lines = [];
while (($line = readline()) !== false && $line !== '.') $lines[] = $line;
$body = trim(implode("\n", $lines));
if ($body === '' && $existing !== '') $body = trim((string)(explode('---', $existing, 3)[2] ?? ''));
if ($body === '') { fwrite(STDERR, "Body is required.\n"); exit(1); }
$document = "---\ntitle: {$title}\nslug: {$slug}\nsummary: {$summary}\norder: {$order}\nicon: guide\n---\n\n# {$title}\n\n{$body}\n";
file_put_contents("{$dir}/{$slug}.md", $document, LOCK_EX);
if ($editSlug !== '' && $editSlug !== $slug) @unlink("{$dir}/{$editSlug}.md");
echo "Written: content/docs/{$slug}.md\nURL: /help/{$slug}\n";
