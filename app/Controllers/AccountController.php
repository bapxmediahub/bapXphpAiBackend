<?php
namespace App\Controllers;
use App\Services\{AppointmentService,AuthService,OrderService,ReviewService,WalletService};
final class AccountController extends BaseController {
    public function __construct() {
        (new AuthService())->requireUser();
        $this->seoKey = 'account';
    }

    public function dashboard(): void { $this->redirect('/account/dashboard/orders'); }
    public function legacyOrders(): void { $this->redirect('/account/dashboard/orders'); }
    public function legacyBookings(): void { $this->redirect('/account/dashboard/sessions'); }
    public function legacyWallet(): void { $this->redirect('/account/dashboard/wallet'); }

    public function orders(): void {
        $orders = (new OrderService())->all();
        if (!empty($_SESSION['user']['email'])) {
            $orders = array_values(array_filter($orders, fn($order) => ($order['customer_email'] ?? '') === $_SESSION['user']['email']));
        }
        $reviewService = new ReviewService();
        $walletBalance = (new WalletService())->balanceFor($_SESSION['user']['email'] ?? '');
        $this->render('account/orders', compact('orders', 'reviewService', 'walletBalance'));
    }
    public function bookings(): void {
        $bookings = (new AppointmentService())->all();
        if (!empty($_SESSION['user']['email'])) {
            $bookings = array_values(array_filter($bookings, fn($booking) => ($booking['customer_email'] ?? '') === $_SESSION['user']['email']));
        }
        $walletBalance = (new WalletService())->balanceFor($_SESSION['user']['email'] ?? '');
        $this->render('account/bookings', compact('bookings', 'walletBalance'));
    }

    public function wallet(): void { $this->legacyWallet(); }
}
