<?php
namespace App\Controllers;
use App\Services\{AppointmentService,AuthService,OrderService,ReviewService,SettingsService};
final class AccountController extends BaseController {
    public function __construct() {
        (new AuthService())->requireUser();
        $this->seoKey = 'account';
    }

    public function dashboard(): void { $this->redirect('/account/dashboard/orders'); }
    public function legacyOrders(): void { $this->redirect('/account/dashboard/orders'); }
    public function legacyBookings(): void { $this->redirect('/account/dashboard/sessions'); }

    public function orders(): void {
        $userEmail = $_SESSION['user']['email'] ?? '';
        $orders = (new OrderService())->all();
        if ($userEmail !== '') {
            $orders = array_values(array_filter($orders, fn($order) => ($order['customer_email'] ?? '') === $userEmail));
        } else {
            $orders = [];
        }
        $reviewService = new ReviewService();
        $this->render('account/orders', compact('orders', 'reviewService'));
    }
    public function bookings(): void {
        $userEmail = $_SESSION['user']['email'] ?? '';
        $bookings = (new AppointmentService())->all();
        if ($userEmail !== '') {
            $bookings = array_values(array_filter($bookings, fn($booking) => ($booking['customer_email'] ?? '') === $userEmail));
        } else {
            $bookings = [];
        }
        $this->render('account/bookings', compact('bookings'));
    }

    public function install(): void {
        $this->render('account/install');
    }

    public function invoice(string $orderId): void {
        $userEmail = $_SESSION['user']['email'] ?? '';
        $orders = (new OrderService())->all();
        $order = null;
        foreach ($orders as $o) {
            if (($o['id'] ?? '') === $orderId && ($o['customer_email'] ?? '') === $userEmail) {
                $order = $o; break;
            }
        }
        if (!$order) { http_response_code(404); echo 'Invoice not found.'; return; }
        $settings = (new SettingsService())->public();
        $this->render('account/invoice', compact('order', 'settings'));
    }

}
