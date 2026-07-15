<?php
namespace App\Controllers;
use App\Services\{CartService,ProductService,SecretService,PaymentService,DatabaseService,MailQueueService,TaxService,SettingsService};
use App\Integrations\Razorpay\RazorpayClient;
use App\Integrations\Stripe\StripeClient;
final class CommerceController extends BaseController {
    public function addToCart(): void {
        $this->validateCsrf();
        $slug = trim($_POST['slug'] ?? '');
        $qty = max(1, min(99, (int)($_POST['qty'] ?? 1)));
        if ($slug === '') {
            $this->flash('Invalid product.','error');
            $this->redirect('/shop');
        }
        $product = (new ProductService())->findBySlug($slug);
        if (!$product || ($product['stock_status'] ?? '') !== 'in_stock') {
            $this->flash('This product is currently out of stock.', 'error');
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
        if ($this->wantsJson()) $this->jsonResponse($this->cartState($slug));
        $this->flash('Product added to cart.','success');
        $redirect = $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/shop';
        $this->redirect($redirect);
    }
    public function removeFromCart(): void {
        $this->validateCsrf();
        $slug = trim($_POST['slug'] ?? '');
        if (!empty($_SESSION['cart'])) {
            $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($item) => ($item['slug'] ?? '') !== $slug));
        }
        $this->redirect('/cart');
    }
    public function updateCart(): void {
        $this->validateCsrf();
        $slug = trim($_POST['slug'] ?? '');
        $action = $_POST['action'] ?? '';
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as &$item) {
                if (($item['slug'] ?? '') === $slug) {
                    if ($action === 'inc') {
                        $item['qty'] = min(99, (int)($item['qty'] ?? 1) + 1);
                    } elseif ($action === 'dec') {
                        $item['qty'] = max(0, (int)($item['qty'] ?? 1) - 1);
                    }
                    break;
                }
            }
            unset($item);
            $_SESSION['cart'] = array_values(array_filter(
                $_SESSION['cart'],
                fn($item) => (int)($item['qty'] ?? 0) > 0
            ));
        }
        if ($this->wantsJson()) {
            $this->jsonResponse($this->cartState($slug));
        }
        $redirect = $_POST['redirect'] ?? '/cart';
        $this->redirect($redirect);
    }

    private function wantsJson(): bool {
        return str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    private function cartState(string $slug): array {
        $quantity = 0;
        $cartCount = 0;
        foreach ($_SESSION['cart'] ?? [] as $item) {
            $itemQty = (int)($item['qty'] ?? 0);
            $cartCount += $itemQty;
            if (($item['slug'] ?? '') === $slug) $quantity = $itemQty;
        }
        return ['slug' => $slug, 'quantity' => $quantity, 'cart_count' => $cartCount];
    }
    public function createOrder(): void {
        $this->isApiRequest = true;
        $this->validateCsrf();
        $secrets = (new SecretService())->all();
        $items = $this->resolveCartItems();
        if (empty($items)) {
            $this->jsonResponse(['error' => 'Cart is empty or products are unavailable.'], 400);
        }
        $store = new DatabaseService();
        $products = [];
        foreach ($store->read('products') as $p) { $products[$p['slug'] ?? ''] = $p; }
        foreach ($items as $item) {
            $product = $products[$item['slug']] ?? null;
            $status = $product['stock_status'] ?? '';
            if (!in_array($status, ['in_stock', 'active'], true)) {
                $this->jsonResponse(['error' => e($item['name']) . ' is currently unavailable.'], 400);
            }
        }
        $paymentMethod = trim($_POST['payment_method'] ?? 'razorpay');
        if (!empty($_SESSION['user']['email']) && !empty($_POST['save_address']) && trim((string)($_POST['address_name'] ?? '')) !== '') {
            (new \App\Services\AddressService())->save($_SESSION['user']['email'], $_POST);
        }
        if ($paymentMethod === 'stripe') {
            if (empty($secrets['stripe_secret_key'])) {
                $this->jsonResponse(['error' => 'Stripe payment gateway is not configured.'], 401);
            }
            $cartTotal = $this->cartTotal($items);
            $lineItems = [[
                'name' => 'Sri Panchami Spiritual Order',
                'amount' => (int)round($cartTotal * 100),
                'quantity' => 1,
            ]];
            $successUrl = rtrim((string)($_ENV['APP_URL'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/') . '/account/orders?stripe_session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = rtrim((string)($_ENV['APP_URL'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/') . '/checkout';
            try {
                $stripeSession = (new StripeClient($secrets['stripe_secret_key']))->createCheckoutSession($lineItems, $successUrl, $cancelUrl);
            } catch (\RuntimeException $exception) {
                $status = $exception->getCode() === 401 ? 401 : 500;
                $this->jsonResponse(['error' => $exception->getMessage()], $status);
            }
            $orderId = bin2hex(random_bytes(8));
            $store->upsert('orders', [
                'id' => $orderId,
                'status' => 'pending',
                'total' => $cartTotal,
                'customer_email' => $_SESSION['user']['email'] ?? ($_POST['email'] ?? 'guest'),
                'customer_name' => trim((string)($_POST['name'] ?? ($_SESSION['user']['name'] ?? ''))),
                'customer_phone' => trim((string)($_POST['phone'] ?? '')),
                'shipping_address' => trim((string)($_POST['address'] ?? '')),
                'shipping_city' => trim((string)($_POST['city'] ?? '')),
                'shipping_pincode' => trim((string)($_POST['pincode'] ?? '')),
                'items' => array_map(fn($i) => ['slug' => $i['slug'], 'name' => $i['name'], 'qty' => $i['qty'], 'line_total' => $i['line_total']], $items),
                'stripe_session_id' => $stripeSession['id'] ?? '',
                'created_at' => date('c'),
            ]);
            $this->jsonResponse([
                'stripe_url' => $stripeSession['url'] ?? '',
                'order_id' => $orderId,
            ]);
            return;
        }
        if (!(new SecretService())->razorpayReadyForCurrentHost($secrets)) {
            $this->jsonResponse(['error' => 'Razorpay ' . ($secrets['razorpay_mode'] ?? 'selected') . ' mode is not configured yet.'], 401);
        }
        $discount = 0;
        $couponCode = trim($_POST['coupon_code'] ?? '');
        if ($couponCode !== '') {
            $coupons = $store->read('coupons');
            foreach ($coupons as $c) {
                if (strcasecmp($c['code'] ?? '', $couponCode) === 0) {
                    if (($c['active'] ?? false) || ($c['status'] ?? '') === 'active') {
                        $discountValue = (float)($c['discount_value'] ?? 0);
                        if (($c['discount_type'] ?? '') === 'percentage') {
                            $discount = min($this->cartTotal($items) * $discountValue / 100, $discountValue);
                        } else {
                            $discount = $discountValue;
                        }
                        $discount = min($discount, $this->cartTotal($items));
                    }
                    break;
                }
            }
        }
        $cartAmount = max(0, $this->cartTotal($items) - $discount) * 100;
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
        $store->upsert('orders', [
            'id' => bin2hex(random_bytes(8)),
            'status' => 'pending',
            'total' => $amount / 100,
            'customer_email' => $_SESSION['user']['email'] ?? ($_POST['email'] ?? 'guest'),
            'customer_name' => trim((string)($_POST['name'] ?? ($_SESSION['user']['name'] ?? ''))),
            'customer_phone' => trim((string)($_POST['phone'] ?? '')),
            'shipping_address' => trim((string)($_POST['address'] ?? '')),
            'shipping_city' => trim((string)($_POST['city'] ?? '')),
            'shipping_pincode' => trim((string)($_POST['pincode'] ?? '')),
            'items' => array_map(fn($i) => ['slug' => $i['slug'], 'name' => $i['name'], 'qty' => $i['qty'], 'line_total' => $i['line_total']], $items),
            'razorpay_order_id' => $order['id'] ?? '',
            'created_at' => date('c'),
        ]);
        $this->jsonResponse([
            'id' => $order['id'] ?? '',
            'order_id' => $order['id'] ?? '',
            'amount' => (int)($order['amount'] ?? $amount),
            'currency' => (string)($order['currency'] ?? 'INR'),
        ]);
    }

    public function verifyPayment(): void {
        $this->isApiRequest = true;
        $this->validateCsrf();
        $secrets = (new SecretService())->all();
        if (!(new SecretService())->razorpayReadyForCurrentHost($secrets)) {
            $this->jsonResponse(['verified' => false, 'error' => 'Razorpay ' . ($secrets['razorpay_mode'] ?? 'selected') . ' mode is not configured yet.'], 400);
        }
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
        $db = new DatabaseService();
        $pendingOrder = $db->find('orders', $orderId, 'razorpay_order_id');
        if (!$pendingOrder || ($pendingOrder['status'] ?? '') !== 'pending') {
            $this->jsonResponse(['verified' => false, 'error' => 'Order not found or already processed.'], 400);
        }
        $razorpay = new RazorpayClient($secrets['razorpay_key_id'], $secrets['razorpay_key_secret']);
        try {
            $payment = $razorpay->fetchPayment($paymentId);
        } catch (\RuntimeException $e) {
            $this->jsonResponse(['verified' => false, 'error' => 'Failed to verify payment with gateway.'], 502);
        }
        $expectedPaise = (int)round(((float)($pendingOrder['total'] ?? 0)) * 100);
        $actualPaise = (int)($payment['amount'] ?? 0);
        if ($actualPaise !== $expectedPaise || (string)($payment['order_id'] ?? '') !== $orderId) {
            $this->jsonResponse(['verified' => false, 'error' => 'Payment amount mismatch.'], 400);
        }
        $orderItems = $pendingOrder['items'] ?? [];
        $products = [];
        foreach ($db->read('products') as $p) { $products[$p['slug'] ?? ''] = $p; }
        foreach ($orderItems as $oi) {
            $product = $products[$oi['slug'] ?? ''] ?? null;
            $status = $product['stock_status'] ?? '';
            if (!in_array($status, ['in_stock', 'active'], true)) {
                $this->jsonResponse(['verified' => false, 'error' => ($oi['name'] ?? 'A product') . ' is no longer available.'], 400);
            }
        }
        $existingOrders = $db->read('orders');
        foreach ($existingOrders as $existing) {
            if (($existing['payment_id'] ?? '') === $paymentId && ($existing['id'] ?? '') !== ($pendingOrder['id'] ?? '')) {
                $this->jsonResponse(['verified' => false, 'error' => 'Payment already processed.'], 400);
            }
        }
        $settings = (new SettingsService())->public();
        $itemsWithRates = array_map(function ($item) use ($products) {
            $product = $products[$item['slug'] ?? ''] ?? [];
            $item['gst_rate'] = (float)($product['gst_rate'] ?? 0);
            $item['hsn_code'] = (string)($product['hsn_code'] ?? '');
            $item['unit_price'] = (float)($item['line_total'] ?? 0) / max(1, (int)($item['qty'] ?? 1));
            return $item;
        }, $orderItems);
        $shippingState = trim((string)($pendingOrder['shipping_state'] ?? ''));
        $taxSnapshot = (new TaxService())->snapshot($itemsWithRates, 0, $shippingState, $settings);
        $allOrders = $db->read('orders');
        $invoice = (new TaxService())->nextInvoice($allOrders);
        $order = array_merge($pendingOrder, [
            'status' => 'confirmed',
            'payment_id' => $paymentId,
            'payment_email_status' => 'pending',
            'tax_lines' => $taxSnapshot['tax_lines'],
            'taxable_value' => $taxSnapshot['taxable_value'],
            'cgst_total' => $taxSnapshot['cgst_total'],
            'sgst_total' => $taxSnapshot['sgst_total'],
            'igst_total' => $taxSnapshot['igst_total'],
            'tax_total' => $taxSnapshot['tax_total'],
            'supply_type' => $taxSnapshot['supply_type'],
            'place_of_supply' => $taxSnapshot['place_of_supply'],
            'supplier' => $taxSnapshot['supplier'],
            'customer_gstin' => trim((string)($_POST['customer_gstin'] ?? '')),
            'invoice_sequence' => $invoice['invoice_sequence'],
            'invoice_financial_year' => $invoice['invoice_financial_year'],
            'invoice_number' => $invoice['invoice_number'],
            'invoice_date' => $invoice['invoice_date'],
        ]);
        $db->upsert('orders', $order);
        (new MailQueueService())->enqueuePaymentConfirmation($order);
        $_SESSION['cart'] = [];
        $this->jsonResponse(['verified' => true, 'order_id' => $order['id']]);
    }
}
