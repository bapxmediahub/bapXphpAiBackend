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
        // These are reached by fetch() from the product and shop pages. Redirecting
        // returns the HTML of /shop, which the caller tries to parse as JSON and fails
        // with "Unexpected token '<'" — so an out-of-stock product looked like a broken
        // site instead of a clear message.
        if ($slug === '') {
            if ($this->wantsJson()) $this->jsonResponse(['error' => 'Invalid product.'], 422);
            $this->flash('Invalid product.','error');
            $this->redirect('/shop');
        }
        $product = (new ProductService())->findBySlug($slug);
        if (!$product) {
            if ($this->wantsJson()) $this->jsonResponse(['error' => 'That product is no longer available.'], 404);
            $this->flash('That product is no longer available.','error');
            $this->redirect('/shop');
        }
        if (($product['stock_status'] ?? '') !== 'in_stock') {
            if ($this->wantsJson()) $this->jsonResponse(['error' => 'This product is currently out of stock.'], 409);
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
        if ($this->wantsJson()) $this->jsonResponse($this->cartState($slug));
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
        // Hidden products resolve to null here, so a product taken off the shop cannot
        // be checked out by anyone still holding a cart or a link.
        $products = (new ProductService())->bySlug();
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
            $successUrl = rtrim((string)($_ENV['APP_URL'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/') . '/payment/stripe/return?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = rtrim((string)($_ENV['APP_URL'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/') . '/checkout';
            try {
                $stripeSession = (new StripeClient($secrets['stripe_secret_key']))->createCheckoutSession($lineItems, $successUrl, $cancelUrl);
            } catch (\RuntimeException $exception) {
                $status = $exception->getCode() === 401 ? 401 : 500;
                $this->jsonResponse(['error' => $exception->getMessage()], $status);
            }
            // Held in the session, not written, until the payment is verified — exactly
            // as the Razorpay path does. Writing here registered an order the moment
            // the customer was sent to the gateway, so every abandoned or cancelled
            // checkout left a permanent "Pending" row in the admin with a real total
            // against a customer who never paid.
            $orderId = bin2hex(random_bytes(8));
            $_SESSION['pending_order'] = [
                'id' => $orderId,
                'total' => $cartTotal,
                'customer_email' => $_SESSION['user']['email'] ?? ($_POST['email'] ?? 'guest'),
                'customer_name' => trim((string)($_POST['name'] ?? ($_SESSION['user']['name'] ?? ''))),
                'customer_phone' => trim((string)($_POST['phone'] ?? '')),
                'shipping_address' => trim((string)($_POST['address'] ?? '')),
                'shipping_city' => trim((string)($_POST['city'] ?? '')),
                'shipping_pincode' => trim((string)($_POST['pincode'] ?? '')),
                'shipping_state' => trim((string)($_POST['state'] ?? '')),
                'customer_gstin' => trim((string)($_POST['customer_gstin'] ?? '')),
                'items' => array_map(fn($i) => ['slug' => $i['slug'], 'name' => $i['name'], 'qty' => $i['qty'], 'line_total' => $i['line_total']], $items),
                'stripe_session_id' => $stripeSession['id'] ?? '',
                'coupon_code' => '',
                'discount' => 0,
            ];
            $this->jsonResponse([
                'stripe_url' => $stripeSession['url'] ?? '',
                'order_id' => $orderId,
            ]);
            return;
        }
        if (!(new SecretService())->razorpayReadyForCurrentHost($secrets)) {
            $this->jsonResponse(['error' => 'Razorpay ' . ($secrets['razorpay_mode'] ?? 'selected') . ' mode is not configured yet.'], 401);
        }
        // Every coupon rule lives in CouponService. This used to check only whether the
        // code was active, so a posted promo code never expired and had no spend or
        // usage limit; and the percentage branch compared rupees against a percentage,
        // paying 25% off a ₹2000 cart as ₹25. A refusal is now told to the shopper
        // rather than silently applying no discount and charging full price.
        $discount = 0;
        $couponCode = trim($_POST['coupon_code'] ?? '');
        if ($couponCode !== '') {
            try {
                $applied = (new \App\Services\CouponService($store))->apply(
                    $couponCode,
                    (float)$this->cartTotal($items),
                    (string)($_SESSION['user']['email'] ?? ($_POST['email'] ?? ''))
                );
                $discount = $applied['discount'];
            } catch (\InvalidArgumentException $e) {
                $this->jsonResponse(['error' => $e->getMessage()], 422);
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
        $_SESSION['pending_order'] = [
            'razorpay_order_id' => $order['id'] ?? '',
            'total' => $amount / 100,
            'items' => array_map(fn($i) => ['slug' => $i['slug'], 'name' => $i['name'], 'qty' => $i['qty'], 'line_total' => $i['line_total']], $items),
            'coupon_code' => $couponCode,
            'discount' => $discount,
            'customer_email' => $_SESSION['user']['email'] ?? ($_POST['email'] ?? 'guest'),
            'customer_name' => trim((string)($_POST['name'] ?? ($_SESSION['user']['name'] ?? ''))),
            'customer_phone' => trim((string)($_POST['phone'] ?? '')),
            'shipping_address' => trim((string)($_POST['address'] ?? '')),
            'shipping_city' => trim((string)($_POST['city'] ?? '')),
            'shipping_pincode' => trim((string)($_POST['pincode'] ?? '')),
            'shipping_state' => trim((string)($_POST['state'] ?? '')),
            'customer_gstin' => trim((string)($_POST['customer_gstin'] ?? '')),
        ];
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
        $pendingOrder = $_SESSION['pending_order'] ?? null;
        if (!$pendingOrder || ($pendingOrder['razorpay_order_id'] ?? '') === '') {
            $this->jsonResponse(['verified' => false, 'error' => 'No pending order found. Please start checkout again.'], 400);
        }
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
        if ($orderId !== ($pendingOrder['razorpay_order_id'] ?? '')) {
            $this->jsonResponse(['verified' => false, 'error' => 'Order ID mismatch.'], 400);
        }
        $ok = (new PaymentService($secrets['razorpay_key_secret'] ?? ''))->verifySignature(
            $orderId,
            $paymentId,
            $signature
        );
        if (!$ok) {
            // Keep the cart: the customer has not been charged and should be able to
            // retry without rebuilding their order.
            try { (new MailQueueService())->enqueuePaymentFailure($pendingOrder, 'Payment signature could not be verified.'); }
            catch (\Throwable $e) { error_log('Payment failure mail failed: ' . $e->getMessage()); }
            unset($_SESSION['pending_order']);
            $this->jsonResponse(['verified' => false, 'error' => 'Payment signature mismatch.'], 400);
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
            unset($_SESSION['pending_order']);
            $this->jsonResponse(['verified' => false, 'error' => 'Payment amount mismatch.'], 400);
        }
        try {
            $localId = $this->persistVerifiedOrder($pendingOrder, $paymentId, [
                'razorpay_order_id' => $pendingOrder['razorpay_order_id'],
            ]);
        } catch (\Throwable $error) {
            error_log('[order-persistence-failed] payment_id=' . $paymentId . ' error=' . $error->getMessage());
            $this->jsonResponse([
                'verified' => false,
                'payment_verified' => true,
                'error' => 'Payment was verified, but the order could not be saved. Your cart has been preserved. Contact support with payment ID ' . $paymentId . '.',
            ], 503);
        }
        $this->jsonResponse(['verified' => true, 'order_id' => $localId]);
    }

    /** Stripe's hosted page returns here; success is never trusted from the URL alone. */
    public function completeStripe(): void {
        $sessionId = trim((string)($_GET['session_id'] ?? ''));
        if ($sessionId === '') {
            $this->flash('The payment result was incomplete. Your cart is still available.', 'error');
            $this->redirect('/checkout');
        }

        $db = new DatabaseService();
        foreach ($db->read('orders') as $existing) {
            if (($existing['stripe_session_id'] ?? '') === $sessionId) {
                $this->redirect('/account/orders/' . rawurlencode((string)$existing['id']) . '?placed=1');
            }
        }

        $pendingOrder = $_SESSION['pending_order'] ?? null;
        if (!$pendingOrder || !hash_equals((string)($pendingOrder['stripe_session_id'] ?? ''), $sessionId)) {
            $this->flash('We could not match this payment to your checkout. Your cart has been preserved.', 'error');
            $this->redirect('/checkout');
        }

        $secrets = (new SecretService())->all();
        try {
            $session = (new StripeClient((string)($secrets['stripe_secret_key'] ?? '')))->retrieveSession($sessionId);
        } catch (\RuntimeException $error) {
            $this->flash('We could not verify the payment with Stripe yet. Please try again.', 'error');
            $this->redirect('/checkout');
        }

        $expected = (int)round(((float)($pendingOrder['total'] ?? 0)) * 100);
        $paid = ($session['payment_status'] ?? '') === 'paid'
            && (int)($session['amount_total'] ?? 0) === $expected
            && strtolower((string)($session['currency'] ?? '')) === 'inr';
        if (!$paid) {
            try { (new MailQueueService())->enqueuePaymentFailure($pendingOrder, 'Stripe did not confirm a paid session.'); }
            catch (\Throwable $error) { error_log('Stripe failure mail failed: ' . $error->getMessage()); }
            $this->flash('Your payment was not confirmed. Your cart is unchanged, so you can try again.', 'error');
            $this->redirect('/checkout');
        }

        $paymentId = trim((string)($session['payment_intent'] ?? '')) ?: $sessionId;
        try {
            $localId = $this->persistVerifiedOrder($pendingOrder, $paymentId, ['stripe_session_id' => $sessionId]);
        } catch (\Throwable $error) {
            error_log('[stripe-order-persistence-failed] session_id=' . $sessionId . ' error=' . $error->getMessage());
            $this->flash('Payment was verified, but the order could not be saved. Your cart is preserved. Contact support with Stripe session ' . $sessionId . '.', 'error');
            $this->redirect('/checkout');
        }
        $this->redirect('/account/orders/' . rawurlencode($localId) . '?placed=1');
    }

    /** Persist either gateway through one idempotent, tax-aware order boundary. */
    private function persistVerifiedOrder(array $pendingOrder, string $paymentId, array $gatewayFields): string {
        $db = new DatabaseService();
        $existingOrders = $db->read('orders');
        foreach ($existingOrders as $existing) {
            if (($existing['payment_id'] ?? '') === $paymentId) return (string)$existing['id'];
            if (!empty($gatewayFields['stripe_session_id']) && ($existing['stripe_session_id'] ?? '') === $gatewayFields['stripe_session_id']) {
                return (string)$existing['id'];
            }
        }

        $orderItems = $pendingOrder['items'] ?? [];
        $products = (new ProductService())->bySlug();
        foreach ($orderItems as $item) {
            $product = $products[$item['slug'] ?? ''] ?? null;
            if (!in_array($product['stock_status'] ?? '', ['in_stock', 'active'], true)) {
                throw new \RuntimeException(($item['name'] ?? 'A product') . ' is no longer available.');
            }
        }

        $itemsWithRates = array_map(function (array $item) use ($products): array {
            $product = $products[$item['slug'] ?? ''] ?? [];
            $item['gst_rate'] = (float)($product['gst_rate'] ?? 0);
            $item['hsn_code'] = (string)($product['hsn_code'] ?? '');
            $item['unit_price'] = (float)($item['line_total'] ?? 0) / max(1, (int)($item['qty'] ?? 1));
            return $item;
        }, $orderItems);
        $settings = (new SettingsService())->public();
        $tax = (new TaxService())->snapshot($itemsWithRates, 0, (string)($pendingOrder['shipping_state'] ?? ''), $settings);
        $invoice = (new TaxService())->nextInvoice($existingOrders);
        $localId = bin2hex(random_bytes(8));
        $order = array_merge([
            'id' => $localId,
            'status' => 'confirmed',
            'total' => $pendingOrder['total'],
            'payment_id' => $paymentId,
            'payment_email_status' => 'pending',
            'customer_email' => $pendingOrder['customer_email'],
            'customer_name' => $pendingOrder['customer_name'],
            'customer_phone' => $pendingOrder['customer_phone'],
            'shipping_address' => $pendingOrder['shipping_address'],
            'shipping_city' => $pendingOrder['shipping_city'],
            'shipping_pincode' => $pendingOrder['shipping_pincode'],
            'shipping_state' => $pendingOrder['shipping_state'] ?? '',
            'items' => $orderItems,
            'coupon_code' => $pendingOrder['coupon_code'] ?? '',
            'discount' => $pendingOrder['discount'] ?? 0,
            'tax_lines' => $tax['tax_lines'],
            'taxable_value' => $tax['taxable_value'],
            'cgst_total' => $tax['cgst_total'],
            'sgst_total' => $tax['sgst_total'],
            'igst_total' => $tax['igst_total'],
            'tax_total' => $tax['tax_total'],
            'supply_type' => $tax['supply_type'],
            'place_of_supply' => $tax['place_of_supply'],
            'supplier' => $tax['supplier'],
            'customer_gstin' => $pendingOrder['customer_gstin'] ?? '',
            'invoice_sequence' => $invoice['invoice_sequence'],
            'invoice_financial_year' => $invoice['invoice_financial_year'],
            'invoice_number' => $invoice['invoice_number'],
            'invoice_date' => $invoice['invoice_date'],
            'created_at' => date('c'),
        ], $gatewayFields);
        $db->upsert('orders', $order);
        (new MailQueueService())->enqueuePaymentConfirmation($order);
        unset($_SESSION['pending_order'], $_SESSION['cart']);
        return $localId;
    }
}
