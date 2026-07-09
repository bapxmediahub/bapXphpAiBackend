<?php
namespace App\Integrations\Razorpay;
final class RazorpayClient {
    public function __construct(private string $keyId, private string $keySecret){}
    public function fetchPayment(string $paymentId): array {
        $ch = curl_init('https://api.razorpay.com/v1/payments/' . $paymentId);
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
