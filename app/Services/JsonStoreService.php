<?php
namespace App\Services;

final class JsonStoreService {
    public function __construct(private ?string $baseDir = null) {
        $this->baseDir ??= storage_path('data');
        if (!is_dir($this->baseDir)) mkdir($this->baseDir, 0775, true);
    }
    public function read(string $collection): array {
        $file = $this->file($collection);
        if (!is_file($file)) return [];
        $json = file_get_contents($file);
        return $json === false || $json === '' ? [] : (json_decode($json, true) ?: []);
    }
    public function write(string $collection, array $records): void {
        $file = $this->file($collection);
        $lock = fopen($file . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('Unable to lock collection');
        $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($tmp, $file);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    public function upsert(string $collection, array $record, string $key = 'id'): array {
        $records = $this->read($collection);
        $found = false;
        foreach ($records as $i => $existing) {
            if (($existing[$key] ?? null) === ($record[$key] ?? null)) { $records[$i] = $record; $found = true; }
        }
        if (!$found) $records[] = $record;
        $this->write($collection, $records);
        return $record;
    }
    public function delete(string $collection, string $value, string $key = 'id'): void {
        $records = array_values(array_filter($this->read($collection), fn($record) => (string)($record[$key] ?? '') !== $value));
        $this->write($collection, $records);
    }
    private function file(string $collection): string { return rtrim($this->baseDir, '/') . '/' . $collection . '.json'; }
}
