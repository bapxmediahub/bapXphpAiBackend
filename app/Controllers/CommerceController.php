<?php
namespace App\Controllers;
use App\Services\{CartService,ProductService,SecretService,PaymentService,JsonStoreService,MailQueueService};
use App\Integrations\Razorpay\RazorpayClient;
final class CommerceController extends BaseController {
    public function addToCart(): void {
        $slug = trim($_POST['slug'] ?? '');
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        if ($slug === '') {
            $this->flash('Invalid product.','error');
            $this->redirect('/shop');
        }
        if (empty($_SESSION['cart'])) $_SESSION['cart'] = [];
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if (($item['slug'] ?? '') === $slug) {
                $item['qty'] = (int)($item['qty'] ?? 1) + $qty;
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $_SESSION['cart'][] = ['slug' => $slug, 'qty' => $qty];
        }
        $this->flash('Product added to cart.','success');
        $redirect = $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/shop';
        $this->redirect($redirect);
    }
    public function removeFromCart(): void {
        $slug = trim($_POST['slug'] ?? '');
        if (!empty($_SESSION['cart'])) {
            $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($item) => ($item['slug'] ?? '') !== $slug));
        }
        $this->redirect('/cart');
    }
    public function updateCart(): void {
        $slug = trim($_POST['slug'] ?? '');
        $action = $_POST['action'] ?? '';
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as &$item) {
                if (($item['slug'] ?? '') === $slug) {
                    if ($action === 'inc') {
                        $item['qty'] = (int)($item['qty'] ?? 1) + 1;
                    } elseif ($action === 'dec') {
                        $item['qty'] = max(1, (int)($item['qty'] ?? 1) - 1);
                    }
                    break;
                }
            }
            unset($item);
        }
        $this->redirect('/cart');
    }
    public function createOrder(): void {
        $secrets = (new SecretService())->all();
        if (empty($secrets['razorpay_key_id']) || empty($secrets['razorpay_key_secret'])) {
            $this->jsonResponse(['error' => 'Razorpay is not configured yet.'], 401);
        }
        $items = $this->resolveCartItems();
        $cartAmount = $this->cartTotal($items) * 100;
        $amount = $cartAmount > 0 ? $cartAmount : (int)($_POST['amount'] ?? 0);
        if ($amount < 100) {
            $this->jsonResponse(['error' => 'Amount must be at least 100 paise.'], 400);
        }
        $receipt = 'order_' . bin2hex(random_bytes(5));
        try {
            $order = (new RazorpayClient($secrets['razorpay_key_id'], $secrets['razorpay_key_secret']))->createOrder($amount, $receipt);
        } catch (\RuntimeException $exception) {
            $status = $exception->getCode() === 401 ? 401 : 500;
            $this->jsonResponse(['error' => $exception->getMessage()], $status);
        }
        $this->jsonResponse([
            'id' => $order['id'] ?? '',
            'order_id' => $order['id'] ?? '',
            'amount' => (int)($order['amount'] ?? $amount),
            'currency' => (string)($order['currency'] ?? 'INR'),
        ]);
    }
    public function verifyPayment(): void {
        $secrets = (new SecretService())->all();
        $orderId = (string)($_POST['razorpay_order_id'] ?? $_POST['order_id'] ?? '');
        $paymentId = (string)($_POST['razorpay_payment_id'] ?? $_POST['payment_id'] ?? '');
        $signature = (string)($_POST['razorpay_signature'] ?? $_POST['signature'] ?? '');
        if ($orderId === '' || $paymentId === '' || $signature === '') {
            $this->jsonResponse(['verified' => false, 'error' => 'Missing Razorpay payment verification fields.'], 400);
        }
        $ok = (new PaymentService($secrets['razorpay_key_secret'] ?? ''))->verifySignature(
            $orderId,
            $paymentId,
            $signature
        );
        if (!$ok) {
            $this->jsonResponse(['verified' => false, 'error' => 'Payment signature mismatch.'], 400);
        }
        $items = $this->resolveCartItems();
        $total = $this->cartTotal($items);
        $order = [
            'id' => bin2hex(random_bytes(8)),
            'status' => 'confirmed',
            'total' => $total,
            'customer_email' => $_SESSION['user']['email'] ?? ($_POST['email'] ?? 'guest'),
            'customer_name' => trim((string)($_POST['name'] ?? ($_SESSION['user']['name'] ?? ''))),
            'customer_phone' => trim((string)($_POST['phone'] ?? '')),
            'shipping_address' => trim((string)($_POST['address'] ?? '')),
            'shipping_city' => trim((string)($_POST['city'] ?? '')),
            'shipping_pincode' => trim((string)($_POST['pincode'] ?? '')),
            'items' => array_map(fn($i) => ['slug' => $i['slug'], 'name' => $i['name'], 'qty' => $i['qty'], 'line_total' => $i['line_total']], $items),
            'razorpay_order_id' => $orderId,
            'payment_id' => $paymentId,
            'payment_email_status' => 'pending',
            'review_request_after_at' => null,
            'created_at' => date('c'),
        ];
        (new JsonStoreService())->upsert('orders', $order);
        (new MailQueueService())->enqueuePaymentConfirmation($order);
        $_SESSION['cart'] = [];
        $this->jsonResponse(['verified' => true, 'order_id' => $order['id']]);
    }
}
