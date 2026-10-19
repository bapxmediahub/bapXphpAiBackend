<?php
namespace App\Services;

final class MediaService {
    private array $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];
    private string $storageDir;

    public function __construct(
        private ImageOptimizerService $optimizer = new ImageOptimizerService(),
        private DatabaseService $store = new DatabaseService()
    ) {
        $this->storageDir = storage_path('media');
    }

    /** Contexts the library knows about. 'blog' was missing, so blog uploads vanished. */
    private const CONTEXTS = ['shared', 'products', 'temples', 'blog'];

    /** Asset folders scanned so images shipped with the site appear alongside uploads. */
    private const ASSET_DIRS = [
        'products'    => 'assets/images/products',
        'temples'     => 'assets/images/temples',
        'blog'        => 'assets/images/blog',
        'shared'      => 'assets/images/media',
    ];

    /** Domain-local upload storage lives beside, rather than inside, the code
     * checkout. Deploying a fresh public_html cannot replace these image files. */
    public static function persistentDirectory(): string {
        return dirname(__DIR__, 3) . '/.sps-media';
    }

    public static function storedPathForUrl(string $url): ?string {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        if (!preg_match('#^/assets/images/media/([0-9]{4}/[0-9]{2}/[a-z0-9][a-z0-9._-]*\.(?:png|jpe?g|webp|gif))$#i', $path, $match)) return null;
        if (str_contains($match[1], '..')) return null;
        $persistent = self::persistentDirectory() . '/' . $match[1];
        if (is_file($persistent) && is_readable($persistent)) return $persistent;
        $legacy = dirname(__DIR__, 2) . $path;
        return is_file($legacy) && is_readable($legacy) ? $legacy : null;
    }

    /** Inspect the actual hosted files behind product records; URLs alone do not
     * prove an upload survived. Only administrators receive this inventory. */
    public function productImageHealth(array $products): array {
        $result = [];
        foreach ($products as $product) {
            $gallery = $product['image_urls'] ?? [];
            if (!is_array($gallery)) $gallery = preg_split('/[\r\n,]+/', (string)$gallery) ?: [];
            $urls = array_unique(array_filter(array_merge([(string)($product['image_url'] ?? '')], $gallery)));
            foreach ($urls as $url) {
                $path = (string)(parse_url((string)$url, PHP_URL_PATH) ?? '');
                if (!str_starts_with($path, '/assets/images/') || str_contains($path, '..')) continue;
                $file = self::storedPathForUrl($path) ?? app_path(ltrim($path, '/'));
                $readable = is_file($file) && is_readable($file) && @getimagesize($file) !== false;
                if ($readable) continue;
                $originals = [];
                $base = preg_replace('/\.(webp|png|jpe?g|gif)$/i', '', $file);
                foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $extension) {
                    $candidate = $base . '.' . $extension;
                    if (is_file($candidate) && @getimagesize($candidate)) {
                        $originals[] = '/' . ltrim(substr($candidate, strlen(app_path())), '/');
                    }
                }
                // Some older uploads used storage/media instead of the public
                // asset folder. Check exact basenames there without exposing files.
                $storageMatches = [];
                $storage = storage_path();
                if (is_dir($storage)) {
                    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storage, \FilesystemIterator::SKIP_DOTS));
                    $sourceName = preg_replace('/-[a-f0-9]{8}$/i', '', pathinfo($path, PATHINFO_FILENAME));
                    foreach ($iterator as $entry) {
                        if (!$entry->isFile() || !in_array(strtolower($entry->getExtension()), ['png','jpg','jpeg','webp','gif'], true)) continue;
                        $name = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($entry->getFilename(), PATHINFO_FILENAME))), '-');
                        if (($name === pathinfo($path, PATHINFO_FILENAME) || $name === $sourceName)
                            && @getimagesize($entry->getPathname())) {
                            $storageMatches[] = substr($entry->getPathname(), strlen(app_path()) + 1);
                        }
                    }
                }
                $result[] = ['product' => (string)($product['name'] ?? ''), 'url' => $path,
                    'status' => is_file($file) ? 'File exists but is not a readable image' : 'File missing',
                    'originals' => array_values(array_unique($originals)), 'storage_matches' => $storageMatches];
            }
        }
        return $result;
    }

    public function all(?string $context = null): array {
        if ($context !== null && !in_array($context, self::CONTEXTS, true)) return [];
        $contexts = $context ? [$context] : self::CONTEXTS;
        $all = [];
        foreach ($contexts as $ctx) {
            $all = array_merge($all, $this->readWithAliases($ctx));
        }
        // The catalogue only records files uploaded through the admin, so product and
        // temple artwork that ships with the site never appeared in the library and
        // could not be picked when editing a post. Scan the folders too and merge on
        // path, with catalogue entries winning so descriptions and usage survive.
        $byPath = [];
        foreach ($all as $item) {
            $key = (string)($item['url'] ?? $item['path'] ?? '');
            // Old metadata may use a shared context for retired profile portraits.
            if ($key !== '' && !str_starts_with((string)parse_url($key, PHP_URL_PATH), '/assets/images/astrologers/')) $byPath[$key] = $item;
        }
        foreach ($this->scanAssetFiles($context) as $file) {
            if (!isset($byPath[$file['url']])) $byPath[$file['url']] = $file;
        }
        $merged = array_values($byPath);
        usort($merged, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return $merged;
    }

    /** Image files present on disk, as library entries. */
    private function scanAssetFiles(?string $context = null): array {
        $dirs = $context ? array_intersect_key(self::ASSET_DIRS, [$context => true]) : self::ASSET_DIRS;
        $out = [];
        foreach ($dirs as $ctx => $relative) {
            $dir = app_path($relative);
            if (!is_dir($dir)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            $paths = [];
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                if (!in_array(strtolower($file->getExtension()), ['jpg','jpeg','png','webp','gif','svg'], true)) continue;
                $paths[] = $file->getPathname();
            }
            sort($paths, SORT_STRING);   // stable order across platforms
            foreach ($paths as $path) {
                $url = '/' . ltrim(str_replace(app_path(), '', $path), '/');
                $out[] = [
                    'id'          => 'file:' . $url,
                    'url'         => $url,
                    'path'        => $url,
                    'filename'    => basename($path),
                    'context'     => $ctx,
                    'description' => '',
                    'size'        => @filesize($path) ?: 0,
                    'created_at'  => date('c', @filemtime($path) ?: time()),
                    'source'      => 'file',
                ];
            }
        }
        return $out;
    }

    public function upload(array $files, string $context = 'shared', ?string $description = null, array $usedIn = []): array {
        if (empty($files['name']) || !is_array($files['name'])) return [];
        // Validate the complete selection before moving any files. A rejected file
        // must never be reported as a successful upload or a successful product save.
        foreach ($files['name'] as $i => $original) {
            $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            $name = basename((string)$original);
            if ($error !== UPLOAD_ERR_OK) {
                $reason = match ($error) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'exceeds the hosting upload size limit. Choose a smaller image.',
                    UPLOAD_ERR_PARTIAL => 'was only partly uploaded. Select it again and retry.',
                    default => 'could not be received. Retry or ask hosting support to check upload storage.',
                };
                throw new \InvalidArgumentException($name . ' ' . $reason);
            }
            $ext = strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
            $tmp = (string)($files['tmp_name'][$i] ?? '');
            $mime = is_file($tmp) ? (mime_content_type($tmp) ?: '') : '';
            if (!isset($this->allowed[$ext]) || $mime !== $this->allowed[$ext] || !@getimagesize($tmp)) {
                throw new \InvalidArgumentException($name . ': choose a valid JPG, PNG, WebP or GIF image.');
            }
        }
        if (!in_array($context, self::CONTEXTS, true)) $context = 'shared';
        $folder = 'assets/images/media/' . date('Y/m');
        $dir = self::persistentDirectory() . '/' . date('Y/m');
        if ((!is_dir($dir) && !@mkdir($dir, 0775, true)) || !is_writable($dir)) {
            throw new \InvalidArgumentException('Image storage is not writable. Ask hosting support to restore upload-folder permissions, then retry.');
        }
        $uploaded = [];
        $records = $this->readCatalogue($context);
        foreach ($files['name'] as $i => $original) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
            if (!isset($this->allowed[$ext])) continue;
            $tmp = (string)($files['tmp_name'][$i] ?? '');
            $mime = is_file($tmp) ? (mime_content_type($tmp) ?: '') : '';
            if ($mime !== '' && $mime !== $this->allowed[$ext]) continue;
            $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', pathinfo((string)$original, PATHINFO_FILENAME)), '-')) ?: 'media';
            $filename = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $dir . '/' . $filename;
            if (!move_uploaded_file($tmp, $destPath)) {
                throw new \InvalidArgumentException(basename((string)$original) . ' could not be saved. Check hosting storage space and retry.');
            }
            // Keep the uploaded original as a recovery source. Optimisation is
            // optional; an unavailable encoder must not lose a valid product photo.
            try { $webpPath = $this->optimizer->optimize($destPath, $dir, ['max_width' => 1920, 'max_height' => 1920, 'quality' => 80, 'preserve_source' => true]); }
            catch (\Throwable) { $webpPath = null; }
            $url = '/' . $folder . '/';
            if ($webpPath && is_file($webpPath) && filesize($webpPath) > 0 && @getimagesize($webpPath)) {
                $webpFilename = basename($webpPath);
                $url .= $webpFilename;
                $record = [
                    'id' => bin2hex(random_bytes(8)),
                    'filename' => $webpFilename,
                    'original_name' => (string)$original,
                    'url' => $url,
                    'original_url' => '/' . $folder . '/' . $filename,
                    'description' => $description ?? '',
                    'context' => $context,
                    'mime' => 'image/webp',
                    'used_in' => $usedIn,
                    'created_at' => date('c'),
                ];
            } else {
                $url .= $filename;
                $record = [
                    'id' => bin2hex(random_bytes(8)),
                    'filename' => $filename,
                    'original_name' => (string)$original,
                    'url' => $url,
                    'original_url' => $url,
                    'description' => $description ?? '',
                    'context' => $context,
                    'mime' => $this->allowed[$ext],
                    'used_in' => $usedIn,
                    'created_at' => date('c'),
                ];
            }
            $records[] = $record;
            $uploaded[] = $record;
        }
        if ($uploaded) $this->writeCatalogue($context, $records);
        return $uploaded;
    }

    public function delete(string $id, ?string $context = null): void {
        $contexts = $context ? [$context] : self::CONTEXTS;
        foreach ($contexts as $ctx) {
            $filePath = '';
            $records = array_values(array_filter($this->readCatalogue($ctx), function($r) use ($id, &$filePath) {
                if (($r['id'] ?? '') === $id) { $filePath = $r['url'] ?? ''; return false; }
                return true;
            }));
            if ($filePath !== '') {
                $fullPath = self::storedPathForUrl($filePath) ?? app_path(ltrim($filePath, '/'));
                if (is_file($fullPath)) @unlink($fullPath);
                $this->store->delete('media_files', $id);
                return;
            }
        }
    }

    public function recordUsage(array $mediaRecords, string $entityType, string $entityId, string $entityName): void {
        $entry = ['type' => $entityType, 'id' => $entityId, 'name' => $entityName];
        $grouped = [];
        foreach ($mediaRecords as $m) {
            $ctx = $m['context'] ?? 'shared';
            $grouped[$ctx][] = $m['id'] ?? '';
        }
        foreach ($grouped as $ctx => $ids) {
            $records = $this->readCatalogue($ctx);
            $changed = false;
            foreach ($records as &$r) {
                if (in_array($r['id'] ?? '', $ids, true)) {
                    $used = $r['used_in'] ?? [];
                    $exists = false;
                    foreach ($used as $u) {
                        if (($u['type'] ?? '') === $entityType && ($u['id'] ?? '') === $entityId) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $used[] = $entry;
                        $r['used_in'] = $used;
                        $changed = true;
                    }
                }
            }
            if ($changed) $this->writeCatalogue($ctx, $records);
        }
    }

    private function filePath(string $context): string {
        return $this->storageDir . '/' . $context . '.yaml';
    }

    private function readYaml(string $context): array {
        $file = $this->filePath($context);
        if (is_file($file)) {
            $yaml = @file_get_contents($file);
            if ($yaml === false || $yaml === '') return [];
            $items = $this->parseYamlItems($yaml);
            return $items;
        }
        return [];
    }

    private function readWithAliases(string $context): array {
        $records = $this->readCatalogue($context);
        foreach ($records as &$r) {
            if (!isset($r['path']) && isset($r['url'])) {
                $r['path'] = $r['url'];
            }
        }
        return $records;
    }

    private function readCatalogue(string $context): array {
        $records = [];
        // Read legacy YAML only for compatibility; new and edited metadata live
        // solely in hosted MySQL, with existing database records taking priority.
        foreach (array_merge($this->readYaml($context), $this->store->read('media_files')) as $record) {
            if (($record['context'] ?? 'shared') !== $context) continue;
            $key = (string)($record['id'] ?? $record['url'] ?? '');
            if ($key !== '') $records[$key] = array_merge($records[$key] ?? [], $record);
        }
        return array_values($records);
    }

    private function writeCatalogue(string $context, array $records): void {
        try {
            foreach ($records as $record) {
                $record['context'] = $context;
                $this->store->upsert('media_files', $record);
            }
        } catch (\Throwable $error) {
            error_log('Media catalogue save failed: ' . $error->getMessage());
            throw new \InvalidArgumentException('The media library could not save this upload. Retry after checking the database connection.');
        }
    }

    private function parseYamlItems(string $yaml): array {
        $items = [];
        $current = null;
        $usedInStack = null;
        $inUsedIn = false;
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^\s*-\s*id:\s*(.+)$/', $line, $m)) {
                if ($current) $items[] = $current;
                $current = ['id' => trim($m[1])];
                $inUsedIn = false;
            } elseif ($current && preg_match('/^\s+(filename|original_name|url|path|description|context|mime|created_at):\s*(.*)$/', $line, $m)) {
                if ($inUsedIn && $m[1] !== 'type' && $m[1] !== 'id' && $m[1] !== 'name') {
                    $inUsedIn = false;
                }
                if (!$inUsedIn) {
                    $current[$m[1]] = $this->unescapeYaml(trim($m[2]));
                }
            } elseif ($current && preg_match('/^\s+used_in:/', $line)) {
                $current['used_in'] = [];
                $inUsedIn = true;
            } elseif ($current && $inUsedIn && preg_match('/^\s+-\s*type:\s*(.+)$/', $line, $m)) {
                $usedInStack = ['type' => trim($m[1])];
            } elseif ($current && $inUsedIn && $usedInStack && preg_match('/^\s+id:\s*(.+)$/', $line, $m)) {
                $usedInStack['id'] = trim($m[1]);
            } elseif ($current && $inUsedIn && $usedInStack && preg_match('/^\s+name:\s*(.*)$/', $line, $m)) {
                $usedInStack['name'] = $this->unescapeYaml(trim($m[2]));
                $current['used_in'][] = $usedInStack;
                $usedInStack = null;
            }
        }
        if ($current) $items[] = $current;
        if (empty($items)) {
            $items = $this->parseLegacyYaml($yaml);
        }
        return $items;
    }

    private function parseLegacyYaml(string $yaml): array {
        // Fallback parser for old flat format
        $lines = explode("\n", $yaml);
        $items = [];
        $current = null;
        $i = 0;
        foreach ($lines as $line) {
            if (preg_match('/^\s*-\s*id:\s*(.+)$/', $line, $m)) {
                if ($current) $items[] = $current;
                $current = ['id' => trim($m[1])];
            } elseif ($current && preg_match('/^\s+(filename|original_name|path|context|mime|created_at):\s*(.*)$/', $line, $m)) {
                $current[$m[1]] = $this->unescapeYaml(trim($m[2]));
            }
        }
        if ($current) $items[] = $current;
        return $items;
    }

    private function unescapeYaml(string $value): string {
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value)-1] === '"') {
            return str_replace(['\"', '\n'], ['"', "\n"], substr($value, 1, -1));
        }
        return $value;
    }

    private function scanExistingAssets(): array {
        $base = app_path('assets/images');
        $items = [];
        foreach (['products', 'temples'] as $context) {
            $dir = $base . '/' . $context;
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '/*.{jpg,jpeg,png,webp,gif,svg}', GLOB_BRACE) ?: [] as $file) {
                $relPath = str_replace(app_path(), '', $file);
                $items[] = [
                    'id' => 'asset-' . md5($file),
                    'filename' => basename($file),
                    'original_name' => basename($file),
                    'url' => $relPath,
                    'path' => $relPath,
                    'description' => '',
                    'context' => $context,
                    'mime' => mime_content_type($file) ?: 'image/*',
                    'used_in' => [],
                    'created_at' => date('c', filemtime($file) ?: time()),
                ];
            }
        }
        return $items;
    }

    public static function migrateFromLegacy(): int {
        $oldFile = storage_path('media.yaml');
        if (!is_file($oldFile)) return 0;
        $yaml = @file_get_contents($oldFile);
        if ($yaml === false || $yaml === '') return 0;
        $service = new self();
        $items = $service->parseLegacyYaml($yaml);
        $grouped = [];
        foreach ($items as $item) {
            $ctx = $item['context'] ?? 'shared';
            if (!isset($item['url']) && isset($item['path'])) {
                $item['url'] = $item['path'];
            }
            $item['description'] = $item['description'] ?? '';
            $item['used_in'] = $item['used_in'] ?? [];
            $grouped[$ctx][] = $item;
        }
        foreach ($grouped as $ctx => $records) {
            $service->writeYaml($ctx, $records);
        }
        @rename($oldFile, $oldFile . '.bak');
        return count($items);
    }
}
