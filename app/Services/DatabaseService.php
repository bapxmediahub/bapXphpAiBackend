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

    private function remoteMutation(string $action, string $table, array $payload, bool $invalidateCache = true): array {
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
        if ($invalidateCache) self::$remoteQueryCache = [];
        return $result;
    }

    /** Begin an independent request in a long-lived CLI transport. */
    public static function clearReadCache(): void { self::$remoteQueryCache = []; }

    /** Close the request's idle connection before a long external call. */
    public static function releaseIdleConnection(): void {
        if (self::$sharedPdo !== null && !self::$sharedPdo->inTransaction()) {
            self::$sharedPdo = null;
        }
    }

    private function db(): \PDO {
        if (self::$sharedPdo === null) {
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
            self::$sharedPdo = new \PDO($dsn, $this->cfg['user'], $this->cfg['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_PERSISTENT => false,
            ]);
        }
        return self::$sharedPdo;
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
            $this->ensureAddressTable();
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

    public function customerAddresses(string $email): array {
        if ($this->isTestMode()) return [];
        if ($this->isRemote()) {
            return $this->remoteMutation('list_customer_addresses', 'addresses', ['email' => $email])['result'];
        }
        $this->ensureAddressTable();
        $statement = $this->db()->prepare('SELECT id, _data FROM addresses WHERE _owner = ?');
        $statement->execute([$email]);
        return array_map(static fn(array $row): array => array_merge(
            json_decode($row['_data'], true, flags: JSON_THROW_ON_ERROR), ['id' => $row['id']]
        ), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function ensureAddressTable(): void {
        try {
            $this->db()->query('SELECT id FROM addresses LIMIT 0');
        } catch (\PDOException $error) {
            if (($error->errorInfo[0] ?? null) !== '42S02') throw $error;
            $this->db()->exec('CREATE TABLE IF NOT EXISTS addresses (
                id VARCHAR(36) PRIMARY KEY, _data JSON NOT NULL,
                _owner VARCHAR(255) DEFAULT NULL, _status VARCHAR(50) DEFAULT NULL,
                _created_at DATETIME DEFAULT NULL, _updated_at DATETIME DEFAULT NULL,
                INDEX idx_owner (_owner), INDEX idx_status (_status), INDEX idx_created (_created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
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

    /** Existing business table, optimistic version and row lock; no conversation SQL table. */
    /** One locked mail row; a worker claim is never a whole-queue rewrite. */
    public function transitionMailDelivery(string $id,string $action,string $attempt,array $fields): ?array {
        if(!preg_match('/^[a-f0-9]{16}$/D',$id))throw new \InvalidArgumentException('Invalid mail identity.');
        if($this->isTestMode())throw new \RuntimeException('Mail mutations require an isolated database test.');
        if($this->isRemote())return $this->remoteMutation('transition_mail_delivery','mail_queue',[
            'id'=>$id,'transition'=>$action,'attempt_id'=>$attempt,'fields'=>$fields])['record'];
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $query=$pdo->prepare('SELECT id, _data FROM mail_queue WHERE id = ? FOR UPDATE');$query->execute([$id]);
            $record=$query->fetch(\PDO::FETCH_ASSOC);
            if(!$record)throw new \InvalidArgumentException('Mail record not found.');
            $row=json_decode($record['_data'],true,512,JSON_THROW_ON_ERROR);$row['id']=$record['id'];
            $updated=MailQueueService::transitionDelivery($row,$action,$attempt,$fields,new \DateTimeImmutable());
            if($updated!==null)$this->upsert('mail_queue',$updated);
            $pdo->commit();self::$remoteQueryCache=[];return $updated;
        }catch(\Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    }

    public function updateSupportTicket(string $id,string $action,array $fields,array $actor,int $expectedVersion): array {
        if(!preg_match('/^[a-f0-9]{16}$/D',$id)||$expectedVersion<0)throw new \InvalidArgumentException('Invalid ticket/version.');
        if($this->isTestMode())throw new \RuntimeException('Ticket mutations require a disposable DB integration test.');
        if($this->isRemote())return $this->remoteMutation('transition_support_ticket','support_tickets',[
            'id'=>$id,'transition'=>$action,'fields'=>$fields,'actor'=>$actor,'expected_version'=>$expectedVersion])['result'];
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $query=$pdo->prepare('SELECT id, _data FROM support_tickets WHERE id = ? FOR UPDATE');$query->execute([$id]);
            $row=$query->fetch(\PDO::FETCH_ASSOC);
            if(!$row)throw new \InvalidArgumentException('Ticket not found.');
            $ticket=json_decode($row['_data'],true,512,JSON_THROW_ON_ERROR);$ticket['id']=$row['id'];
            if((int)($ticket['version']??0)!==$expectedVersion)throw new \InvalidArgumentException('Ticket changed; reload before saving.');
            $updated=SupportTicketService::transition($ticket,$action,$fields,$actor,new \DateTimeImmutable());
            $this->upsert('support_tickets',$updated);$pdo->commit();self::$remoteQueryCache=[];return $updated;
        }catch(\Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    }

    /** Fixed anonymous-cart protocol; the DB host owns the clock, row lock and creation limits. */
    public function remoteCartOperation(string $operation,array $payload): array {
        if(!in_array($operation,['create','get','set_quantity','prepare_checkout','preview_checkout','claim_checkout'],true)
            || !is_string($payload['id']??null) || !preg_match('/^[a-f0-9]{32}$/D',$payload['id'])) {
            throw new RemoteCartException('invalid_arguments','Invalid cart operation.');
        }
        if($this->isTestMode())throw new \RuntimeException('Cart persistence requires an isolated database test.');
        if($this->isRemote()) {
            $result=$this->remoteMutation('remote_cart_operation','agent_carts',[
                'operation'=>$operation,'payload'=>$payload])['result']??null;
            if(!is_array($result))throw new \RuntimeException('Invalid remote cart response.');
            if(isset($result['cart_error'])) {
                $error=$result['cart_error'];
                if(!is_array($error)||!is_string($error['code']??null)||!is_string($error['message']??null)
                    ||!is_int($error['status']??null)||!is_int($error['retry_after']??null)) {
                    throw new \RuntimeException('Invalid remote cart failure.');
                }
                throw new RemoteCartException($error['code'],$error['message'],$error['status'],$error['retry_after']);
            }
            return $result;
        }
        $this->ensureAgentCartTable();$pdo=$this->db();
        if($operation==='create') {
            // Serialize creation on MySQL, not in a process-local limiter. Concurrent
            // clients and the remote bridge cannot race through the resource limits.
            $lock=$pdo->query("SELECT GET_LOCK(CONCAT('sps:agent-carts:',SHA1(DATABASE())),5)");
            if((int)$lock->fetchColumn()!==1)throw new RemoteCartException('cart_busy','Cart creation is busy. Retry shortly.',429,5);
            try {
                $now=time();
                $result=RemoteCartService::transition(null,$operation,$payload,$now);
                $cutoff=gmdate('Y-m-d H:i:s',$now-RemoteCartService::CART_TTL);
                $cleanup=$pdo->prepare('DELETE FROM agent_carts WHERE _created_at <= ? ORDER BY _created_at LIMIT 100');
                $cleanup->execute([$cutoff]);
                $count=$pdo->prepare('SELECT COUNT(*) FROM agent_carts WHERE _owner = ? AND _created_at > ?');
                foreach([[60,10],[3600,20]] as [$seconds,$maximum]) {
                    $count->execute([$payload['creationKey'],gmdate('Y-m-d H:i:s',$now-$seconds)]);
                    if((int)$count->fetchColumn()>=$maximum)throw new RemoteCartException('cart_rate_limited','Too many new carts. Reuse an existing cart or try later.',429,$seconds);
                }
                $capacity=$pdo->prepare('SELECT COUNT(*) FROM agent_carts WHERE _created_at > ?');$capacity->execute([$cutoff]);
                if((int)$capacity->fetchColumn()>=10000)throw new RemoteCartException('cart_capacity','New carts are temporarily unavailable. Try later.',429,3600);
                $record=$result['record'];
                $insert=$pdo->prepare('INSERT INTO agent_carts (id,_data,_owner,_status,_created_at,_updated_at) VALUES (?,?,?,?,?,?)');
                $insert->execute([$record['id'],json_encode($record,JSON_THROW_ON_ERROR),$record['creation_key'],
                    $record['status'],$record['created_at'],$record['updated_at']]);
                self::$remoteQueryCache=[];return $result;
            } finally {
                $pdo->query("SELECT RELEASE_LOCK(CONCAT('sps:agent-carts:',SHA1(DATABASE())))");
            }
        }
        $pdo->beginTransaction();
        try {
            $query=$pdo->prepare('SELECT id,_data FROM agent_carts WHERE id = ? FOR UPDATE');$query->execute([$payload['id']]);
            $now=time();
            $row=$query->fetch(\PDO::FETCH_ASSOC);
            $record=$row?json_decode($row['_data'],true,512,JSON_THROW_ON_ERROR):null;
            if($record!==null)$record['id']=$row['id'];
            $result=RemoteCartService::transition($record,$operation,$payload,$now);
            if($result['record']!==$record) {
                $updated=$result['record'];
                $write=$pdo->prepare('UPDATE agent_carts SET _data = ?, _status = ?, _updated_at = ? WHERE id = ?');
                $write->execute([json_encode($updated,JSON_THROW_ON_ERROR),$updated['status'],$updated['updated_at'],$updated['id']]);
            }
            $pdo->commit();self::$remoteQueryCache=[];return $result;
        } catch(\Throwable $error) { if($pdo->inTransaction())$pdo->rollBack();throw $error; }
    }

    private function ensureAgentCartTable(): void {
        try { $this->db()->query('SELECT id FROM agent_carts LIMIT 0'); }
        catch(\PDOException $error) {
            if(($error->errorInfo[0]??null)!=='42S02')throw $error;
            // Same generic JSON-record layout as canonical schema initialization.
            // DDL precedes every transaction so MySQL cannot implicitly commit a claim.
            $this->db()->exec('CREATE TABLE IF NOT EXISTS agent_carts (
                id VARCHAR(36) PRIMARY KEY, _data JSON NOT NULL,
                _owner VARCHAR(255) DEFAULT NULL, _status VARCHAR(50) DEFAULT NULL,
                _created_at DATETIME DEFAULT NULL, _updated_at DATETIME DEFAULT NULL,
                INDEX idx_owner (_owner), INDEX idx_status (_status), INDEX idx_created (_created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    /** Fixed checkout protocol: durable attempts and the cart lock live on the MySQL host. */
    public function remoteCheckoutOperation(string $operation,array $payload):array {
        $operations=['read_cart','save_review','begin_payment','read_attempt','read_handoff','read_internal','claim_reconcile','update_attempt','list_pending'];
        if(!in_array($operation,$operations,true)||($operation!=='list_pending'
            &&(!is_string($payload['id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$payload['id'])))) {
            throw new RemoteCartException('invalid_arguments','Invalid checkout operation.');
        }
        if($this->isTestMode())throw new \RuntimeException('Checkout persistence requires an isolated database test.');
        if($this->isRemote()) {
            $result=$this->remoteMutation('remote_checkout_operation','checkout_attempts',['operation'=>$operation,'payload'=>$payload])['result']??null;
            if(!is_array($result))throw new \RuntimeException('Invalid remote checkout response.');
            if(isset($result['cart_error'])) {
                $error=$result['cart_error'];
                if(!is_array($error)||!is_string($error['code']??null)||!is_string($error['message']??null)
                    ||!is_int($error['status']??null)||!is_int($error['retry_after']??null))throw new \RuntimeException('Invalid remote checkout failure.');
                throw new RemoteCartException($error['code'],$error['message'],$error['status'],$error['retry_after']);
            }
            return $result;
        }
        $this->ensureCheckoutAttemptTable();$pdo=$this->db();
        if($operation==='list_pending') {
            $limit=$payload['limit']??null;
            if(!is_int($limit)||$limit<1||$limit>20)throw new RemoteCartException('invalid_arguments','Use a bounded reconciliation limit.');
            $now=time();$query=$pdo->prepare("SELECT id,_data FROM checkout_attempts WHERE _status NOT IN ('completed','expired','cancelled') AND _updated_at <= ? ORDER BY _updated_at,id LIMIT ".$limit);
            $query->execute([gmdate('Y-m-d H:i:s',$now-30)]);$attempts=[];
            foreach($query->fetchAll(\PDO::FETCH_ASSOC) as $row){$attempt=json_decode($row['_data'],true,512,JSON_THROW_ON_ERROR);$attempt['id']=$row['id'];$attempts[]=$attempt;}
            return ['attempts'=>$attempts,'now'=>$now];
        }
        $cartOperation=in_array($operation,['read_cart','save_review','begin_payment'],true);
        if($cartOperation)$this->ensureAgentCartTable();
        $creationLock=$operation==='begin_payment';
        if($creationLock) {
            $lock=$pdo->query("SELECT GET_LOCK(CONCAT('sps:checkout-attempts:',SHA1(DATABASE())),5)");
            if((int)$lock->fetchColumn()!==1)throw new RemoteCartException('checkout_busy','Checkout preparation is busy. Retry this same quote shortly.',429,5);
        }
        try {
            $pdo->beginTransaction();$record=$attempt=null;
            if($cartOperation) {
                $read=$pdo->prepare('SELECT id,_data FROM agent_carts WHERE id = ? FOR UPDATE');$read->execute([$payload['id']]);$row=$read->fetch(\PDO::FETCH_ASSOC);
                if($row){$record=json_decode($row['_data'],true,512,JSON_THROW_ON_ERROR);$record['id']=$row['id'];}
                $attemptId=$record['checkout_receipt']['attempt_id']??null;
                if(is_string($attemptId)) {
                    $read=$pdo->prepare('SELECT id,_data FROM checkout_attempts WHERE id = ? FOR UPDATE');$read->execute([$attemptId]);$row=$read->fetch(\PDO::FETCH_ASSOC);
                    if($row){$attempt=json_decode($row['_data'],true,512,JSON_THROW_ON_ERROR);$attempt['id']=$row['id'];}
                }
                $now=time();$result=RemoteCheckoutStateService::cart($record,$attempt,$operation,$payload,$now);
                if(($result['created']??false)===true) {
                    // Completed/expired receipts live for seven days. Unresolved money
                    // is retained and reconciled even after the 24-hour cart is removed.
                    $cleanup=$pdo->prepare("DELETE FROM checkout_attempts WHERE _status IN ('completed','expired','cancelled') AND _created_at <= ? ORDER BY _created_at LIMIT 100");
                    $cleanup->execute([gmdate('Y-m-d H:i:s',$now-RemoteCheckoutService::STATUS_TTL)]);
                    if((int)$pdo->query('SELECT COUNT(*) FROM checkout_attempts')->fetchColumn()>=20000) {
                        throw new RemoteCartException('checkout_capacity','New checkouts are temporarily unavailable. Existing payment status remains available.',429,3600);
                    }
                    $new=$result['attempt'];$insert=$pdo->prepare('INSERT INTO checkout_attempts (id,_data,_owner,_status,_created_at,_updated_at) VALUES (?,?,?,?,?,?)');
                    $insert->execute([$new['id'],json_encode($new,JSON_THROW_ON_ERROR),$new['cart_id'],$new['state'],$new['created_at'],$new['updated_at']]);
                }
                if($result['record']!==$record) {
                    $updated=$result['record'];$write=$pdo->prepare('UPDATE agent_carts SET _data = ?, _status = ?, _updated_at = ? WHERE id = ?');
                    $write->execute([json_encode($updated,JSON_THROW_ON_ERROR),$updated['status'],$updated['updated_at'],$updated['id']]);
                }
            } else {
                $read=$pdo->prepare('SELECT id,_data FROM checkout_attempts WHERE id = ? FOR UPDATE');$read->execute([$payload['id']]);$row=$read->fetch(\PDO::FETCH_ASSOC);
                if($row){$attempt=json_decode($row['_data'],true,512,JSON_THROW_ON_ERROR);$attempt['id']=$row['id'];}
                $result=RemoteCheckoutStateService::attempt($attempt,$operation,$payload,time());
                if($result['attempt']!==$attempt) {
                    $updated=$result['attempt'];$write=$pdo->prepare('UPDATE checkout_attempts SET _data = ?, _status = ?, _updated_at = ? WHERE id = ?');
                    $write->execute([json_encode($updated,JSON_THROW_ON_ERROR),$updated['state'],$updated['updated_at'],$updated['id']]);
                }
            }
            $pdo->commit();self::$remoteQueryCache=[];return $result;
        } catch(\Throwable $error) {
            if($pdo->inTransaction())$pdo->rollBack();throw $error;
        } finally {
            if($creationLock)$pdo->query("SELECT RELEASE_LOCK(CONCAT('sps:checkout-attempts:',SHA1(DATABASE())))");
        }
    }

    private function ensureCheckoutAttemptTable():void {
        try{$this->db()->query('SELECT id FROM checkout_attempts LIMIT 0');}
        catch(\PDOException $error) {
            if(($error->errorInfo[0]??null)!=='42S02')throw $error;
            $this->db()->exec('CREATE TABLE IF NOT EXISTS checkout_attempts (
                id VARCHAR(36) PRIMARY KEY, _data JSON NOT NULL,
                _owner VARCHAR(255) DEFAULT NULL, _status VARCHAR(50) DEFAULT NULL,
                _created_at DATETIME DEFAULT NULL, _updated_at DATETIME DEFAULT NULL,
                INDEX idx_owner (_owner), INDEX idx_status (_status), INDEX idx_created (_created_at), INDEX idx_updated (_updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
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

    /** Lock and patch the three owner-editable media fields without creating a row. */
    public function updateMediaMetadata(string $id, array $fields, string $expected): void {
        if (!preg_match('/^[a-f0-9]{16}$/D', $id) || !preg_match('/^[a-f0-9]{64}$/D', $expected)) {
            throw new \InvalidArgumentException('Invalid media identity or revision.');
        }
        if ($this->isTestMode()) throw new \RuntimeException('Media edits require an isolated database integration test.');
        if ($this->isRemote()) {
            $result = $this->remoteMutation('update_media_metadata', 'media_files', ['id'=>$id,'fields'=>$fields,'expected'=>$expected]);
            if (($result['updated'] ?? false) !== true) throw new \RuntimeException('Media metadata was not saved.');
            return;
        }
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $record = $this->lockMediaRecord($id);
            if (!$record) throw new \InvalidArgumentException('Uploaded media record not found.');
            $patch = MediaService::metadataPatch($record, $fields, $expected);
            $write = $pdo->prepare('UPDATE media_files SET _data = JSON_MERGE_PATCH(_data, ?), _updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $write->execute([json_encode($patch, JSON_THROW_ON_ERROR), $id]);
            $pdo->commit();
            self::$remoteQueryCache = [];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    /** Patch only the intended user fields. Compare credentials before replacing them. */
    public function patchUser(string $id, array $fields, ?string $credential = null, ?string $expected = null): bool {
        $fields = array_intersect_key($fields, array_flip([
            'name', 'phone', 'gender', 'date_of_birth', 'password_hash',
            'password_changed_at', 'must_change_password', 'reset_token_hash', 'reset_token_expires_at',
        ]));
        if ($id === '' || $fields === []) return false;
        if ($credential !== null && !in_array($credential, ['password_hash', 'reset_token_hash'], true)) {
            throw new \InvalidArgumentException('Unsupported credential check.');
        }
        if ($this->isTestMode()) return false;
        if ($this->isRemote()) {
            $result = $this->remoteMutation('patch_user', 'users', [
                'id' => $id, 'fields' => $fields, 'credential' => $credential, 'expected' => $expected,
            ]);
            return ($result['updated'] ?? false) === true;
        }
        $sql = "UPDATE users SET _data = JSON_MERGE_PATCH(_data, ?), _updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $params = [json_encode($fields, JSON_THROW_ON_ERROR), $id];
        if ($credential !== null) {
            $sql .= " AND BINARY JSON_UNQUOTE(JSON_EXTRACT(_data, ?)) = BINARY ?";
            $params[] = '$.' . $credential;
            $params[] = $expected;
        }
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount() > 0;
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
        if ($table === 'settings' && $key === 'id' && ($record['id'] ?? null) === SettingsService::SETTINGS_ID) {
            return $this->upsertApplicationSettings($record);
        }
        $clean = preg_replace('/[^a-z_]/', '', $table);
        $id = $record[$key] ?? bin2hex(random_bytes(8));
        $existing = $this->find($table, $id, $key);
        if ($existing) {
            $merged = array_merge($existing, $record);
            $owner = $merged['customer_email'] ?? $merged['email'] ?? $merged['user_id'] ?? null;
            $status = $merged['status'] ?? null;
            $updated = self::campaignSqlTimestamp($table, $merged, $merged['updated_at'] ?? date('c'));
            $stmt = $this->db()->prepare("UPDATE {$clean} SET _data = ?, _owner = ?, _status = ?, _updated_at = ? WHERE id = ?");
            $stmt->execute([json_encode($merged), $owner, $status, $updated, $id]);
        } else {
            $owner = $record['customer_email'] ?? $record['email'] ?? $record['user_id'] ?? null;
            $status = $record['status'] ?? null;
            $created = self::campaignSqlTimestamp($table, $record, $record['created_at'] ?? date('c'));
            $updated = self::campaignSqlTimestamp($table, $record, $record['updated_at'] ?? $created);
            $stmt = $this->db()->prepare("INSERT INTO {$clean} (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, json_encode($record), $owner, $status, $created, $updated]);
        }
        return $record;
    }

    /** Keep campaign dates in JSON and bind a portable UTC DATETIME for their CRM rows. */
    private static function campaignSqlTimestamp(string $table, array $record, mixed $value): mixed {
        $slug = $record['campaign_slug'] ?? null;
        if (!in_array($table, ['contact_submissions', 'mail_inbox'], true)
            || !is_string($slug)
            || !AuthoredPageService::validSlug($slug)
            || !AuthoredPageService::validRevision($record['campaign_revision'] ?? null)
            || !is_string($value)
            || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            return $value;
        }
        // https://mariadb.com/docs/server/reference/sql-structure/sql-language-structure/date-and-time-literals
        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
        if ($parsed === false || \DateTimeImmutable::getLastErrors() !== false) return $value;
        return gmdate('Y-m-d H:i:s', $parsed->getTimestamp());
    }

    /** Independent owner forms patch the canonical settings row under one row lock. */
    private function upsertApplicationSettings(array $record): array {
        $id = SettingsService::SETTINGS_ID;
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $ensure = $pdo->prepare('INSERT INTO settings (id, _data, _created_at, _updated_at)
                VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE id = id');
            $ensure->execute([$id, json_encode($record, JSON_THROW_ON_ERROR)]);

            $read = $pdo->prepare('SELECT _data FROM settings WHERE id = ? FOR UPDATE');
            $read->execute([$id]);
            $current = json_decode((string)$read->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);
            if ($ensure->rowCount() === 1) {
                // Only the request that created the canonical row can seed its legacy defaults.
                $seed = $pdo->prepare("SELECT _data FROM settings
                    WHERE id NOT IN (?, ?) AND id NOT LIKE 'pg:%' ORDER BY id LIMIT 1");
                $seed->execute([$id, SettingsService::HEADER_MENU_ID]);
                $legacy = json_decode($seed->fetchColumn() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
                if (password_get_info((string)($legacy['admin_password'] ?? ''))['algo'] === null) {
                    unset($legacy['admin_password']);
                }
                $current += $legacy;
            }
            $merged = array_replace($current, $record);
            $write = $pdo->prepare('UPDATE settings SET _data = ?, _owner = ?, _status = ?, _updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $write->execute([
                json_encode($merged, JSON_THROW_ON_ERROR),
                $merged['customer_email'] ?? $merged['email'] ?? $merged['user_id'] ?? null,
                $merged['status'] ?? null,
                $id,
            ]);
            $pdo->commit();
            return $record;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    /** Coordinate owner edits and create-only imports without a change to the hosted schema. */
    private function withProductLock(callable $operation): array {
        $pdo = $this->db();
        $key = "CONCAT('sps:products:', SHA1(DATABASE()))";
        if ((int)$pdo->query("SELECT GET_LOCK($key, 5)")->fetchColumn() !== 1) {
            throw new \RuntimeException('Another product change is in progress.');
        }
        try { return $operation(); }
        finally { $pdo->query("SELECT RELEASE_LOCK($key)"); }
    }

    public function saveCatalogProduct(array $record, bool $matchSlug, bool $requireExactTarget = false): array {
        if ($this->isTestMode()) return $this->upsert('products', $record);
        if ($this->isRemote()) return $this->remoteMutation('save_catalog_product', 'products',
            ['record'=>$record, 'match_slug'=>$matchSlug, 'require_exact_target'=>$requireExactTarget])['record'];
        return $this->withProductLock(function () use ($record, $matchSlug, $requireExactTarget): array {
            $slug = trim((string)($record['slug'] ?? ''));
            $current = null;
            if ($requireExactTarget && !$matchSlug) {
                $current = $this->find('products', (string)$record['id']);
                if ($current === null) throw new \InvalidArgumentException('The product was removed before this edit was saved.');
            }
            if (($matchSlug || $requireExactTarget) && $slug !== '') {
                $existing = $this->query("SELECT id FROM products WHERE LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.slug')))) = LOWER(?) ORDER BY id LIMIT 1", [$slug]);
                if ($existing) {
                    if ($requireExactTarget && ($matchSlug || $existing[0]['id'] !== $record['id'])) {
                        throw new \InvalidArgumentException('That slug already belongs to a product. Use its edit command or choose a different slug.');
                    }
                    $record['id'] = $existing[0]['id'];
                }
            }
            return $this->upsert('products', $record) + ($current ?? []);
        });
    }

    /** Return existing identifiers for a bounded import preview or its final locked recheck. */
    public function existingProductSlugs(array $slugs, bool $forUpdate = false): array {
        if (!$slugs) return [];
        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $slug = "LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.slug'))))";
        $sql = "SELECT $slug AS slug FROM products WHERE $slug IN ($placeholders)";
        return array_column($this->query($sql . ($forUpdate ? ' FOR UPDATE' : ''), $slugs), 'slug');
    }

    /** Run the complete create-only batch on the MySQL host, including remote clients. */
    public function importProducts(string $csv, array $skip = []): array {
        $records = ProductImportService::parse($csv);
        if ($this->isTestMode()) throw new \LogicException('Product import requires a database.');
        if ($this->isRemote()) return $this->remoteMutation('import_products', 'products', ['csv'=>$csv, 'skip'=>$skip])['result'];
        return $this->withProductLock(function () use ($records, $skip): array {
            $pdo = $this->db();
            $pdo->beginTransaction();
            try {
                $existing = array_flip(array_merge($skip, $this->existingProductSlugs(array_column($records, 'slug'), true)));
                $insert = $pdo->prepare('INSERT INTO products (id, _data, _status, _created_at, _updated_at) VALUES (?, ?, ?, NOW(), NOW())');
                $added = 0;
                foreach ($records as $record) {
                    if (isset($existing[$record['slug']])) continue;
                    $record['id'] = bin2hex(random_bytes(8));
                    $insert->execute([$record['id'], json_encode($record, JSON_THROW_ON_ERROR), $record['status']]);
                    $added++;
                }
                $pdo->commit();
                self::$remoteQueryCache = [];
                return ['added'=>$added, 'skipped'=>count($records) - $added];
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        });
    }

    public function deleteAuthoredPage(string $slug, string $expected): bool {
        if (!AuthoredPageService::validSlug($slug) || !AuthoredPageService::validRevision($expected)) {
            throw new \InvalidArgumentException('Invalid page or revision.');
        }
        if ($this->isTestMode()) throw new \LogicException('Page writes require a database.');
        if ($this->isRemote()) return $this->remoteMutation('delete_authored_page', 'settings', compact('slug','expected'))['deleted'] === true;
        $query = $this->db()->prepare("DELETE FROM settings WHERE id = ? AND JSON_UNQUOTE(JSON_EXTRACT(_data, '$.revision')) = ?");
        $query->execute(['pg:'.$slug, $expected]);
        return $query->rowCount() === 1;
    }

    public function saveAuthoredPage(array $input, string $expected): bool {
        if ($expected !== '0' && !AuthoredPageService::validRevision($expected)) throw new \InvalidArgumentException('Invalid page revision.');
        $record = AuthoredPageService::record($input);
        if ($this->isTestMode()) throw new \LogicException('Page writes require a database.');
        if ($this->isRemote()) {
            $result = $this->remoteMutation('save_authored_page', 'settings', compact('record', 'expected'));
            if (($result['header_unavailable'] ?? false) === true) {
                throw new \InvalidArgumentException('Choose an available uploaded image from the Media Library.');
            }
            return $result['saved'] === true;
        }
        $url = $record['header_image_url'] ?? null;
        if ($url === null) return $this->writeAuthoredPage($record, $expected);

        $media = (new MediaService(store: $this))->findPageHeaderUpload($url);
        if (!$media) throw new \InvalidArgumentException('Choose an available uploaded image from the Media Library.');
        // Serialize attachment with removal of the same upload on the database host.
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $current = $this->lockMediaRecord((string)$media['id']);
            if (!$current || ($current['url'] ?? null) !== $url || !MediaService::canDeleteRecord($current)) {
                throw new \InvalidArgumentException('Choose an available uploaded image from the Media Library.');
            }
            $saved = $this->writeAuthoredPage($record, $expected);
            $pdo->commit();
            return $saved;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    private function writeAuthoredPage(array $record, string $expected): bool {
        if ($expected === '0') return $this->insertIfAbsent('settings', $record)['inserted'];
        $query = $this->db()->prepare("UPDATE settings SET _data = ?, _status = ?, _updated_at = ?
            WHERE id = ? AND JSON_UNQUOTE(JSON_EXTRACT(_data, '$.revision')) = ?");
        $query->execute([json_encode($record, JSON_THROW_ON_ERROR), $record['status'], date('c'), $record['id'], (string)$expected]);
        return $query->rowCount() === 1;
    }

    /** Store a validated campaign contact and its Inbox projection together on the DB host. */
    public function saveCampaignLead(string $id, string $slug, string $revision, array $answers): string {
        if (!preg_match('/^lead_[a-f0-9]{24}$/D', $id) || !AuthoredPageService::validSlug($slug)
            || !AuthoredPageService::validRevision($revision)) {
            throw new \InvalidArgumentException('Invalid campaign submission identity.');
        }
        if ($this->isTestMode()) throw new \LogicException('Campaign leads require an isolated database.');
        if ($this->isRemote()) {
            $result = $this->remoteMutation('save_campaign_lead', 'contact_submissions', compact('id','slug','revision','answers'));
            $outcome = $result['outcome'] ?? null;
            if (!in_array($outcome, ['received','stale','invalid'], true)) {
                throw new \RuntimeException('Campaign submission was not confirmed.');
            }
            return $outcome;
        }

        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            // A concurrent editor must not replace or withdraw the definition during an accepted lead write.
            $rows = $this->query('SELECT _data FROM settings WHERE id = ? FOR UPDATE', ['pg:'.$slug]);
            $page = $rows === [] ? [] : json_decode($rows[0]['_data'], true, flags: JSON_THROW_ON_ERROR);
            $definition = CampaignLeadService::pageDefinition($page);
            if (($page['slug'] ?? null) !== $slug || ($page['revision'] ?? null) !== $revision
                || ($page['status'] ?? null) !== 'published' || $definition === null) {
                $pdo->rollBack();
                return 'stale';
            }

            // A retained Inbox receipt also prevents a replay after owner deletion from Contacts.
            $contacts = new ContactService($this);
            if ($contacts->hasCampaignReceipt($id, $slug, $revision)) {
                $pdo->commit();
                return 'received';
            }

            $validation = CampaignLeadService::validate($definition, $answers);
            if ($validation['errors']) {
                $pdo->rollBack();
                return 'invalid';
            }
            $contact = CampaignLeadService::contactRecord($id, $slug, $revision, $definition, $validation['values']);
            if (ContactService::validationErrors($contact, ['Campaign enquiry'=>'Campaign enquiry'])) {
                $pdo->rollBack();
                return 'invalid';
            }
            if ($this->insertIfAbsent('contact_submissions', $contact)['inserted']) {
                (new MailStorageService($this))->recordInboxFromContact($contact);
            } elseif (!$contacts->hasCampaignReceipt($id, $slug, $revision)) {
                throw new \RuntimeException('Campaign submission was not confirmed.');
            }
            $pdo->commit();
            return 'received';
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    /** A single settings record owns public header order, independently of the general settings forms. */
    public function saveHeaderMenu(array $items, int $revision): bool {
        SettingsService::validateMenuItems($items);
        if ($revision < 0 || $revision > 999999999) throw new \InvalidArgumentException('Invalid menu revision.');
        if ($this->isTestMode()) throw new \LogicException('Menu writes require a database.');
        if ($this->isRemote()) {
            return $this->remoteMutation('save_header_menu', 'settings', compact('items','revision'))['saved'] === true;
        }
        $id = SettingsService::HEADER_MENU_ID;
        $record = ['id'=>$id, 'menu_revision'=>$revision+1, 'header_menu'=>$items];
        if ($revision === 0) return $this->insertIfAbsent('settings', $record)['inserted'];
        $query = $this->db()->prepare("UPDATE settings SET _data = ?, _updated_at = ?
            WHERE id = ? AND JSON_UNQUOTE(JSON_EXTRACT(_data, '$.menu_revision')) = ?");
        $query->execute([json_encode($record, JSON_THROW_ON_ERROR), date('c'), $id, (string)$revision]);
        return $query->rowCount() === 1;
    }

    /** Compare the encrypted row revision so a stale selection/disconnect cannot overwrite a new grant. */
    public function changeGoogleReportingRecord(string $revision, ?array $record): bool {
        if (strlen((string)base64_decode($revision, true)) !== 16) throw new \InvalidArgumentException('Invalid reporting revision.');
        if ($record !== null && (
            ($record['id'] ?? '') !== 'google_reporting_grant' || !isset($record['iv'], $record['ciphertext'])
            || strlen((string)base64_decode((string)$record['iv'], true)) !== 16
            || empty($record['ciphertext'])
        )) throw new \InvalidArgumentException('Invalid encrypted reporting record.');
        if ($this->isTestMode()) return false;
        if ($this->isRemote()) {
            return $this->remoteMutation('change_google_reporting', 'secrets', compact('revision','record'))['changed'] === true;
        }
        $where = " WHERE id = ? AND BINARY JSON_UNQUOTE(JSON_EXTRACT(_data, '$.iv')) = ?";
        $values = ['google_reporting_grant', $revision];
        $sql = 'DELETE FROM secrets';
        if ($record !== null) {
            $sql = 'UPDATE secrets SET _data = ?, _updated_at = ?';
            array_unshift($values, json_encode($record, JSON_THROW_ON_ERROR), date('c'));
        }
        $query = $this->db()->prepare($sql . $where);
        $query->execute($values);
        return $query->rowCount() === 1;
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
        $created = self::campaignSqlTimestamp($table, $record, $record['created_at'] ?? date('c'));
        $updated = self::campaignSqlTimestamp($table, $record, $record['updated_at'] ?? $created);
        $stmt = $this->db()->prepare("INSERT IGNORE INTO {$clean} (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, json_encode($record), $owner, $status, $created, $updated]);
        $inserted = $stmt->rowCount() === 1;
        self::$remoteQueryCache = [];
        return ['inserted' => $inserted, 'record' => $inserted ? $record : ($this->find($table, $id) ?? $record)];
    }
    private function lockMediaRecord(string $id): ?array {
        $rows = $this->query('SELECT id, _data FROM media_files WHERE id = ? FOR UPDATE', [$id]);
        if ($rows === []) return null;
        return ['id'=>$rows[0]['id']] + json_decode($rows[0]['_data'], true, flags: JSON_THROW_ON_ERROR);
    }

    /** Close the gap between the library's reference scan and the metadata deletion. */
    private function deleteMediaRecord(string $id): void {
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $media = $this->lockMediaRecord($id);
            $url = $media['url'] ?? null;
            if (MediaService::isPageHeaderPath($url)
                && $this->query("SELECT id FROM settings WHERE id LIKE 'pg:%'
                    AND BINARY JSON_UNQUOTE(JSON_EXTRACT(_data, '$.header_image_url')) = BINARY ? LIMIT 1", [$url]) !== []) {
                throw new \InvalidArgumentException('This image is still referenced by an authored page.');
            }
            $pdo->prepare('DELETE FROM media_files WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public function delete(string $table, string $value, string $key = 'id'): void {
        if ($this->isTestMode()) return;
        if ($this->isRemote()) {
            $record = $key === 'id' ? ['id' => $value] : $this->find($table, $value, $key);
            if ($record) $this->remoteMutation('delete', $table, ['id' => $record['id']]);
            return;
        }
        $clean = preg_replace('/[^a-z_]/', '', $table);
        if ($key === 'id' && $clean === 'media_files') {
            $this->deleteMediaRecord($value);
            return;
        }
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
    /** Count storefront actions on the database host, preserving the PHP cutoff. */
    public function storefrontActivitySince(int $since): array {
        if ($this->isTestMode()) return SiteTelemetryService::counts([], $since);
        if (!$this->isRemote()) {
            return SiteTelemetryService::counts($this->storefrontActivityCandidates($since), $since);
        }

        $response = $this->remoteMutation('count_storefront_activity', 'audit_events', ['since' => $since], invalidateCache: false);
        $counts = $response['result'] ?? null;
        if (($response['success'] ?? null) !== true || !is_array($counts) || count($counts) !== 2
            || !is_int($counts['cart_add'] ?? null) || $counts['cart_add'] < 0
            || !is_int($counts['checkout_initiated'] ?? null) || $counts['checkout_initiated'] < 0) {
            throw new \RuntimeException('Database returned invalid storefront activity counts.');
        }
        return $counts;
    }

    private function storefrontActivityCandidates(int $since): \Generator {
        // Only discard unmistakably old date('c') shapes. Four days cover both
        // PHP's short-month date normalization and the explicit offset. Keep any
        // other format for the same PHP parser used before this filtered read.
        $oldestDate = gmdate('Y-m-d', $since - 4 * 86400);
        $sql = <<<'SQL'
            SELECT event_name, recorded_at
            FROM (
                SELECT JSON_UNQUOTE(JSON_EXTRACT(_data, '$.meta.event')) AS event_name,
                       JSON_EXTRACT(_data, '$.created_at') AS recorded_at
                FROM audit_events
                WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.event')) AS BINARY) = CAST('commerce.event' AS BINARY)
            ) AS storefront_events
            WHERE CAST(event_name AS BINARY) IN (CAST('cart_add' AS BINARY), CAST('checkout_initiated' AS BINARY))
              AND JSON_TYPE(recorded_at) NOT IN ('NULL', 'OBJECT', 'ARRAY')
              AND NOT (
                  CHAR_LENGTH(JSON_UNQUOTE(recorded_at)) = 25
                  AND JSON_UNQUOTE(recorded_at) REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9][+-]([01][0-9]|2[0-3]):[0-5][0-9]$'
                  AND LEFT(JSON_UNQUOTE(recorded_at), 10) < ?
              )
            SQL;
        $pdo = $this->db();
        $bufferedAttribute = defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
            ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY
            : \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
        $wasBuffered = $pdo->getAttribute($bufferedAttribute);
        $stmt = null;
        try {
            if (!$pdo->setAttribute($bufferedAttribute, false)) {
                throw new \RuntimeException('Could not stream the storefront activity result.');
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$oldestDate]);
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $timestamp = json_decode($row['recorded_at'], true, flags: JSON_THROW_ON_ERROR);
                yield ['event' => 'commerce.event', 'meta' => ['event' => $row['event_name']], 'created_at' => $timestamp];
            }
        } finally {
            try {
                $stmt?->closeCursor();
            } finally {
                $pdo->setAttribute($bufferedAttribute, $wasBuffered);
            }
        }
    }

    public function connection(): \PDO {
        if ($this->isTestMode()) throw new \RuntimeException('Database connection is disabled in test mode.');
        return $this->db();
    }
}
