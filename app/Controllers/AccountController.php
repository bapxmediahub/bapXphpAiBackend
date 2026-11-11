<?php
namespace App\Controllers;
use App\Services\{AddressService,AuthService,DatabaseService,OrderService,ReviewService,SettingsService,TaxService};
final class AccountController extends BaseController {
    public function __construct() {
        (new AuthService())->requireUser();
        $this->seoKey = 'account';
    }

    public function dashboard(): void { $this->redirect('/account/dashboard/orders'); }
    public function legacyOrders(): void { $this->redirect('/account/dashboard/orders'); }
    public function legacyBookings(): void { $this->redirect('/account/dashboard/orders'); }

    public function profile(): void {
        $profile = $this->profileRecord(new DatabaseService());
        if ($profile === null) { $this->renderNotFound(); return; }
        $this->render('account/profile', [
            'profile' => array_intersect_key($profile, array_flip(['name', 'email', 'phone', 'picture', 'gender', 'date_of_birth'])),
        ]);
    }

    public function saveProfile(): void {
        $this->validateCsrf();
        $store = new DatabaseService();
        $record = $this->profileRecord($store);
        if ($record === null) { $this->renderNotFound(); return; }
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $gender = (string)($_POST['gender'] ?? '');
        $birth = (string)($_POST['date_of_birth'] ?? '');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $birth) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $birth) : false;
        if ($name === '' || mb_strlen($name) > 100
            || ($phone !== '' && !AuthService::validPhone($phone))
            || !in_array($gender, ['', 'female', 'male', 'other', 'prefer_not_to_say'], true)
            || ($birth !== '' && (!$date || $date->format('Y-m-d') !== $birth || $date > new \DateTimeImmutable('today')))) {
            $this->flash('Check your name, phone number, gender and date of birth.', 'error');
        } else {
            $store->upsert('users', ['id' => $record['id'], 'name' => $name, 'phone' => $phone,
                'gender' => $gender, 'date_of_birth' => $birth]);
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['phone'] = $phone;
            $this->flash('Profile saved.', 'success');
        }
        $this->redirect('/account/dashboard/profile');
    }

    private function profileRecord(DatabaseService $store): ?array {
        $session = $_SESSION['user'] ?? [];
        $id = (string)($session['sub'] ?? '');
        if ($id === '' || $id === 'env-admin') return null;
        $record = $store->find('users', $id);
        return $record !== null && strcasecmp((string)($record['email'] ?? ''), (string)($session['email'] ?? '')) === 0
            ? $record : null;
    }

    public function addresses(): void {
        $service = new AddressService();
        $email = (string)($_SESSION['user']['email'] ?? '');
        $addresses = $service->forCustomer($email);
        $id = (string)($_GET['edit'] ?? '');
        try {
            $editing = $id !== '' ? $service->owned($email, $id) : [];
        } catch (\InvalidArgumentException) {
            $this->renderNotFound();
            return;
        }
        $this->render('account/addresses', [
            'addresses' => $addresses,
            'editing' => $editing,
            'states' => TaxService::states(),
        ]);
    }

    public function saveAddress(): void {
        $this->validateCsrf();
        try {
            (new AddressService())->save((string)($_SESSION['user']['email'] ?? ''), $_POST, (string)($_POST['address_id'] ?? ''));
            $this->flash('Address saved.', 'success');
        } catch (\InvalidArgumentException $error) {
            $this->flash($error->getMessage(), 'error');
        }
        $this->redirect('/account/dashboard/addresses');
    }

    public function deleteAddress(): void {
        $this->validateCsrf();
        try {
            (new AddressService())->delete((string)($_SESSION['user']['email'] ?? ''), (string)($_POST['address_id'] ?? ''));
            $this->flash('Address removed.', 'success');
        } catch (\InvalidArgumentException $error) {
            $this->flash($error->getMessage(), 'error');
        }
        $this->redirect('/account/dashboard/addresses');
    }

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
    public function bookings(): void { $this->redirect('/account/dashboard/orders'); }

    public function install(): void {
        $this->render('account/install');
    }

    /**
     * One order, in full.
     *
     * My Orders was a seven-column table with no way into an individual order, so a
     * customer could see a status but never the items, the address and the tracking
     * together. It doubles as the thank-you screen: a verified payment lands here with
     * ?placed=1, which is the first time the customer sees what they actually bought.
     */
    public function order(string $orderId): void {
        $order = $this->ownedOrder($orderId);
        if (!$order) { $this->renderNotFound(); return; }
        $justPlaced = isset($_GET['placed']);
        $settings = (new SettingsService())->public();
        $this->render('account/order', compact('order', 'justPlaced', 'settings'));
    }

    /** The order, only if it belongs to the signed-in customer. */
    private function ownedOrder(string $orderId): ?array {
        $userEmail = $_SESSION['user']['email'] ?? '';
        if ($userEmail === '') return null;
        foreach ((new OrderService())->all() as $o) {
            if (($o['id'] ?? '') === $orderId && ($o['customer_email'] ?? '') === $userEmail) return $o;
        }
        return null;
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
        $invoiceReviewReason = TaxService::invoiceReviewReason($order, $settings);
        $this->render('account/invoice', compact('order', 'settings', 'invoiceReviewReason'));
    }

}
