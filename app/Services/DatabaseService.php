<?php
namespace App\Services;
final class DatabaseService {
    private static array $remoteQueryCache = [];
    /**
     * One connection per request, shared by every instance. There are 27 places that
     * construct a DatabaseService, and each used to open its own MySQL connection, so a
     * single page render could open a dozen. On shared hosting that exhausts the
     * per-user connection limit and PDO fails with
     * SQLSTATE[HY000] [2002] Operation not permitted — the real cause of the
     * intermittent 503s. PHP tears the connection down at the end of the request.
     */
    private static ?\PDO $sharedPdo = null;
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
            if (self::$sharedPdo !== null) return $this->pdo = self::$sharedPdo;
            $this->cfg = require app_path('config/database.php');
            foreach (['host', 'dbname', 'user', 'pass'] as $required) {
                if (trim((string)($this->cfg[$required] ?? '')) === '') {
                    throw new \RuntimeException('Direct MySQL is not configured; missing ' . $required . '.');
                }
            }
            // No fsockopen probe. It opened a second socket purely to test reachability,
            // doubling connection pressure under exactly the conditions where the limit
            // is already being hit. PDO's own timeout reports an unreachable server.
            $dsn = 'mysql:host=' . $this->cfg['host'] . ';port=' . $this->cfg['port'] . ';dbname=' . $this->cfg['dbname'] . ';charset=utf8mb4';
            $this->pdo = self::$sharedPdo = new \PDO($dsn, $this->cfg['user'], $this->cfg['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_PERSISTENT => false,
            ]);
        }
        return $this->pdo;
    }

    private function isRemote(): bool {
        if ($this->remoteOnly !== null) return $this->remoteOnly;
        if ($this->forceDirect) return $this->remoteOnly = false;
        if (empty($this->cfg['remote_url'])) { $this->remoteOnly = false; return false; }
        // The bridge is for instances that have no database of their own. When
        // remote_url points back at the host serving this request, taking it would mean
        // HTTP-requesting ourselves: the inner request hits the same unavailable MySQL,
        // returns 500, and the outer one reports a confusing nested failure — while
        // doubling the connection pressure that caused the problem. Stay direct and let
        // a real MySQL error surface.
        if ($this->remoteUrlIsSelf()) { $this->remoteOnly = false; return false; }
        try { $this->db(); $this->remoteOnly = false; return false; }
        catch (\Throwable) { $this->remoteOnly = true; return true; }
    }

    /** True when remote_url resolves to the host currently serving this request. */
    private function remoteUrlIsSelf(): bool {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') return false;                       // CLI: no self to compare against
        $remoteHost = strtolower((string)(parse_url((string)$this->cfg['remote_url'], PHP_URL_HOST) ?: ''));
        if ($remoteHost === '') return false;
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        return $remoteHost === $host || $remoteHost === 'www.' . $host || 'www.' . $remoteHost === $host;
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
    /** Keep address ownership and default selection consistent on the database host. */
    public function mutateAddressBook(string $email, string $action, array $payload, callable $operation): array {
        if ($email === '') throw new \InvalidArgumentException('A customer account is required.');
        if ($this->isTestMode()) return $operation();
        if ($this->isRemote()) {
            return $this->remoteMutation($action, 'addresses', ['email' => $email] + $payload)['result'];
        }
        $pdo = $this->db();
        $name = 'sps:addresses:' . sha1($email);
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $lock->execute([$name]);
        if ((int)$lock->fetchColumn() !== 1) throw new \RuntimeException('Address book is busy. Please retry.');
        try {
            $pdo->beginTransaction();
            $result = $operation();
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        } finally {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$name]);
        }
    }

    /** Serialize order creation and invoice numbering on the database host. */
    private function withOrderInvoiceLock(callable $operation): array {
        $pdo = $this->db();
        $lock = $pdo->query("SELECT GET_LOCK(CONCAT('sps:orders:', SHA1(DATABASE())), 5)");
        if ((int)$lock->fetchColumn() !== 1) {
            throw new \RuntimeException('Order persistence is busy. Please retry.');
        }
        try {
            return $operation();
        } finally {
            $pdo->query("SELECT RELEASE_LOCK(CONCAT('sps:orders:', SHA1(DATABASE())))");
        }
    }

    public function saveVerifiedOrder(array $order, bool $issueInvoice): array {
        if ($this->isTestMode()) throw new \RuntimeException('Order writes are disabled in test mode.');
        if ($this->isRemote()) {
            return $this->remoteMutation('save_verified_order', 'orders', ['record' => $order, 'issue_invoice' => $issueInvoice])['result'];
        }
        return $this->withOrderInvoiceLock(function () use ($order, $issueInvoice): array {
            $orders = $this->read('orders');
            foreach ($orders as $existing) {
                if (($existing['payment_id'] ?? '') === $order['payment_id']
                    || (!empty($order['stripe_session_id']) && ($existing['stripe_session_id'] ?? '') === $order['stripe_session_id'])) {
                    return ['record' => $existing, 'created' => false];
                }
            }
            if ($issueInvoice) $order = array_merge($order, (new TaxService())->nextInvoice($orders));
            $insert = $this->insertIfAbsent('orders', $order);
            return ['record' => $insert['record'], 'created' => $insert['inserted']];
        });
    }

    public function resolveOrderTaxReview(string $id, string $shippingState, array $settings): array {
        if ($this->isTestMode()) throw new \RuntimeException('Order writes are disabled in test mode.');
        if ($this->isRemote()) {
            return $this->remoteMutation('resolve_order_tax_review', 'orders', ['id' => $id, 'state' => $shippingState, 'settings' => $settings])['result'];
        }
        return $this->withOrderInvoiceLock(function () use ($id, $shippingState, $settings): array {
            $orders = $this->read('orders');
            foreach ($orders as $order) {
                if ((string)($order['id'] ?? '') !== $id) continue;
                $updated = (new TaxService())->reviewedOrder($order, $orders, $shippingState, $settings);
                return $this->upsert('orders', $updated);
            }
            throw new \InvalidArgumentException('Order not found.');
        });
    }

    public function recordOrderRefund(string $id, string $paymentId, string $refundId, int $amount): array {
        if ($this->isTestMode()) throw new \RuntimeException('Order writes are disabled in test mode.');
        if ($this->isRemote()) {
            return $this->remoteMutation('record_order_refund', 'orders', [
                'id' => $id, 'payment_id' => $paymentId, 'refund_id' => $refundId, 'amount_paise' => $amount,
            ])['result'];
        }
        return $this->withOrderInvoiceLock(function () use ($id, $paymentId, $refundId, $amount): array {
            $order = $this->find('orders', $id);
            if ($order === null || $paymentId === '' || ($order['payment_id'] ?? '') !== $paymentId) {
                throw new \InvalidArgumentException('The order payment could not be matched.');
            }
            foreach ($order['verified_refunds'] ?? [] as $refund) {
                if ($refund['id'] === $refundId) {
                    if ($refund['amount_paise'] !== $amount) throw new \InvalidArgumentException('Refund amount conflicts with the saved record.');
                    return $order;
                }
            }
            if ($amount <= 0 || OrderService::refundedPaise($order) + $amount > (int)round((float)($order['total'] ?? 0) * 100)) {
                throw new \InvalidArgumentException('Refunds cannot exceed the recorded order total.');
            }
            $order['verified_refunds'][] = ['id' => $refundId, 'amount_paise' => $amount];
            return $this->upsert('orders', $order);
        });
    }

    public function updateOrderStatus(string $id, string $status, \DateTimeImmutable $now, array $tracking): array {
        if ($this->isTestMode()) throw new \RuntimeException('Order writes are disabled in test mode.');
        if ($this->isRemote()) {
            return $this->remoteMutation('update_order_status', 'orders', [
                'id' => $id, 'status' => $status, 'now' => $now->format('c'), 'tracking' => $tracking,
            ])['result'];
        }
        return $this->withOrderInvoiceLock(function () use ($id, $status, $now, $tracking): array {
            $order = $this->find('orders', $id);
            if ($order === null) throw new \RuntimeException('Order not found.');
            $order = OrderService::reviewFulfillment($order, $status, !empty($tracking['availability_confirmed']), $now);
            $order['status'] = $status;
            $order['updated_at'] = $now->format('c');
            if (in_array($status, ['shipped', 'delivered'], true)) {
                foreach (['tracking_id', 'tracking_url', 'courier_name'] as $field) {
                    if (!empty($tracking[$field])) $order[$field] = trim((string)$tracking[$field]);
                }
                $order['shipped_at'] ??= $now->format('c');
                $order['review_request_after_at'] = (new \DateTimeImmutable($order['shipped_at']))->modify('+10 days')->format('c');
            }
            return $this->upsert('orders', $order);
        });
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
    /** Atomically keep the first record for an id and return the stored winner. */
    public function insertIfAbsent(string $table, array $record): array {
        $id = trim((string)($record['id'] ?? ''));
        if ($id === '') throw new \InvalidArgumentException('Record id is required.');
        if ($this->isTestMode()) return ['inserted' => true, 'record' => $record];
        if ($this->isRemote()) {
            $result = $this->remoteMutation('insert_if_absent', $table, ['record' => $record]);
            return ['inserted' => !empty($result['inserted']), 'record' => $result['record'] ?? $record];
        }
        $clean = preg_replace('/[^a-z_]/', '', $table);
        $owner = $record['customer_email'] ?? $record['email'] ?? $record['user_id'] ?? null;
        $status = $record['status'] ?? null;
        $created = $record['created_at'] ?? date('c');
        $updated = $record['updated_at'] ?? $created;
        $stmt = $this->db()->prepare("INSERT IGNORE INTO {$clean} (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, json_encode($record), $owner, $status, $created, $updated]);
        $inserted = $stmt->rowCount() === 1;
        self::$remoteQueryCache = [];
        return ['inserted' => $inserted, 'record' => $inserted ? $record : ($this->find($table, $id) ?? $record)];
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
