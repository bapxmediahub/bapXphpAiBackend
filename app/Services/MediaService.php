<?php
namespace App\Services;

final class MediaService {
    private array $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];

    public function __construct(private JsonStoreService $store = new JsonStoreService()) {}

    public function all(?string $context = null): array {
        $records = $this->store->read('media_files');
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
        $records = $this->store->read('media_files');
        foreach ($files['name'] as $i => $original) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
            if (!isset($this->allowed[$ext])) continue;
            $tmp = (string)($files['tmp_name'][$i] ?? '');
            $mime = is_file($tmp) ? (mime_content_type($tmp) ?: '') : '';
            if ($mime !== '' && $mime !== $this->allowed[$ext]) continue;
            $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', pathinfo((string)$original, PATHINFO_FILENAME)), '-')) ?: 'media';
            $filename = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($tmp, $dir . '/' . $filename)) continue;
            $record = [
                'id' => bin2hex(random_bytes(8)),
                'filename' => $filename,
                'original_name' => (string)$original,
                'path' => '/' . $folder . '/' . $filename,
                'context' => $context,
                'mime' => $this->allowed[$ext],
                'created_at' => date('c'),
            ];
            $records[] = $record;
            $uploaded[] = $record;
        }
        if ($uploaded) $this->store->write('media_files', $records);
        return $uploaded;
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
