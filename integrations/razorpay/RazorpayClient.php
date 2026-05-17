<?php
namespace App\Integrations\Razorpay;
final class RazorpayClient { public function __construct(private string $keyId, private string $keySecret){} public function orderPayload(int $amountPaise, string $receipt): array{return ['amount'=>$amountPaise,'currency'=>'INR','receipt'=>$receipt];} }
