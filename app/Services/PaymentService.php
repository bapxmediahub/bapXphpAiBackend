<?php
namespace App\Services;
final class PaymentService {
    public function __construct(private string $secret) {}
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool {
        if ($this->secret === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, $this->secret), $signature);
    }
}
