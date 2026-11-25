<?php
declare(strict_types=1);

namespace App\Services;

use App\Integrations\Razorpay\RazorpayClient;

/** Shared validation and recovery for Razorpay Standard Payment Links. */
final class RazorpayPaymentLinkService
{
    public static function mode(array $secrets): ?string
    {
        $mode = $secrets['razorpay_mode'] ?? 'test';
        if (!in_array($mode, ['test', 'live'], true)) $mode = 'test';
        if (!self::isKeyForMode((string)($secrets['razorpay_'.$mode.'_key_id'] ?? ''), $mode)) return null;
        foreach (['key_secret', 'webhook_secret'] as $field) {
            if (trim((string)($secrets['razorpay_'.$mode.'_'.$field] ?? '')) === '') return null;
        }
        return $mode;
    }

    public static function request(array $secrets, array $attempt, string $operation, array $payload): array
    {
        $mode = $attempt['mode'];
        $keyId = trim((string)($secrets['razorpay_'.$mode.'_key_id'] ?? ''));
        $secret = trim((string)($secrets['razorpay_'.$mode.'_key_secret'] ?? ''));
        if (!self::isKeyForMode($keyId, $mode) || $secret === '') {
            throw new \RuntimeException('Payment credentials unavailable', 401);
        }
        $client = new RazorpayClient($keyId, $secret);
        return match ($operation) {
            'create_link' => $client->createPaymentLink($payload),
            'find_link' => $client->findPaymentLinks($attempt['reference_id']),
            'fetch_link' => $client->fetchPaymentLink($attempt['provider_link_id']),
            'fetch_payment' => $client->fetchPayment($payload['payment_id']),
            'order_payments' => $client->fetchOrderPayments($payload['order_id']),
            default => throw new \LogicException('Unknown fixed payment operation.'),
        };
    }

    private static function isKeyForMode(string $keyId, string $mode): bool
    {
        // Keep separate test and live API keys. https://axisbank-docs.razorpay.com/payment-links/api/v1/
        return str_starts_with(trim($keyId), 'rzp_'.$mode.'_');
    }

    public static function validUrl(mixed $url): bool
    {
        return is_string($url) && strlen($url) <= 512
            && preg_match('~^https://rzp\.io/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+$~D', $url) === 1;
    }

    /** @param callable(string,array=): array $provider */
    public static function find(array $attempt, callable $provider): ?array
    {
        if (isset($attempt['provider_link_id'])) return $provider('fetch_link');

        $response = $provider('find_link');
        $links = $response['payment_links'] ?? $response['items'] ?? null;
        if (!is_array($links) || !array_is_list($links) || count($links) > 20) {
            throw new \RuntimeException('Invalid provider lookup result.');
        }
        $matches = array_values(array_filter($links, static fn($link) => is_array($link)
            && ($link['reference_id'] ?? null) === $attempt['reference_id']));
        if (count($matches) > 1) throw new \RuntimeException('Ambiguous provider reference.');

        return $matches[0] ?? null;
    }

    public static function validate(array $attempt, array $link): string
    {
        if (!is_string($link['id'] ?? null) || !preg_match('/^plink_[A-Za-z0-9]{1,64}$/D', $link['id'])
            || ($link['reference_id'] ?? null) !== $attempt['reference_id']
            || ($link['amount'] ?? null) !== $attempt['amount_paise']
            || ($link['currency'] ?? null) !== 'INR' || ($link['accept_partial'] ?? null) !== false
            || !is_int($link['expire_by'] ?? null) || $link['expire_by'] > $attempt['payment_expires_at']
            || $link['expire_by'] <= strtotime($attempt['created_at'].' UTC') || !self::validUrl($link['short_url'] ?? null)
            || (isset($attempt['provider_link_id']) && $attempt['provider_link_id'] !== $link['id'])) {
            throw new \RuntimeException('Provider checkout identity mismatch.');
        }

        $status = $link['status'] ?? null;
        if (!in_array($status, ['created', 'partially_paid', 'paid', 'expired', 'cancelled'], true)) {
            throw new \RuntimeException('Unknown provider checkout state.');
        }
        return $status;
    }

    /** Check the canonical paid link and authenticate its single captured payment via REST. */
    public static function captured(array $attempt, array $link, callable $provider): array
    {
        $orderId = $link['order_id'] ?? null;
        if (($link['amount_paid'] ?? null) !== $attempt['amount_paise']
            || !is_string($orderId) || !preg_match('/^order_[A-Za-z0-9]{1,64}$/D', $orderId)) {
            throw new \RuntimeException('Paid checkout amount or order mismatch.');
        }
        $ids = self::paymentIds($link['payments'] ?? [], 'payment_id');
        if (!$ids) {
            $payments = $provider('order_payments', ['order_id'=>$orderId]);
            $ids = self::paymentIds($payments['items'] ?? [], 'id');
        }
        $verified = [];
        foreach ($ids as $id) {
            $payment = $provider('fetch_payment', ['payment_id'=>$id]);
            if (PaymentService::isCapturedRazorpayPayment($payment, $id, $orderId, $attempt['amount_paise'])) {
                $verified[] = $id;
            }
        }
        if (count($verified) !== 1) throw new \RuntimeException('Captured payment could not be uniquely verified.');

        return ['payment_id'=>$verified[0], 'order_id'=>$orderId];
    }

    private static function paymentIds(mixed $payments, string $field): array
    {
        $ids = [];
        if (is_array($payments)) {
            foreach (array_slice($payments, 0, 20) as $payment) {
                $id = $payment[$field] ?? null;
                if (is_string($id) && preg_match('/^pay_[A-Za-z0-9]{1,64}$/D', $id)) $ids[$id] = true;
            }
        }
        return array_keys($ids);
    }
}
