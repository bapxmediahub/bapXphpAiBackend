<?php
namespace App\Controllers;
use App\Services\{CartService,ProductService,SecretService,PaymentService,JsonStoreService};
use App\Integrations\Razorpay\RazorpayClient;
final class CommerceController extends BaseController {
    public function addToCart(): void {
        $slug = trim($_POST['slug'] ?? '');
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        if ($slug === '') {
            $this->flash('Invalid product.');
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
        $this->flash('Product added to cart.');
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
        if (!empty($_POST['qty']) && is_array($_POST['qty'])) {
            foreach ($_POST['qty'] as $slug => $qty) {
                $qty = max(1, (int)$qty);
                foreach ($_SESSION['cart'] as &$item) {
                    if (($item['slug'] ?? '') === $slug) {
                        $item['qty'] = $qty;
                        break;
                    }
                }
                unset($item);
            }
        }
        $this->redirect('/cart');
    }
    public function createOrder(): void {
        $secrets = (new SecretService())->all();
        if (empty($secrets['razorpay_key_id']) || empty($secrets['razorpay_key_secret'])) {
            $this->flash('Razorpay is not configured yet.');
            $this->redirect('/checkout');
        }
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount < 100) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid amount']);
            exit;
        }
        $receipt = 'order_' . bin2hex(random_bytes(5));
        $order = (new RazorpayClient($secrets['razorpay_key_id'], $secrets['razorpay_key_secret']))->createOrder($amount, $receipt);
        header('Content-Type: application/json');
        echo json_encode($order);
    }
    public function verifyPayment(): void {
        $secrets = (new SecretService())->all();
        $ok = (new PaymentService($secrets['razorpay_key_secret'] ?? ''))->verifySignature(
            $_POST['order_id'] ?? '',
            $_POST['payment_id'] ?? '',
            $_POST['signature'] ?? ''
        );
        if ($ok) {
            $items = $this->resolveCartItems();
            $total = $this->cartTotal($items);
            $order = [
                'id' => bin2hex(random_bytes(8)),
                'status' => 'confirmed',
                'total' => $total,
                'customer_email' => $_SESSION['user']['email'] ?? ($_POST['email'] ?? 'guest'),
                'customer_name' => $_SESSION['user']['name'] ?? '',
                'items' => array_map(fn($i) => ['name' => $i['name'], 'qty' => $i['qty'], 'line_total' => $i['line_total']], $items),
                'payment_id' => $_POST['payment_id'] ?? '',
                'created_at' => date('c'),
            ];
            (new JsonStoreService())->upsert('orders', $order);
            $_SESSION['cart'] = [];
        }
        header('Content-Type: application/json');
        echo json_encode(['verified' => $ok]);
    }
}
