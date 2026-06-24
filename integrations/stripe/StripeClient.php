<?php
namespace App\Integrations\Stripe;
final class StripeClient {
    public function __construct(private string $secretKey){}
    public function createCheckoutSession(array $lineItems, string $successUrl, string $cancelUrl): array {
        $payload = json_encode([
            'mode'=>'payment',
            'line_items'=>$lineItems,
            'success_url'=>$successUrl,
            'cancel_url'=>$cancelUrl,
        ]);
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>http_build_query([
                'mode'=>'payment',
                'success_url'=>$successUrl,
                'cancel_url'=>$cancelUrl,
                'line_items[0][price_data][currency]'=>'inr',
                'line_items[0][price_data][product_data][name]'=>$lineItems[0]['name']??'Order',
                'line_items[0][price_data][unit_amount]'=>$lineItems[0]['amount'],
                'line_items[0][quantity]'=>$lineItems[0]['quantity']??1,
            ]),
            CURLOPT_USERPWD=>$this->secretKey.':',
            CURLOPT_TIMEOUT=>20,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($status === 401) throw new \RuntimeException('Stripe authentication failed',401);
        $decoded = is_string($body) && $body !== '' ? json_decode($body,true) : null;
        if ($status >= 300 || !is_array($decoded)) {
            $message = $decoded['error']['message'] ?? $error ?: 'Stripe session creation failed';
            throw new \RuntimeException($message, $status ?: 500);
        }
        return $decoded;
    }
    public function retrieveSession(string $sessionId): array {
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/'.$sessionId);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>$this->secretKey.':',CURLOPT_TIMEOUT=>20]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 300) throw new \RuntimeException('Failed to retrieve Stripe session', $status);
        return json_decode($body,true) ?: [];
    }
}
