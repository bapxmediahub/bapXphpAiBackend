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
        return $this->catalogue($context);
    }

    private function catalogue(?string $context, bool $filterGlobal = false): array {
        if ($context !== null && !in_array($context, self::CONTEXTS, true)) return [];
        $contexts = $context ? [$context] : self::CONTEXTS;
        // Merge in the same context order as legacy catalogues. Read database
        // records once, in bounded batches, including on the remote DB bridge.
        $catalogues = [];
        foreach ($contexts as $ctx) {
            $catalogues[$ctx] = [];
            foreach ($this->readYaml($ctx) as $record) {
                if (($record['context'] ?? 'shared') !== $ctx) continue;
                $key = (string)($record['id'] ?? $record['url'] ?? '');
                if ($key !== '') $catalogues[$ctx][$key] = ['source' => 'legacy'] + $record;
            }
        }
        $afterId = null;
        do {
            $filters = [];
            $params = [];
            if ($context !== null) {
                $filters[] = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.context')), 'null'), 'shared') = ?";
                $params[] = $context;
            }
            if ($afterId !== null) { $filters[] = 'id > ?'; $params[] = $afterId; }
            $where = $filters ? ' WHERE '.implode(' AND ', $filters) : '';
            $rows = $this->store->query("SELECT id, _data FROM media_files$where ORDER BY id LIMIT 250", $params);
            foreach ($rows as $row) {
                $id = (string)$row['id'];
                $record = ['id' => $id] + json_decode($row['_data'], true, flags: JSON_THROW_ON_ERROR);
                $ctx = $record['context'] ?? 'shared';
                if (isset($catalogues[$ctx])) $catalogues[$ctx][$id] = array_merge($catalogues[$ctx][$id] ?? [], $record);
                $afterId = $id;
            }
        } while (count($rows) === 250);
        $all = array_merge(...array_map('array_values', array_values($catalogues)));
        // The catalogue only records files uploaded through the admin, so product and
        // temple artwork that ships with the site never appeared in the library and
        // could not be picked when editing a post. Scan the folders too and merge on
        // path, with catalogue entries winning so descriptions and usage survive.
        $byPath = [];
        foreach ($all as $item) {
            $item['path'] ??= $item['url'] ?? '';
            $key = (string)($item['url'] ?? $item['path']);
            // Old metadata may use a shared context for retired profile portraits.
            if ($key !== '' && !str_starts_with((string)parse_url($key, PHP_URL_PATH), '/assets/images/astrologers/')) $byPath[$key] = $item;
        }
        $cataloguedPaths = $filterGlobal && $context !== null ? array_fill_keys(array_keys($byPath), true) : [];
        foreach ($this->scanAssetFiles($context) as $file) {
            if (!isset($byPath[$file['url']])) $byPath[$file['url']] = $file;
        }
        if ($filterGlobal && $context !== null && $byPath !== []) {
            $this->excludeForeignOwners($byPath, $cataloguedPaths, $context);
        }
        $merged = array_values($byPath);
        usort($merged, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return $merged;
    }

    /** A bundled image can be owned by an upload in another context. Only fetch
     * competing paths and database overrides for legacy rows claiming those paths. */
    private function excludeForeignOwners(array &$items, array $cataloguedPaths, string $context): void {
        $priority = array_flip(self::CONTEXTS);
        $legacy = [];
        $legacyAtPath = [];
        foreach (self::CONTEXTS as $other) {
            if ($other === $context) continue;
            foreach ($this->readYaml($other) as $row) {
                if (($row['context'] ?? 'shared') !== $other) continue;
                $path = $row['url'] ?? $row['path'] ?? '';
                $id = $row['id'] ?? $row['url'] ?? '';
                if ($id === '') continue;
                $legacy[$other][$id] = $row;
                if (isset($items[$path])) $legacyAtPath[$path][$other][] = $id;
            }
        }

        foreach (array_chunk(array_keys($items), 200) as $paths) {
            $competitors = [];
            $legacyIds = [];
            foreach ($paths as $path) {
                foreach ($legacyAtPath[$path] ?? [] as $other => $ids) {
                    foreach ($ids as $id) {
                        $competitors[$other][$id] = $legacy[$other][$id];
                        $legacyIds[] = $id;
                    }
                }
            }
            $placeholders = implode(',', array_fill(0, count($paths), '?'));
            $overlap = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.url')), 'null'), JSON_UNQUOTE(JSON_EXTRACT(_data, '$.path'))) IN ($placeholders)";
            if ($legacyIds !== []) $overlap .= ' OR id IN ('.implode(',', array_fill(0, count($legacyIds), '?')).')';
            $where = " WHERE COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.context')), 'null'), 'shared') <> ? AND ($overlap)";
            $params = [$context, ...$paths, ...$legacyIds];
            $after = null;
            do {
                $cursor = $after === null ? '' : ' AND id > ?';
                $rows = $this->store->query("SELECT id, _data FROM media_files$where$cursor ORDER BY id LIMIT 250",
                    $after === null ? $params : [...$params, $after]);
                foreach ($rows as $record) {
                    $after = (string)$record['id'];
                    $row = json_decode($record['_data'], true, flags: JSON_THROW_ON_ERROR);
                    $other = $row['context'] ?? 'shared';
                    if (isset($priority[$other])) $competitors[$other][$after] = $row + ($legacy[$other][$after] ?? []);
                }
            } while (count($rows) === 250);
            foreach ($competitors as $other => $rows) {
                foreach ($rows as $row) {
                    $path = $row['url'] ?? $row['path'] ?? '';
                    if (!isset($cataloguedPaths[$path]) || $priority[$other] > $priority[$context]) unset($items[$path]);
                }
            }
        }
    }

    public function adminPage(array $query): array {
        $options = ResourceService::listOptions($query);
        $context = in_array($query['context'] ?? null, self::CONTEXTS, true) ? $query['context'] : '';
        $matches = $this->catalogue($context === '' ? null : $context, true);
        if ($options['search'] !== '') {
            $matches = array_values(array_filter($matches, static function (array $item) use ($options): bool {
                foreach (['original_name','filename','description','alt_text','caption','url','path'] as $key) {
                    if (mb_stripos((string)($item[$key] ?? ''), $options['search']) !== false) return true;
                }
                return false;
            }));
        }
        if ($options['sort'] === 'oldest') $matches = array_reverse($matches);
        $paging = ResourceService::pageBounds($options, count($matches)) + ['context' => $context];
        $items = array_slice($matches, $paging['offset'], $paging['pageSize']);
        return compact('paging', 'items');
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
            $uploaded[] = $record;
        }
        if ($uploaded) $this->writeCatalogue($context, $uploaded);
        return $uploaded;
    }

    /** Metadata is editable only for actual uploaded records, not bundled or legacy assets. */
    public static function metadataRevision(array $record): string {
        $values = [];
        foreach (['id','url','context','description','alt_text','caption'] as $field) {
            $values[$field] = (string)($record[$field] ?? '');
        }
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public static function metadataPatch(array $record, array $fields, string $expected): array {
        if (!self::canDeleteRecord($record) || !in_array($record['context'] ?? '', self::CONTEXTS, true)) {
            throw new \InvalidArgumentException('Only uploaded library images can be edited.');
        }
        if (!hash_equals(self::metadataRevision($record), $expected)) {
            throw new \InvalidArgumentException('Media metadata changed; reload before saving.');
        }
        $patch = [];
        foreach (['description'=>255, 'alt_text'=>255, 'caption'=>500] as $field=>$limit) {
            $value = $fields[$field] ?? null;
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen(trim($value)) > $limit
                || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                throw new \InvalidArgumentException('Enter valid plain text within the displayed limits.');
            }
            $patch[$field] = trim($value);
        }
        return $patch;
    }

    public function saveMetadata(string $id, array $fields, string $expected): void {
        $this->store->updateMediaMetadata($id, $fields, $expected);
    }

    /** Only records uploaded through the media library may be removed. */
    public static function canDeleteRecord(array $record): bool {
        $id = (string)($record['id'] ?? '');
        $url = (string)($record['url'] ?? '');
        return !in_array(($record['source'] ?? ''), ['file', 'legacy'], true)
            && (bool)preg_match('/^[a-f0-9]{16}$/D', $id)
            && (bool)preg_match(
                '#^/assets/images/media/\d{4}/\d{2}/[a-z0-9][a-z0-9._-]*\.(?:png|jpe?g|webp|gif)$#i',
                (string)(parse_url($url, PHP_URL_PATH) ?? '')
            );
    }

    /** Identify links before a destructive operation; stale used_in is insufficient. */
    public static function referencesToUrl(string $url, array $recordsByType): array {
        $references = [];
        foreach ($recordsByType as $type => $records) {
            foreach ($records as $record) {
                if (!is_array($record)) continue;
                $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($json === false || !str_contains($json, $url)) continue;
                $label = trim((string)($record['name'] ?? $record['title'] ?? $record['slug'] ?? $record['id'] ?? 'Record'));
                $references[] = (string)$type . ': ' . mb_substr($label, 0, 90);
            }
        }
        return array_values(array_unique($references));
    }

    /** Return true only after metadata was removed and the file safely isolated. */
    public function delete(string $id, ?string $context = null): bool {
        if (!preg_match('/^[a-f0-9]{16}$/D', $id)) {
            throw new \InvalidArgumentException('Only uploaded library images can be deleted.');
        }
        $records = $this->store->read('media_files');
        $matches = array_values(array_filter($records, static fn(array $r): bool => ($r['id'] ?? '') === $id));
        $record = $matches[0] ?? null;
        if ($record === null) throw new \InvalidArgumentException('Uploaded media record not found.');
        if (!self::canDeleteRecord($record)) {
            throw new \InvalidArgumentException('Bundled site images cannot be deleted from Media Library.');
        }
        if ($context !== null && ($record['context'] ?? '') !== $context) {
            throw new \InvalidArgumentException('Media context does not match.');
        }
        // An old YAML record would reappear after deleting the MySQL row.
        foreach (self::CONTEXTS as $section) {
            foreach ($this->readYaml($section) as $old) {
                if (($old['id'] ?? '') === $id) {
                    throw new \InvalidArgumentException('Legacy media requires migration before deletion.');
                }
            }
        }
        // recordUsage records the owner at upload time. Editors can replace the image or delete that owner.
        $collections = ['products', 'temples', 'categories', 'settings'];
        $history = $record['used_in'] ?? [];
        if (!is_array($history) || !array_is_list($history)) {
            throw new \InvalidArgumentException('Recorded image usage cannot be verified.');
        }
        foreach ($history as $use) {
            if (!is_array($use) || !is_string($use['id'] ?? null) || trim($use['id']) === '') {
                throw new \InvalidArgumentException('Recorded image usage cannot be verified.');
            }
            $type = $use['type'] ?? null;
            if ($type === 'astrologers' && !in_array($type, $collections, true)) $collections[] = $type;
            if (!in_array($type, $collections, true)) {
                throw new \InvalidArgumentException('Recorded image usage cannot be verified.');
            }
        }

        $urls = array_values(array_unique(array_filter([
            (string)($record['url'] ?? ''), (string)($record['original_url'] ?? '')
        ])));
        $content = [];
        foreach ($collections as $collection) $content[$collection] = $this->store->read($collection);
        $content['articles'] = (new BlogService())->all(true);
        foreach ($urls as $url) {
            $uses = self::referencesToUrl($url, $content);
            if ($uses !== []) {
                throw new \InvalidArgumentException('This image is still referenced by: ' . implode(', ', array_slice($uses, 0, 3)) . '.');
            }
            foreach ($records as $other) {
                if (($other['id'] ?? '') === $id) continue;
                if (($other['url'] ?? '') === $url || ($other['original_url'] ?? '') === $url) {
                    throw new \InvalidArgumentException('Another media record uses this image.');
                }
            }
        }

        // Quarantine all owned binaries before deleting the MySQL metadata. Restore
        // the files if the database mutation fails. Never remove bundled assets.
        $moved = [];
        try {
            foreach ($urls as $url) {
                if (!preg_match(
                    '#^/assets/images/media/\d{4}/\d{2}/[a-z0-9][a-z0-9._-]*\.(?:png|jpe?g|webp|gif)$#i',
                    (string)(parse_url($url, PHP_URL_PATH) ?? '')
                )) continue;
                $path = self::storedPathForUrl($url);
                if ($path === null) continue; // Remove stale DB metadata safely.
                if (is_link($path)) throw new \RuntimeException('Linked media files cannot be deleted.');
                $real = realpath($path);
                $persistent = realpath(self::persistentDirectory());
                $legacy = realpath(app_path('assets/images/media'));
                $allowed = false;
                foreach ([$persistent, $legacy] as $base) {
                    if ($base !== false && $real !== false && str_starts_with($real, $base . '/')) $allowed = true;
                }
                if (!$allowed) throw new \RuntimeException('Media file is outside allowed upload storage.');
                $trashDir = dirname($real) . '/.trash';
                if (!is_dir($trashDir) && !@mkdir($trashDir, 0700, true) && !is_dir($trashDir)) {
                    throw new \RuntimeException('Media quarantine is not writable.');
                }
                $trash = $trashDir . '/' . $id . '-' . basename($real);
                if (!@rename($real, $trash)) throw new \RuntimeException('Could not isolate media before deletion.');
                $moved[$real] = $trash;
            }
            $this->store->delete('media_files', $id);
        } catch (\Throwable $error) {
            foreach ($moved as $original => $trash) {
                if (is_file($trash)) @rename($trash, $original);
            }
            throw $error;
        }
        foreach ($moved as $trash) {
            if (is_file($trash) && !@unlink($trash)) {
                error_log('Media cleanup pending for deleted record ' . $id);
            }
        }
        return true;
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
            $changed = [];
            foreach ($records as $r) {
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
                        $changed[] = $r;
                    }
                }
            }
            if ($changed) $this->writeCatalogue($ctx, $changed);
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

    private function readCatalogue(string $context): array {
        $records = [];
        // Read legacy YAML only for compatibility; new and edited metadata live
        // solely in hosted MySQL, with existing database records taking priority.
        $legacy = array_map(static function(array $record): array {
            return ['source'=>'legacy'] + $record;
        }, $this->readYaml($context));
        foreach (array_merge($legacy, $this->store->read('media_files')) as $record) {
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

}
