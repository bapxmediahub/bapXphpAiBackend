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

    /** A signed browser callback is not sufficient evidence of a captured payment. */
    public static function isCapturedRazorpayPayment(array $payment, string $paymentId, string $orderId, int $expectedPaise): bool {
        return $paymentId !== '' && $orderId !== '' && $expectedPaise > 0
            && ($payment['id'] ?? null) === $paymentId
            && ($payment['order_id'] ?? null) === $orderId
            && is_int($payment['amount'] ?? null) && $payment['amount'] === $expectedPaise
            && ($payment['currency'] ?? null) === 'INR'
            && ($payment['status'] ?? null) === 'captured'
            && (!array_key_exists('captured', $payment) || $payment['captured'] === true);
    }
}
