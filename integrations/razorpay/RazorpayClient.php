<?php
namespace App\Integrations\Razorpay;
final class RazorpayClient {
    public function __construct(private string $keyId, private string $keySecret){}
    public function fetchPayment(string $paymentId): array {
        return $this->get('payments/' . rawurlencode($paymentId));
    }

    // https://razorpay.com/docs/api/refunds/fetch-with-id
    public function fetchRefund(string $refundId): array {
        return $this->get('refunds/' . rawurlencode($refundId));
    }

    public function fetchPaymentLink(string $id):array {
        // https://razorpay.com/docs/api/payments/payment-links/fetch-id-standard
        return $this->get('payment_links/'.rawurlencode($id));
    }

    public function findPaymentLinks(string $reference):array {
        // https://razorpay.com/docs/api/payments/payment-links/fetch-all-standard
        return $this->get('payment_links?reference_id='.rawurlencode($reference).'&count=20');
    }

    public function fetchOrderPayments(string $orderId):array {
        // https://razorpay.com/docs/api/orders/fetch-payments
        return $this->get('orders/'.rawurlencode($orderId).'/payments');
    }

    /** Create under a durable claim. Reconcile timeouts by reference. */
    public function createPaymentLink(array $payload):array {
        // https://razorpay.com/docs/api/payments/payment-links/create-standard
        $payload=array_intersect_key($payload,array_flip(['amount','currency','accept_partial','reference_id','expire_by','description','notify','reminder_enable']));
        $ch=curl_init('https://api.razorpay.com/v1/payment_links');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_USERPWD=>$this->keyId.':'.$this->keySecret,CURLOPT_TIMEOUT=>20]);
        $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        return self::paymentLinkResult($body, $status);
    }

    public static function paymentLinkResult(string|false $body, int $status): array {
        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        $error = $decoded['error'] ?? null;
        // Explicit pre-creation refusals from the Standard Payment Link response.
        // A duplicate reference also returns 400 and must be reconciled instead.
        // https://razorpay.com/docs/api/payments/payment-links/create-standard
        if (is_array($error) && !isset($decoded['id']) && ($error['code'] ?? null) === 'BAD_REQUEST_ERROR'
            && is_string($error['description'] ?? null)) {
            $reason = strtolower(rtrim(trim($error['description']), '.'));
            $authentication = in_array($status, [400, 401], true)
                && in_array($reason, ['the api key provided is invalid', 'authentication failed'], true);
            $accountDenied = $status === 400 && in_array($reason, [
                'invalid access: cannot create a payment link in live mode, as live mode is disabled for merchant',
                'invalid access: cannot create a payment link, as merchant is suspended',
            ], true);
            if ($authentication || $accountDenied) throw new PaymentLinkCreationRejected($status);
        }
        // Provider bodies can echo customer or request data.
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new \RuntimeException('Razorpay payment-link request failed', $status ?: 503);
        }
        return $decoded;
    }

    private function get(string $path): array {
        $ch = curl_init('https://api.razorpay.com/v1/' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($status === 401) throw new \RuntimeException('Razorpay authentication failed', 401);
        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        if ($status >= 300 || !is_array($decoded)) {
            $message = $decoded['error']['description'] ?? $error ?: 'Failed to fetch payment';
            throw new \RuntimeException($message, $status ?: 500);
        }
        return $decoded;
    }

    public function createOrder(int $amountPaise, string $receipt): array {
        $payload = json_encode(['amount'=>$amountPaise,'currency'=>'INR','receipt'=>$receipt]);
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_USERPWD=>$this->keyId.':'.$this->keySecret,
            CURLOPT_TIMEOUT=>20,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        if ($status === 401) {
            throw new \RuntimeException('Razorpay authentication failed', 401);
        }
        if ($status >= 300 || !is_array($decoded)) {
            $message = $decoded['error']['description'] ?? $error ?: 'Razorpay order creation failed';
            throw new \RuntimeException($message, $status ?: 500);
        }
        return $decoded;
    }
}
