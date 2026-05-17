<?php
namespace App\Integrations\Razorpay;
final class RazorpayClient {
    public function __construct(private string $keyId, private string $keySecret){}
    public function createOrder(int $amountPaise, string $receipt): array {
        $payload = json_encode(['amount'=>$amountPaise,'currency'=>'INR','receipt'=>$receipt]);
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_USERPWD=>$this->keyId.':'.$this->keySecret]);
        $body = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($status >= 300 || !$body) throw new \RuntimeException('Razorpay order creation failed');
        return json_decode($body, true);
    }
}
