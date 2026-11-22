<?php
namespace App\Controllers;
use App\Services\{CartService,ProductService,SecretService,PaymentService,DatabaseService,MailQueueService,TaxService,SettingsService,RemoteCartService,RemoteCartException};
use App\Integrations\Razorpay\RazorpayClient;
use App\Integrations\Stripe\StripeClient;
final class CommerceController extends BaseController {
    public function __construct(private ?RemoteCartService $remoteCarts=null) {}

    /** The separate review capability never selects a customer account or mutates a cart on GET. */
    public function agentCheckout(): void {
        $this->agentCheckoutHeaders();
        $ticket=is_string($_GET['ticket']??null)?$_GET['ticket']:'';
        $this->renderAgentCheckout($ticket);
    }

    public function importAgentCheckout(): void {
        $this->agentCheckoutHeaders();
        $csrf=$_POST['_csrf']??null;$expected=$_SESSION['csrf_token']??null;
        if(!is_string($csrf)||!is_string($expected)||$csrf===''||!hash_equals($expected,$csrf)
            ||($_POST['confirm']??null)!=='replace') {
            $this->renderAgentCheckout('', 'Confirm cart replacement from a fresh review page before continuing.',419);return;
        }
        $ticket=is_string($_POST['ticket']??null)?$_POST['ticket']:'';
        $quote=is_string($_POST['quote']??null)?$_POST['quote']:'';
        $service=$this->remoteCarts??=new RemoteCartService();
        try {
            $binding=$this->agentCheckoutBinding();$preview=$service->previewCheckout($ticket,$binding);
            $receiptKey=hash('sha256',$ticket);$receipts=$_SESSION['agent_checkout_receipts']??[];
            if(!is_array($receipts))$receipts=[];
            $receipts=array_filter($receipts,static fn(mixed $expires):bool=>is_int($expires)&&$expires>time());
            // A repeated POST must not replace browser changes made after a successful import.
            // A DB claim without this session receipt is recoverable by the same browser.
            if(isset($receipts[$receiptKey])&&$preview['alreadyImported'])$this->redirect('/checkout');
            if(count($receipts)>=100)throw new RemoteCartException('checkout_limit','Too many checkout reviews in this browser. Continue with your current cart or try later.',429,900);
            $claimed=$service->claimCheckout($ticket,$binding,$quote);
            $_SESSION['cart']=$claimed['lines'];
            $receipts[$receiptKey]=strtotime($preview['expiresAt']);
            $_SESSION['agent_checkout_receipts']=$receipts;
            $this->redirect('/checkout');
        }catch(RemoteCartException $error){
            if($error->retryAfter>0)header('Retry-After: '.$error->retryAfter);
            $this->renderAgentCheckout($ticket,$error->getMessage(),$error->httpStatus);
        }
    }

    private function agentCheckoutHeaders():void {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    }

    private function agentCheckoutBinding():string {
        if(!is_string($_SESSION['agent_checkout_binding']??null)
            ||!preg_match('/^[a-f0-9]{64}$/D',$_SESSION['agent_checkout_binding'])) {
            $_SESSION['agent_checkout_binding']=bin2hex(random_bytes(32));
        }
        return hash('sha256',$_SESSION['agent_checkout_binding']);
    }

    private function renderAgentCheckout(string $ticket,?string $error=null,int $status=200):void {
        $preview=null;
        if($ticket!=='') {
            try{$preview=($this->remoteCarts??=new RemoteCartService())->previewCheckout($ticket,$this->agentCheckoutBinding());}
            catch(RemoteCartException $failure){$error??=$failure->getMessage();if($status===200)$status=$failure->httpStatus;}
        }elseif($error===null){$error='Open the complete checkout review link supplied with your cart.';$status=400;}
        $csrf=$_SESSION['csrf_token']??=bin2hex(random_bytes(16));
        $browserItemCount=array_sum(array_map(static fn(array $line):int=>max(0,(int)($line['qty']??0)),$_SESSION['cart']??[]));
        $alreadyImported=$preview!==null&&$preview['alreadyImported']
            &&(int)($_SESSION['agent_checkout_receipts'][hash('sha256',$ticket)]??0)>time();
        http_response_code($status);
        // Deliberately omit the analytics-enabled public layout on a capability URL.
        require app_path('views/public/agent-checkout.php');
    }

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
        if (!in_array($product['stock_status'] ?? '', ['in_stock', 'active'], true)) {
            if ($this->wantsJson()) $this->jsonResponse(['error' => 'This product is currently out of stock.'], 409);
            $this->flash('This product is currently out of stock.', 'error');
            $this->redirect('/shop');
        }
        if (empty($_SESSION['cart'])) $_SESSION['cart'] = [];
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if (($item['slug'] ?? '') === $slug) {
                $item['qty'] = min(99, (int)($item['qty'] ?? 1) + $qty);
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $_SESSION['cart'][] = ['slug' => $slug, 'qty' => $qty];
        }
        (new \App\Services\SiteTelemetryService())->record('cart_add', ['product'=>$slug]);
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
        $summary = TaxService::cartSummary($this->resolveCartItems(), (new SettingsService())->public());
        return ['slug' => $slug, 'quantity' => $quantity, 'cart_count' => $cartCount, 'summary' => $summary];
    }
    public function createOrder(): void {
        $this->isApiRequest = true;
        $this->validateCsrf();
        $phone = trim((string)($_POST['phone'] ?? ''));
        if (!\App\Services\AuthService::validPhone($phone)) {
            $this->jsonResponse(['error' => 'Enter a valid phone number for delivery before payment.'], 422);
        }
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
        (new \App\Services\SiteTelemetryService())->record('checkout_initiated');
        $shippingState = TaxService::normaliseState((string)($_POST['state'] ?? ''));
        if ($shippingState === null) {
            $this->jsonResponse(['error' => 'Select a valid delivery state before payment.'], 422);
        }
        $supplierSettings = (new SettingsService())->public();
        if (TaxService::normaliseState((string)($supplierSettings['gst_state'] ?? 'Tamil Nadu')) === null) {
            $this->jsonResponse(['error' => 'Checkout is temporarily unavailable. Please contact the store.'], 503);
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
                'customer_phone' => $phone,
                'shipping_address' => trim((string)($_POST['address'] ?? '')),
                'shipping_city' => trim((string)($_POST['city'] ?? '')),
                'shipping_pincode' => trim((string)($_POST['pincode'] ?? '')),
                'shipping_state' => $shippingState,
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
        $cartAmount = (int)round(max(0, $this->cartTotal($items) - $discount) * 100);
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
            'customer_phone' => $phone,
            'shipping_address' => trim((string)($_POST['address'] ?? '')),
            'shipping_city' => trim((string)($_POST['city'] ?? '')),
            'shipping_pincode' => trim((string)($_POST['pincode'] ?? '')),
            'shipping_state' => $shippingState,
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
            $this->jsonResponse(['verified' => false, 'error' => 'This browser no longer has the pending checkout. Check your account or order confirmation email, or contact support with the payment reference before making another payment.'], 400);
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
            // A bad callback proves neither payment failure nor that no charge occurred.
            $this->jsonResponse(['verified' => false, 'error' => 'Payment signature could not be verified. Keep the payment reference and check again or contact support.'], 400);
        }
        $razorpay = new RazorpayClient($secrets['razorpay_key_id'], $secrets['razorpay_key_secret']);
        try {
            $payment = $razorpay->fetchPayment($paymentId);
        } catch (\RuntimeException $e) {
            $this->jsonResponse(['verified' => false, 'error' => 'Failed to verify payment with gateway.'], 502);
        }
        $expectedPaise = (int)round(((float)($pendingOrder['total'] ?? 0)) * 100);
        if (!PaymentService::isCapturedRazorpayPayment($payment, $paymentId, $orderId, $expectedPaise)) {
            $this->jsonResponse(['verified' => false, 'payment_pending' => true,
                'error' => 'The gateway has not confirmed a captured INR payment matching this checkout. Keep the payment reference and check again; do not start another payment.'], 409);
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

    /** Browser adapter for the shared, session-independent verified-order boundary. */
    private function persistVerifiedOrder(array $pendingOrder, string $paymentId, array $gatewayFields, ?array $testPorts = null): string {
        $localId = (new \App\Services\VerifiedOrderService())->persist($pendingOrder, $paymentId, $gatewayFields, $testPorts);
        // A callback must not clear a different checkout or a later browser cart.
        if (isset($_SESSION['pending_order']) && $_SESSION['pending_order'] === $pendingOrder) {
            $canonicalLines = static function (array $items): array {
                $lines = array_map(static fn(array $item): array =>
                    ['slug'=>(string)($item['slug'] ?? ''), 'qty'=>(int)($item['qty'] ?? 0)], $items);
                usort($lines, static fn(array $a, array $b): int => $a['slug'] <=> $b['slug']);
                return $lines;
            };
            $expectedCart = $canonicalLines($pendingOrder['items'] ?? []);
            $currentCart = $canonicalLines($_SESSION['cart'] ?? []);
            unset($_SESSION['pending_order']);
            if ($expectedCart !== [] && $currentCart === $expectedCart) unset($_SESSION['cart']);
        }
        return $localId;
    }
}
