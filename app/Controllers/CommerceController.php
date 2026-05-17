<?php
namespace App\Controllers;
use App\Services\{CartService,ProductService,SecretService,PaymentService,JsonStoreService};
use App\Integrations\Razorpay\RazorpayClient;
final class CommerceController extends BaseController {
 public function addToCart(): void{$_SESSION['cart'][]=['slug'=>$_POST['slug']??'','qty'=>(int)($_POST['qty']??1)];$this->redirect('/cart');}
 public function createOrder(): void{
  $secrets=(new SecretService())->all(); if(empty($secrets['razorpay_key_id'])||empty($secrets['razorpay_key_secret'])){$this->flash('Razorpay is not configured yet.');$this->redirect('/checkout');}
  $amount=(int)($_POST['amount']??0); $receipt='order_'.bin2hex(random_bytes(5)); $order=(new RazorpayClient($secrets['razorpay_key_id'],$secrets['razorpay_key_secret']))->createOrder($amount,$receipt); header('Content-Type: application/json'); echo json_encode($order);
 }
 public function verifyPayment(): void{ $secrets=(new SecretService())->all(); $ok=(new PaymentService($secrets['razorpay_key_secret']??''))->verifySignature($_POST['order_id']??'',$_POST['payment_id']??'',$_POST['signature']??''); header('Content-Type: application/json'); echo json_encode(['verified'=>$ok]); }
}
