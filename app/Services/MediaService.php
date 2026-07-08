<?php
namespace App\Services;

final class MediaService {
    private array $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];
    private string $yamlFile;

    public function __construct(
        private ImageOptimizerService $optimizer = new ImageOptimizerService()
    ) {
        $this->yamlFile = storage_path('media.yaml');
    }

    public function all(?string $context = null): array {
        $records = $this->readYaml();
        $paths = array_column($records, 'path');
        foreach ($this->scanExistingAssets() as $asset) {
            if (!in_array($asset['path'], $paths, true)) $records[] = $asset;
        }
        if ($context) {
            $records = array_values(array_filter($records, fn($item) => ($item['context'] ?? '') === $context || ($item['context'] ?? '') === 'shared'));
        }
        usort($records, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return $records;
    }

    public function upload(array $files, string $context = 'shared'): array {
        if (empty($files['name']) || !is_array($files['name'])) return [];
        $folder = 'assets/images/media/' . date('Y/m');
        $dir = app_path($folder);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $uploaded = [];
        $records = $this->readYaml();
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
            if ($webpPath && is_file($webpPath)) {
                if ($webpPath !== $destPath) unlink($destPath);
                $webpFilename = basename($webpPath);
                $record = [
                    'id' => bin2hex(random_bytes(8)),
                    'filename' => $webpFilename,
                    'original_name' => (string)$original,
                    'path' => '/' . $folder . '/' . $webpFilename,
                    'context' => $context,
                    'mime' => 'image/webp',
                    'created_at' => date('c'),
                ];
            } else {
                $record = [
                    'id' => bin2hex(random_bytes(8)),
                    'filename' => $filename,
                    'original_name' => (string)$original,
                    'path' => '/' . $folder . '/' . $filename,
                    'context' => $context,
                    'mime' => $this->allowed[$ext],
                    'created_at' => date('c'),
                ];
            }
            $records[] = $record;
            $uploaded[] = $record;
        }
        if ($uploaded) $this->writeYaml($records);
        return $uploaded;
    }

    public function delete(string $id): void {
        $records = array_values(array_filter($this->readYaml(), fn($r) => ($r['id'] ?? '') !== $id));
        $this->writeYaml($records);
    }

    private function readYaml(): array {
        if (!is_file($this->yamlFile)) return [];
        $yaml = file_get_contents($this->yamlFile);
        $items = [];
        $current = null;
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^\s*-\s*id:\s*(.+)$/', $line, $m)) {
                if ($current) $items[] = $current;
                $current = ['id' => trim($m[1])];
            } elseif ($current && preg_match('/^\s+(filename|original_name|path|context|mime|created_at):\s*(.+)$/', $line, $m)) {
                $current[$m[1]] = trim((string)$m[2]);
            }
        }
        if ($current) $items[] = $current;
        return $items;
    }

    private function writeYaml(array $records): void {
        $yaml = "media:\n";
        foreach ($records as $r) {
            $yaml .= "  - id: " . ($r['id'] ?? '') . "\n";
            $yaml .= "    filename: " . ($r['filename'] ?? '') . "\n";
            $yaml .= "    original_name: " . ($r['original_name'] ?? '') . "\n";
            $yaml .= "    path: " . ($r['path'] ?? '') . "\n";
            $yaml .= "    context: " . ($r['context'] ?? '') . "\n";
            $yaml .= "    mime: " . ($r['mime'] ?? '') . "\n";
            $yaml .= "    created_at: " . ($r['created_at'] ?? '') . "\n";
        }
        file_put_contents($this->yamlFile, $yaml, LOCK_EX);
    }

    private function scanExistingAssets(): array {
        $base = app_path('assets/images');
        $items = [];
        foreach (['products', 'temples', 'astrologers'] as $context) {
            $dir = $base . '/' . $context;
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '/*.{jpg,jpeg,png,webp,gif,svg}', GLOB_BRACE) ?: [] as $file) {
                $items[] = [
                    'id' => 'asset-' . md5($file),
                    'filename' => basename($file),
                    'original_name' => basename($file),
                    'path' => str_replace(app_path(), '', $file),
                    'context' => $context,
                    'mime' => mime_content_type($file) ?: 'image/*',
                    'created_at' => date('c', filemtime($file) ?: time()),
                ];
            }
        }
        return $items;
    }
}
