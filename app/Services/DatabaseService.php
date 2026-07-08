<?php
namespace App\Services;
final class DatabaseService {
    private ?\PDO $pdo = null;
    public function __construct() {}
    private function db(): \PDO {
        if ($this->pdo === null) {
            $cfg = require app_path('config/database.php');
            $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4';
            $this->pdo = new \PDO($dsn, $cfg['user'], $cfg['pass'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3, \PDO::MYSQL_ATTR_CONNECT_TIMEOUT => 3]);
            if (!$this->pdo) throw new \RuntimeException('Cannot connect to MySQL. Check config/database.php or env vars.');
        }
        return $this->pdo;
    }
    public function read(string $table): array {
        $stmt = $this->db()->query('SELECT * FROM ' . preg_replace('/[^a-z_]/', '', $table));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => array_merge(json_decode($r['_data'] ?? '{}', true) ?: [], ['id' => $r['id']]), $rows);
    }
    public function write(string $table, array $records): void {
        $this->db()->beginTransaction();
        try {
            $clean = preg_replace('/[^a-z_]/', '', $table);
            $this->db()->exec("TRUNCATE TABLE {$clean}");
            $stmt = $this->db()->prepare("INSERT INTO {$clean} (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
            foreach ($records as $rec) {
                $id = $rec['id'] ?? bin2hex(random_bytes(8));
                $owner = $rec['customer_email'] ?? $rec['email'] ?? $rec['user_id'] ?? null;
                $status = $rec['status'] ?? null;
                $created = $rec['created_at'] ?? date('Y-m-d H:i:s');
                $stmt->execute([$id, json_encode($rec), $owner, $status, $created]);
            }
            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }
    public function upsert(string $table, array $record, string $key = 'id'): array {
        $clean = preg_replace('/[^a-z_]/', '', $table);
        $id = $record[$key] ?? bin2hex(random_bytes(8));
        $existing = $this->find($table, $id, $key);
        if ($existing) {
            $merged = array_merge($existing, $record);
            $owner = $merged['customer_email'] ?? $merged['email'] ?? $merged['user_id'] ?? null;
            $status = $merged['status'] ?? null;
            $stmt = $this->db()->prepare("UPDATE {$clean} SET _data = ?, _owner = ?, _status = ?, _updated_at = NOW() WHERE id = ?");
            $stmt->execute([json_encode($merged), $owner, $status, $id]);
        } else {
            $owner = $record['customer_email'] ?? $record['email'] ?? $record['user_id'] ?? null;
            $status = $record['status'] ?? null;
            $created = $record['created_at'] ?? date('Y-m-d H:i:s');
            $stmt = $this->db()->prepare("INSERT INTO {$clean} (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$id, json_encode($record), $owner, $status, $created]);
        }
        return $record;
    }
    public function delete(string $table, string $value, string $key = 'id'): void {
        $clean = preg_replace('/[^a-z_]/', '', $table);
        if ($key === 'id') {
            $stmt = $this->db()->prepare("DELETE FROM {$clean} WHERE id = ?");
            $stmt->execute([$value]);
        } else {
            $rows = $this->read($table);
            $ids = array_map(fn($r) => $r['id'] ?? null, array_filter($rows, fn($r) => (string)($r[$key] ?? '') === $value));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->db()->prepare("DELETE FROM {$clean} WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
            }
        }
    }
    public function find(string $table, string $value, string $key = 'id'): ?array {
        $clean = preg_replace('/[^a-z_]/', '', $table);
        if ($key === 'id') {
            $stmt = $this->db()->prepare("SELECT * FROM {$clean} WHERE id = ?");
            $stmt->execute([$value]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } else {
            foreach ($this->read($table) as $r) {
                if ((string)($r[$key] ?? '') === $value) return $r;
            }
            return null;
        }
        return $row ? array_merge(json_decode($row['_data'] ?? '{}', true) ?: [], ['id' => $row['id']]) : null;
    }
    public function query(string $sql, array $params = []): array {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function connection(): \PDO { return $this->db(); }
}
