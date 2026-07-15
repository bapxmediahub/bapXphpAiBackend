<?php
namespace App\Services;
use App\Services\MarkdownRenderer;
final class BlogService {
    private string $postsDir;
    private string $categoriesFile;
    public function __construct() {
        $this->postsDir = app_path('content/blog/posts');
        $this->categoriesFile = app_path('content/blog/categories.yaml');
        if (!is_dir($this->postsDir)) mkdir($this->postsDir, 0775, true);
    }
    public function all(): array {
        $posts = [];
        foreach (glob($this->postsDir . '/*.md') ?: [] as $file) {
            $post = $this->parseFile($file);
            if ($post) $posts[] = $post;
        }
        usort($posts, fn($a, $b) => strcmp($b['published_at'] ?? '', $a['published_at'] ?? ''));
        return $posts;
    }
    public function find(string $slug): ?array {
        $file = $this->postsDir . '/' . $slug . '.md';
        return is_file($file) ? $this->parseFile($file) : null;
    }
    public function categories(): array {
        if (!is_file($this->categoriesFile)) return [];
        $yaml = @file_get_contents($this->categoriesFile);
        if ($yaml === false || $yaml === '') return [];
        return $this->parseYamlList($yaml);
    }
    public function save(array $data): array {
        $slug = $data['slug'] ?? '';
        if ($slug === '') $slug = $this->slugify($data['title'] ?? 'untitled');
        $content = $data['content'] ?? '';
        unset($data['content']);
        $frontmatter = '';
        foreach (['title','slug','category','published','published_at','updated_at','summary','order','excerpt','author','seo_title','seo_description','keywords','og_image','image_alt','source_url','template'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $val = $data[$key];
                if (is_bool($val)) $val = $val ? 'true' : 'false';
                elseif (is_string($val) && (str_contains($val, ':') || str_contains($val, '#'))) $val = '"' . str_replace('"', '\"', $val) . '"';
                $frontmatter .= $key . ': ' . $val . "\n";
            }
        }
        $md = "---\n{$frontmatter}---\n\n{$content}";
        file_put_contents($this->postsDir . '/' . $slug . '.md', $md, LOCK_EX);
        $data['slug'] = $slug;
        return $data;
    }
    public function delete(string $slug): void {
        $file = $this->postsDir . '/' . $slug . '.md';
        if (is_file($file)) unlink($file);
    }
    public function saveCategory(array $data): void {
        $cats = $this->categories();
        $found = false;
        foreach ($cats as &$c) {
            if (($c['slug'] ?? '') === ($data['slug'] ?? '')) { $c = $data; $found = true; break; }
        }
        if (!$found) $cats[] = $data;
        $yaml = "categories:\n";
        foreach ($cats as $c) {
            $yaml .= "  - slug: " . ($c['slug'] ?? '') . "\n";
            $yaml .= "    name: " . ($c['name'] ?? '') . "\n";
            $yaml .= "    description: " . ($c['description'] ?? '') . "\n";
        }
        file_put_contents($this->categoriesFile, $yaml, LOCK_EX);
    }
    public function deleteCategory(string $slug): void {
        $cats = array_values(array_filter($this->categories(), fn($c) => ($c['slug'] ?? '') !== $slug));
        $yaml = "categories:\n";
        foreach ($cats as $c) {
            $yaml .= "  - slug: " . ($c['slug'] ?? '') . "\n";
            $yaml .= "    name: " . ($c['name'] ?? '') . "\n";
            $yaml .= "    description: " . ($c['description'] ?? '') . "\n";
        }
        file_put_contents($this->categoriesFile, $yaml, LOCK_EX);
    }
    private function parseFile(string $file): ?array {
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') return null;
        $meta = [];
        $content = $raw;
        if (str_starts_with($raw, '---')) {
            $parts = explode('---', $raw, 3);
            if (count($parts) >= 3) {
                $front = trim($parts[1]);
                $content = trim($parts[2]);
                foreach (explode("\n", $front) as $line) {
                    $line = trim($line);
                    if ($line === '' || !str_contains($line, ':')) continue;
                    [$k, $v] = explode(':', $line, 2);
                    $k = trim($k); $v = trim($v);
                    if ($v === 'true') $v = true; elseif ($v === 'false') $v = false;
                    elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) $v = $v;
                    elseif (is_numeric($v)) $v = (float)$v;
                    else { $v = trim($v, '"\'"'); }
                    $meta[$k] = $v;
                }
            }
        }
        $meta['content'] = $content;
        $meta['slug'] = $meta['slug'] ?? pathinfo($file, PATHINFO_FILENAME);
        $meta['published'] = $meta['published'] ?? true;
        $meta['html'] = (new MarkdownRenderer())->render($content);
        return $meta;
    }
    private function parseYamlList(string $yaml): array {
        $items = [];
        $current = null;
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^\s*-\s*slug:\s*(.+)$/', $line, $m)) {
                if ($current) $items[] = $current;
                $current = ['slug' => trim($m[1])];
            } elseif ($current && preg_match('/^\s+(name|description):\s*(.+)$/', $line, $m)) {
                $current[$m[1]] = trim($m[2]);
            }
        }
        if ($current) $items[] = $current;
        return $items;
    }
    private function slugify(string $text): string {
        return strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', preg_replace('/[^\w\s-]/u', '', $text)), '-'));
    }
}
