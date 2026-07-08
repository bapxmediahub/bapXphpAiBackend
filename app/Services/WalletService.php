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

    public function addTopUp(string $email, array $quote, string $paymentId = '', string $status = 'confirmed'): array {
        return $this->store->upsert('wallet_transactions', [
            'id' => bin2hex(random_bytes(8)),
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

    public function spend(string $email, int $credits, string $sourceId, string $note): array {
        $credits = max(0, $credits);
        if ($credits <= 0) throw new \InvalidArgumentException('Credits must be positive.');
        if ($this->balanceFor($email) < $credits) throw new \RuntimeException('Insufficient credits.');
        return $this->store->upsert('wallet_transactions', [
            'id' => bin2hex(random_bytes(8)),
            'customer_email' => strtolower(trim($email)),
            'type' => 'debit',
            'credits' => -$credits,
            'source_id' => $sourceId,
            'note' => $note,
            'created_at' => date('c'),
        ]);
    }
}
