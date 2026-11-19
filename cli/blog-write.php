#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Services\BlogService;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$root = $argv[1] ?? dirname(__DIR__);
$editSlug = $argv[3] ?? '';
$blog = new BlogService($root . '/content/blog/posts');

function prompt(string $label, string $default = ''): string {
    echo $label, ' [', $default, ']: ';
    $line = fgets(STDIN);
    if ($line === false) throw new RuntimeException('Input ended before the article was saved.');
    $value = trim($line);
    return $value === '' ? $default : $value;
}

try {
    $existing = $editSlug !== '' ? $blog->find($editSlug) : [];
    if ($existing === null) throw new InvalidArgumentException('Article not found.');
    echo $editSlug === '' ? "New blog article\n" : "Editing {$editSlug}\n";
    $title = prompt('Title', (string)($existing['title'] ?? ''));
    if ($title === '') throw new InvalidArgumentException('Enter an article title.');
    $defaultSlug = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($title)), '-');
    $slug = prompt('Slug', $editSlug !== '' ? $editSlug : $defaultSlug);
    if ($editSlug !== '' && $slug !== $editSlug) throw new InvalidArgumentException('An existing article keeps its original slug.');
    if ($slug === '') throw new InvalidArgumentException('Enter an article slug.');
    $categories = array_column($blog->categories(), 'slug');
    echo 'Categories: ', implode(', ', $categories), "\n";
    $category = prompt('Category', (string)($existing['category'] ?? $categories[0] ?? ''));
    $author = prompt('Author', (string)($existing['author'] ?? ''));
    $date = prompt('Publication date (YYYY-MM-DD)', (string)($existing['published_at'] ?? date('Y-m-d')));
    $excerpt = prompt('Excerpt', (string)($existing['excerpt'] ?? ''));
    $publication = strtolower(prompt('Publish when due? (y/n)', !empty($existing['published']) ? 'y' : 'n'));
    if (!in_array($publication, ['y', 'yes', '1', 'true', 'n', 'no', '0', 'false'], true)) {
        throw new InvalidArgumentException('Enter y or n for publication.');
    }

    echo "Article Markdown. Enter . on its own line to finish or keep the existing body.\n";
    $lines = [];
    while (($line = fgets(STDIN)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === '.') break;
        $lines[] = $line;
    }
    if ($line === false) throw new RuntimeException('Input ended before the article was saved.');
    $content = $lines ? implode("\n", $lines) : (string)($existing['content'] ?? '');
    if (trim($content) === '') throw new InvalidArgumentException('Enter article content.');

    $blog->save(array_replace($existing, [
        'title' => $title, 'slug' => $slug, 'original_slug' => $editSlug,
        'category' => $category, 'author' => $author, 'published_at' => $date,
        'excerpt' => $excerpt, 'published' => in_array($publication, ['y', 'yes', '1', 'true'], true),
        'content' => $content, 'expected_revision' => $existing['_revision'] ?? 'new',
    ]));
    echo "Saved {$slug}. Open /admin/blog?reload={$slug} to review.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
