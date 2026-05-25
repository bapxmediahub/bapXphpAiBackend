<?php
namespace App\Controllers;
use App\Services\{AppointmentService,OrderService};
final class AccountController extends BaseController {
    public function orders(): void {
        $orders = (new OrderService())->all();
        if (!empty($_SESSION['user']['email'])) {
            $orders = array_values(array_filter($orders, fn($order) => ($order['customer_email'] ?? '') === $_SESSION['user']['email']));
        }
        $this->render('account/orders', compact('orders'));
    }
    public function bookings(): void {
        $bookings = (new AppointmentService())->all();
        if (!empty($_SESSION['user']['email'])) {
            $bookings = array_values(array_filter($bookings, fn($booking) => ($booking['customer_email'] ?? '') === $_SESSION['user']['email']));
        }
        $this->render('account/bookings', compact('bookings'));
    }
}
