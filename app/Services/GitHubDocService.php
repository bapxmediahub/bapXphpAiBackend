<?php
namespace App\Services;

final class GitHubDocService
{
    private string $cacheDir;
    private int $cacheTtl;

    private const DEFAULT_BASE_URL = 'https://raw.githubusercontent.com/sripanchami-spiritual/blog/main';
    private const BLOG_INDEX_PATH = '/index.json';
    private const CATEGORIES_PATH = '/categories.json';
    private const POSTS_DIR = '/posts';

    public function __construct(?string $cacheDir = null, int $cacheTtl = 3600)
    {
        $this->cacheDir = $cacheDir ?: dirname(__DIR__, 2) . '/storage/cache';
        $this->cacheTtl = $cacheTtl;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function getBaseUrl(): string
    {
        $settingsDir = dirname(__DIR__, 2) . '/config/database.php';
        return self::DEFAULT_BASE_URL;
    }

    public function fetchIndex(): array
    {
        $cacheKey = 'blog_index';
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $url = $this->getBaseUrl() . self::BLOG_INDEX_PATH;
        $data = $this->fetchJson($url);
        if ($data !== null) {
            $this->writeCache($cacheKey, $data);
            return $data;
        }
        return [];
    }

    public function fetchCategories(): array
    {
        $cacheKey = 'blog_categories';
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $url = $this->getBaseUrl() . self::CATEGORIES_PATH;
        $data = $this->fetchJson($url);
        if ($data !== null) {
            $this->writeCache($cacheKey, $data);
            return $data;
        }
        return [];
    }

    public function fetchPost(string $slug): ?string
    {
        $cacheKey = 'blog_post_' . $slug;
        $cached = $this->readCacheString($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $url = $this->getBaseUrl() . self::POSTS_DIR . '/' . $slug . '.md';
        $content = $this->fetchString($url);
        if ($content !== null) {
            $this->writeCacheString($cacheKey, $content);
            return $content;
        }
        return null;
    }

    public function refreshCache(): int
    {
        $count = 0;

        $index = $this->fetchJson($this->getBaseUrl() . self::BLOG_INDEX_PATH);
        if ($index !== null) {
            $this->writeCache('blog_index', $index);
            $count++;
        }

        $categories = $this->fetchJson($this->getBaseUrl() . self::CATEGORIES_PATH);
        if ($categories !== null) {
            $this->writeCache('blog_categories', $categories);
            $count++;
        }

        if ($index) {
            foreach ($index as $post) {
                $slug = $post['slug'] ?? '';
                if (!$slug) continue;
                $content = $this->fetchString($this->getBaseUrl() . self::POSTS_DIR . '/' . $slug . '.md');
                if ($content !== null) {
                    $this->writeCacheString('blog_post_' . $slug, $content);
                    $count++;
                }
            }
        }

        return $count;
    }

    private function fetchJson(string $url): ?array
    {
        $content = $this->fetchString($url);
        if ($content === null) return null;
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    private function fetchString(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "User-Agent: bapXphp-blog-fetcher/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            return null;
        }
        return $result;
    }

    private function readCache(string $key): ?array
    {
        $path = $this->cacheDir . '/' . $key . '.json';
        if (!is_file($path)) return null;
        if (time() - filemtime($path) > $this->cacheTtl) return null;
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function readCacheString(string $key): ?string
    {
        $path = $this->cacheDir . '/' . $key . '.md';
        if (!is_file($path)) return null;
        if (time() - filemtime($path) > $this->cacheTtl) return null;
        $content = file_get_contents($path);
        return $content !== false ? $content : null;
    }

    private function writeCache(string $key, array $data): void
    {
        file_put_contents(
            $this->cacheDir . '/' . $key . '.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function writeCacheString(string $key, string $content): void
    {
        file_put_contents($this->cacheDir . '/' . $key . '.md', $content);
    }
}
