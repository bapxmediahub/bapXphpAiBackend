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
        $quote = (new WalletService())->quoteTopUp((int)($_POST['amount_rupees'] ?? 0));
        if (empty($secrets['razorpay_key_id']) || empty($secrets['razorpay_key_secret'])) {
            $this->jsonResponse(['error' => 'Razorpay ' . ($secrets['razorpay_mode'] ?? 'selected') . ' mode is not configured yet.'], 400);
        }
        $order = (new RazorpayClient($secrets['razorpay_key_id'], $secrets['razorpay_key_secret']))->createOrder($quote['total_paise'], 'wallet_' . bin2hex(random_bytes(5)));
        $order['quote'] = $quote;
        $this->jsonResponse($order);
    }

    public function verify(): void {
        (new AuthService())->requireUser();
        $secrets = (new SecretService())->all();
        if (empty($secrets['razorpay_key_secret'])) {
            $this->jsonResponse(['verified' => false, 'error' => 'Razorpay ' . ($secrets['razorpay_mode'] ?? 'selected') . ' mode is not configured yet.'], 400);
        }
        $ok = (new PaymentService($secrets['razorpay_key_secret'] ?? ''))->verifySignature($_POST['order_id'] ?? '', $_POST['payment_id'] ?? '', $_POST['signature'] ?? '');
        if ($ok) {
            $quote = (new WalletService())->quoteTopUp((int)($_POST['amount_rupees'] ?? 0));
            (new WalletService())->addTopUp($_SESSION['user']['email'] ?? '', $quote, $_POST['payment_id'] ?? '');
        }
        $this->jsonResponse(['verified' => $ok]);
    }
}
