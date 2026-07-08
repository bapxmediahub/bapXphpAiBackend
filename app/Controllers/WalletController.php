<?php
namespace App\Controllers;
use App\Services\{AuthService,SecretService,WalletService,PaymentService};
use App\Integrations\Razorpay\RazorpayClient;

final class WalletController extends BaseController {
    public function legacyShow(): void {
        (new AuthService())->requireUser();
        $amount = max(10, (int)($_GET['amount'] ?? 100));
        $this->redirect('/account/dashboard/wallet?amount=' . $amount);
    }

    public function show(): void {
        (new AuthService())->requireUser();
        $this->seoKey = 'account';
        $wallet = new WalletService();
        $email = $_SESSION['user']['email'] ?? '';
        $balance = $wallet->balanceFor($email);
        $quote = $wallet->quoteTopUp((int)($_GET['amount'] ?? 100));
        $secrets = (new SecretService())->all();
        $this->render('account/wallet', compact('balance', 'quote', 'secrets'));
    }

    public function createOrder(): void {
        (new AuthService())->requireUser();
        $secrets = (new SecretService())->all();
        $amountRupees = (int)($_POST['amount_rupees'] ?? 0);
        $quote = (new WalletService())->quoteTopUp($amountRupees);
        if (empty($secrets['razorpay_key_id']) || empty($secrets['razorpay_key_secret'])) {
            $this->jsonResponse(['error' => 'Razorpay ' . ($secrets['razorpay_mode'] ?? 'selected') . ' mode is not configured yet.'], 400);
        }
        try {
            $order = (new RazorpayClient($secrets['razorpay_key_id'], $secrets['razorpay_key_secret']))->createOrder($quote['total_paise'], 'wallet_' . bin2hex(random_bytes(5)));
        } catch (\RuntimeException $exception) {
            $status = $exception->getCode() === 401 ? 401 : 500;
            $this->jsonResponse(['error' => $exception->getMessage()], $status);
        }
        $_SESSION['_wallet_pending'][$order['id'] ?? ''] = ['amount_rupees' => $amountRupees, 'total_paise' => $quote['total_paise']];
        $this->jsonResponse([
            'id' => $order['id'] ?? '',
            'order_id' => $order['id'] ?? '',
            'amount' => (int)($order['amount'] ?? $quote['total_paise']),
            'currency' => (string)($order['currency'] ?? 'INR'),
            'quote' => $quote,
        ]);
    }

    public function verify(): void {
        (new AuthService())->requireUser();
        $secrets = (new SecretService())->all();
        if (empty($secrets['razorpay_key_secret'])) {
            $this->jsonResponse(['verified' => false, 'error' => 'Razorpay ' . ($secrets['razorpay_mode'] ?? 'selected') . ' mode is not configured yet.'], 400);
        }
        $orderId = (string)($_POST['razorpay_order_id'] ?? $_POST['order_id'] ?? '');
        $paymentId = (string)($_POST['razorpay_payment_id'] ?? $_POST['payment_id'] ?? '');
        $signature = (string)($_POST['razorpay_signature'] ?? $_POST['signature'] ?? '');
        if ($orderId === '' || $paymentId === '' || $signature === '') {
            $this->jsonResponse(['verified' => false, 'error' => 'Missing Razorpay payment verification fields.'], 400);
        }
        $ok = (new PaymentService($secrets['razorpay_key_secret'] ?? ''))->verifySignature($orderId, $paymentId, $signature);
        if (!$ok) {
            $this->jsonResponse(['verified' => false, 'error' => 'Payment signature mismatch.'], 400);
        }
        $quote = (new WalletService())->quoteTopUp((int)($_POST['amount_rupees'] ?? 0));
        (new WalletService())->addTopUp($_SESSION['user']['email'] ?? '', $quote, $paymentId);
        $this->jsonResponse(['verified' => true]);
    }
}
