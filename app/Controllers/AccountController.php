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
        $userEmail = $_SESSION['user']['email'] ?? '';
        $orders = (new OrderService())->all();
        if ($userEmail !== '') {
            $orders = array_values(array_filter($orders, fn($order) => ($order['customer_email'] ?? '') === $userEmail));
        } else {
            $orders = [];
        }
        $reviewService = new ReviewService();
        $walletBalance = (new WalletService())->balanceFor($userEmail);
        $this->render('account/orders', compact('orders', 'reviewService', 'walletBalance'));
    }
    public function bookings(): void {
        $userEmail = $_SESSION['user']['email'] ?? '';
        $bookings = (new AppointmentService())->all();
        if ($userEmail !== '') {
            $bookings = array_values(array_filter($bookings, fn($booking) => ($booking['customer_email'] ?? '') === $userEmail));
        } else {
            $bookings = [];
        }
        $walletBalance = (new WalletService())->balanceFor($userEmail);
        $this->render('account/bookings', compact('bookings', 'walletBalance'));
    }

    public function wallet(): void { $this->legacyWallet(); }
}
