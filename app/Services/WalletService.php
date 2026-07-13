<?php
namespace App\Services;

final class WalletService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function balanceFor(string $email): int {
        $email = strtolower(trim($email));
        if ($email === '') return 0;
        $total = 0;
        foreach ($this->store->read('wallet_transactions') as $row) {
            if (strtolower((string)($row['customer_email'] ?? '')) !== $email) continue;
            $total += (int)($row['credits'] ?? 0);
        }
        return max(0, $total);
    }

    public function ensureSignupBonus(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') return ['granted' => false, 'credits' => 0];
        foreach ($this->store->read('wallet_transactions') as $row) {
            if (strtolower((string)($row['customer_email'] ?? '')) === $email && ($row['source_id'] ?? '') === 'signup_bonus_25') {
                return ['granted' => false, 'credits' => (int)($row['credits'] ?? 25)];
            }
        }
        $this->store->upsert('wallet_transactions', [
            'id' => bin2hex(random_bytes(8)),
            'customer_email' => $email,
            'type' => 'bonus',
            'credits' => 25,
            'amount_rupees' => 0,
            'source_id' => 'signup_bonus_25',
            'note' => 'Welcome signup credits',
            'status' => 'confirmed',
            'created_at' => date('c'),
        ]);
        return ['granted' => true, 'credits' => 25];
    }

    public function quoteTopUp(int $amountRupees): array {
        $amount = max(10, $amountRupees);
        $service = (int)ceil($amount * 0.02);
        $tax = (int)ceil(($amount + $service) * 0.18);
        $total = $amount + $service + $tax;
        return [
            'amount_rupees' => $amount,
            'service_charge' => $service,
            'tax' => $tax,
            'total_rupees' => $total,
            'total_paise' => $total * 100,
            'credits' => $amount * 20,
        ];
    }

    public function addTopUp(string $email, array $quote, string $paymentId = '', string $status = 'confirmed', ?string $id = null): array {
        return $this->store->upsert('wallet_transactions', [
            'id' => $id ?? bin2hex(random_bytes(8)),
            'customer_email' => strtolower(trim($email)),
            'type' => 'recharge',
            'credits' => (int)($quote['credits'] ?? 0),
            'amount_rupees' => (int)($quote['amount_rupees'] ?? 0),
            'service_charge' => (int)($quote['service_charge'] ?? 0),
            'tax' => (int)($quote['tax'] ?? 0),
            'total_rupees' => (int)($quote['total_rupees'] ?? 0),
            'payment_id' => $paymentId,
            'status' => $status,
            'created_at' => date('c'),
        ]);
    }

    public function confirmTopUp(string $email, string $orderId, string $paymentId, array $quote): bool {
        $pdo = $this->store->connection();
        $lock = 'wallet-payment-' . hash('sha256', $orderId);
        if (!$this->acquireLock($pdo, $lock)) throw new \RuntimeException('Payment confirmation is already being processed.');
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, _data FROM wallet_transactions WHERE JSON_UNQUOTE(JSON_EXTRACT(_data, '$.source_id')) = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) throw new \RuntimeException('Pending payment was not found.');
            $record = json_decode((string)$row['_data'], true) ?: [];
            if (($record['status'] ?? '') === 'confirmed') {
                $pdo->commit();
                return hash_equals((string)($record['payment_id'] ?? ''), $paymentId);
            }
            if (($record['status'] ?? '') !== 'pending' || strcasecmp((string)($record['customer_email'] ?? ''), trim($email)) !== 0) {
                throw new \RuntimeException('Payment does not belong to this wallet or is no longer pending.');
            }
            $record = array_merge($record, [
                'credits' => (int)($quote['credits'] ?? 0),
                'service_charge' => (int)($quote['service_charge'] ?? 0),
                'tax' => (int)($quote['tax'] ?? 0),
                'payment_id' => $paymentId,
                'status' => 'confirmed',
                'confirmed_at' => date('c'),
            ]);
            $update = $pdo->prepare('UPDATE wallet_transactions SET _data = ?, _status = ?, _updated_at = ? WHERE id = ?');
            $update->execute([json_encode($record), 'confirmed', date('c'), $row['id']]);
            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        } finally {
            $this->releaseLock($pdo, $lock);
        }
    }

    public function spend(string $email, int $credits, string $sourceId, string $note): array {
        $credits = max(0, $credits);
        if ($credits <= 0) throw new \InvalidArgumentException('Credits must be positive.');
        $email = strtolower(trim($email));
        if ($email === '') throw new \InvalidArgumentException('Wallet email is required.');
        $pdo = $this->store->connection();
        $lock = 'wallet-balance-' . hash('sha256', $email);
        if (!$this->acquireLock($pdo, $lock)) throw new \RuntimeException('Wallet is busy. Please try again.');
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.credits')) AS SIGNED)), 0) FROM wallet_transactions WHERE _owner = ? FOR UPDATE");
            $stmt->execute([$email]);
            $balance = max(0, (int)$stmt->fetchColumn());
            if ($balance < $credits) throw new \RuntimeException('Insufficient credits.');
            $record = [
                'id' => bin2hex(random_bytes(8)), 'customer_email' => $email, 'type' => 'debit',
                'credits' => -$credits, 'source_id' => $sourceId, 'note' => $note,
                'status' => 'confirmed', 'created_at' => date('c'),
            ];
            $insert = $pdo->prepare('INSERT INTO wallet_transactions (id, _data, _owner, _status, _created_at, _updated_at) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->execute([$record['id'], json_encode($record), $email, 'confirmed', $record['created_at'], $record['created_at']]);
            $pdo->commit();
            return $record;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        } finally {
            $this->releaseLock($pdo, $lock);
        }
    }

    private function acquireLock(\PDO $pdo, string $name): bool {
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $stmt->execute([$name]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function releaseLock(\PDO $pdo, string $name): void {
        try { $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$name]); } catch (\Throwable) {}
    }
}
