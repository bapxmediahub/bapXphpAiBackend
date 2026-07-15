<?php
namespace App\Services;

final class MediaService {
    private array $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];
    private string $storageDir;

    public function __construct(
        private ImageOptimizerService $optimizer = new ImageOptimizerService()
    ) {
        $this->storageDir = storage_path('media');
    }

    public function all(?string $context = null): array {
        if ($context) return $this->readWithAliases($context);
        $all = [];
        foreach (['shared','products','temples','astrologers'] as $ctx) {
            $all = array_merge($all, $this->readWithAliases($ctx));
        }
        usort($all, fn($a,$b) => strcmp((string)($b['created_at']??''), (string)($a['created_at']??'')));
        return $all;
    }

    public function upload(array $files, string $context = 'shared', ?string $description = null, array $usedIn = []): array {
        if (empty($files['name']) || !is_array($files['name'])) return [];
        $folder = 'assets/images/media/' . date('Y/m');
        $dir = app_path($folder);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $uploaded = [];
        $records = $this->readYaml($context);
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
            if (!move_uploaded_file($tmp, $destPath)) continue;
            $webpPath = $this->optimizer->optimize($destPath, $dir, ['max_width' => 1920, 'max_height' => 1920, 'quality' => 80]);
            $url = '/' . $folder . '/';
            if ($webpPath && is_file($webpPath)) {
                if ($webpPath !== $destPath) unlink($destPath);
                $webpFilename = basename($webpPath);
                $url .= $webpFilename;
                $record = [
                    'id' => bin2hex(random_bytes(8)),
                    'filename' => $webpFilename,
                    'original_name' => (string)$original,
                    'url' => $url,
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
        if ($uploaded) $this->writeYaml($context, $records);
        return $uploaded;
    }

    public function delete(string $id, ?string $context = null): void {
        $contexts = $context ? [$context] : ['shared','products','temples','astrologers'];
        foreach ($contexts as $ctx) {
            $filePath = '';
            $records = array_values(array_filter($this->readYaml($ctx), function($r) use ($id, &$filePath) {
                if (($r['id'] ?? '') === $id) { $filePath = $r['url'] ?? ''; return false; }
                return true;
            }));
            if ($filePath !== '') {
                $fullPath = app_path(ltrim($filePath, '/'));
                if (is_file($fullPath)) @unlink($fullPath);
                $this->writeYaml($ctx, $records);
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
            $records = $this->readYaml($ctx);
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
            if ($changed) $this->writeYaml($ctx, $records);
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
        $records = $this->readYaml($context);
        foreach ($records as &$r) {
            if (!isset($r['path']) && isset($r['url'])) {
                $r['path'] = $r['url'];
            }
        }
        return $records;
    }

    private function writeYaml(string $context, array $records): void {
        if (!is_dir($this->storageDir)) mkdir($this->storageDir, 0775, true);
        $lines = ["media:"];
        foreach ($records as $r) {
            $lines[] = "  - id: " . ($r['id'] ?? '');
            $lines[] = "    filename: " . ($r['filename'] ?? '');
            $lines[] = "    original_name: " . ($r['original_name'] ?? '');
            $lines[] = "    url: " . ($r['url'] ?? '');
            $lines[] = "    description: " . $this->escapeYaml($r['description'] ?? '');
            $lines[] = "    context: " . ($r['context'] ?? 'shared');
            $lines[] = "    mime: " . ($r['mime'] ?? '');
            if (!empty($r['used_in'])) {
                $lines[] = "    used_in:";
                foreach ($r['used_in'] as $u) {
                    $lines[] = "      - type: " . ($u['type'] ?? '');
                    $lines[] = "        id: " . ($u['id'] ?? '');
                    $lines[] = "        name: " . $this->escapeYaml($u['name'] ?? '');
                }
            }
            $lines[] = "    created_at: " . ($r['created_at'] ?? '');
        }
        $lines[] = '';
        file_put_contents($this->filePath($context), implode("\n", $lines), LOCK_EX);
    }

    private function escapeYaml(string $value): string {
        if ($value === '' || preg_match('/[:\{\}\[\],&\*#\?\|\-<>=!%@`\n"\']/', $value)) {
            return '"' . str_replace(['"', "\n"], ['\"', '\n'], $value) . '"';
        }
        return $value;
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
        foreach (['products', 'temples', 'astrologers'] as $context) {
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
