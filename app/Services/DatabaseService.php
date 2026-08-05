<?php
namespace App\Services;
final class DatabaseService {
    private static array $remoteQueryCache = [];
    private ?\PDO $pdo = null;
    private ?bool $remoteOnly = null;
    private array $cfg = [];
    public function __construct(private bool $forceDirect = false) {
        $this->cfg = require app_path('config/database.php');
    }

    private function isTestMode(): bool {
        return getenv('BAPX_TEST_MODE') === '1';
    }

    private function remoteCall(string $sql, array $params = []): array {
        $cacheKey = hash('sha256', (string)$this->cfg['remote_url'] . "\0" . $sql . "\0" . serialize($params));
        if (array_key_exists($cacheKey, self::$remoteQueryCache)) return self::$remoteQueryCache[$cacheKey];

        $payload = json_encode(array_filter(['query' => $sql, 'params' => $params, 'password' => $this->cfg['pass'] ?? '']), JSON_THROW_ON_ERROR);
        $ch = curl_init($this->cfg['remote_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
        ]);
        $body = @curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $transportError = $body === false ? curl_error($ch) : '';
        if ($body === false) throw new \RuntimeException('Remote database transport failed: ' . ($transportError ?: 'unknown cURL error'));
        $result = json_decode((string)$body, true);
        if ($code !== 200) {
            $message = is_array($result) ? trim((string)($result['error'] ?? '')) : '';
            throw new \RuntimeException('Remote database request failed with HTTP ' . $code . ($message !== '' ? ': ' . $message : '.'));
        }
        if (!is_array($result) || empty($result['success']) || !isset($result['data']) || !is_array($result['data'])) {
            throw new \RuntimeException('Remote database returned an invalid response.');
        }
        return self::$remoteQueryCache[$cacheKey] = $result['data'];
    }

    private function remoteMutation(string $action, string $table, array $payload): array {
        $payload['password'] = $this->cfg['pass'] ?? '';
        $body = json_encode(['action' => $action, 'collection' => preg_replace('/[^a-z_]/', '', $table)] + $payload);
        $ch = curl_init($this->cfg['remote_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
        ]);
        $body = @curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result = json_decode((string)$body, true) ?: [];
        if ($body === false || $code < 200 || $code >= 300 || empty($result['success'])) {
            throw new \RuntimeException((string)($result['error'] ?? 'Remote mutation failed.'));
        }
        self::$remoteQueryCache = [];
        return $result;
    }

    private function db(): \PDO {
        if ($this->pdo === null) {
            $this->cfg = require app_path('config/database.php');
            foreach (['host', 'dbname', 'user', 'pass'] as $required) {
                if (trim((string)($this->cfg[$required] ?? '')) === '') {
                    throw new \RuntimeException('Direct MySQL is not configured; missing ' . $required . '.');
                }
            }
            $errno = 0; $errstr = '';
            $fp = @fsockopen($this->cfg['host'], (int)$this->cfg['port'], $errno, $errstr, 3);
            if (!$fp) {
                throw new \RuntimeException("MySQL unavailable at {$this->cfg['host']}:{$this->cfg['port']}");
            }
            fclose($fp);
            $dsn = 'mysql:host=' . $this->cfg['host'] . ';port=' . $this->cfg['port'] . ';dbname=' . $this->cfg['dbname'] . ';charset=utf8mb4';
            $this->pdo = new \PDO($dsn, $this->cfg['user'], $this->cfg['pass'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
            if (!$this->pdo) throw new \RuntimeException('Cannot connect to MySQL.');
        }
        return $this->pdo;
    }

    private function isRemote(): bool {
        if ($this->remoteOnly !== null) return $this->remoteOnly;
        if ($this->forceDirect) return $this->remoteOnly = false;
        if (empty($this->cfg['remote_url'])) { $this->remoteOnly = false; return false; }
        try { $this->db(); $this->remoteOnly = false; return false; }
        catch (\Throwable) { $this->remoteOnly = true; return true; }
    }

    public function read(string $table): array {
        if ($this->isTestMode()) return [];
        if ($this->isRemote()) {
            $rows = $this->remoteCall('SELECT * FROM ' . preg_replace('/[^a-z_]/', '', $table));
            return array_map(fn($r) => array_merge(json_decode($r['_data'] ?? '{}', true) ?: [], ['id' => $r['id']]), $rows);
        }
        $stmt = $this->db()->query('SELECT * FROM ' . preg_replace('/[^a-z_]/', '', $table));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => array_merge(json_decode($r['_data'] ?? '{}', true) ?: [], ['id' => $r['id']]), $rows);
    }
    public function write(string $table, array $records): void {
        if ($this->isTestMode()) return;
        if ($this->isRemote()) { $this->remoteMutation('replace', $table, ['records' => $records]); return; }
        $this->db()->beginTransaction();
        try {
            $clean = preg_replace('/[^a-z_]/', '', $table);
            // DELETE, not TRUNCATE. TRUNCATE is DDL and triggers an implicit COMMIT in
            // MySQL, which ends the transaction opened above — the later commit()/rollBack()
            // then fails with "There is no active transaction" and the whole write is lost.
            $this->db()->exec("DELETE FROM {$clean}");
            $stmt = $this->db()->prepare("INSERT INTO {$clean} (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($records as $rec) {
                $id = $rec['id'] ?? bin2hex(random_bytes(8));
                $owner = $rec['customer_email'] ?? $rec['email'] ?? $rec['user_id'] ?? null;
                $status = $rec['status'] ?? null;
                $created = $rec['created_at'] ?? date('c');
                $updated = $rec['updated_at'] ?? $created;
                $stmt->execute([$id, json_encode($rec), $owner, $status, $created, $updated]);
            }
            $this->db()->commit();
            self::$remoteQueryCache = [];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }
    public function upsert(string $table, array $record, string $key = 'id'): array {
        if ($this->isTestMode()) {
            $record['id'] ??= bin2hex(random_bytes(8));
            return $record;
        }
        if ($this->isRemote()) {
            if ($key !== 'id') {
                $existing = $this->find($table, (string)($record[$key] ?? ''), $key);
                if ($existing) $record['id'] = $existing['id'];
            }
            $record['id'] ??= bin2hex(random_bytes(8));
            return $this->remoteMutation('upsert', $table, ['record' => $record])['record'] ?? $record;
        }
        $clean = preg_replace('/[^a-z_]/', '', $table);
        $id = $record[$key] ?? bin2hex(random_bytes(8));
        $existing = $this->find($table, $id, $key);
        if ($existing) {
            $merged = array_merge($existing, $record);
            $owner = $merged['customer_email'] ?? $merged['email'] ?? $merged['user_id'] ?? null;
            $status = $merged['status'] ?? null;
            $updated = $merged['updated_at'] ?? date('c');
            $stmt = $this->db()->prepare("UPDATE {$clean} SET _data = ?, _owner = ?, _status = ?, _updated_at = ? WHERE id = ?");
            $stmt->execute([json_encode($merged), $owner, $status, $updated, $id]);
        } else {
            $owner = $record['customer_email'] ?? $record['email'] ?? $record['user_id'] ?? null;
            $status = $record['status'] ?? null;
            $created = $record['created_at'] ?? date('c');
            $updated = $record['updated_at'] ?? $created;
            $stmt = $this->db()->prepare("INSERT INTO {$clean} (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, json_encode($record), $owner, $status, $created, $updated]);
        }
        return $record;
    }
    public function delete(string $table, string $value, string $key = 'id'): void {
        if ($this->isTestMode()) return;
        if ($this->isRemote()) {
            $record = $key === 'id' ? ['id' => $value] : $this->find($table, $value, $key);
            if ($record) $this->remoteMutation('delete', $table, ['id' => $record['id']]);
            return;
        }
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
        if ($this->isTestMode()) return null;
        if ($this->isRemote()) {
            if ($key === 'id') {
                $clean = preg_replace('/[^a-z_]/', '', $table);
                $rows = $this->remoteCall("SELECT * FROM {$clean} WHERE id = ?", [$value]);
                if (!empty($rows)) {
                    return array_merge(json_decode($rows[0]['_data'] ?? '{}', true) ?: [], ['id' => $rows[0]['id']]);
                }
                return null;
            }
            foreach ($this->read($table) as $r) {
                if ((string)($r[$key] ?? '') === $value) return $r;
            }
            return null;
        }
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
        if ($this->isTestMode()) return [];
        if ($this->isRemote()) return $this->remoteCall($sql, $params);
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function connection(): \PDO {
        if ($this->isTestMode()) throw new \RuntimeException('Database connection is disabled in test mode.');
        return $this->db();
    }
}
