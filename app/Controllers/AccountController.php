<?php
namespace App\Controllers;
use App\Services\{AppointmentService,AuthService,OrderService,ReviewService};
final class AccountController extends BaseController {
    public function __construct() {
        (new AuthService())->requireUser();
    }

    public function orders(): void {
        $orders = (new OrderService())->all();
        if (!empty($_SESSION['user']['email'])) {
            $orders = array_values(array_filter($orders, fn($order) => ($order['customer_email'] ?? '') === $_SESSION['user']['email']));
        }
        $reviewService = new ReviewService();
        $this->render('account/orders', compact('orders', 'reviewService'));
    }
    public function bookings(): void {
        $bookings = (new AppointmentService())->all();
        if (!empty($_SESSION['user']['email'])) {
            $bookings = array_values(array_filter($bookings, fn($booking) => ($booking['customer_email'] ?? '') === $_SESSION['user']['email']));
        }
        $this->render('account/bookings', compact('bookings'));
    }
}
