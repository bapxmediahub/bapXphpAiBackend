<?php
namespace App\Services;
final class SettingsService {
    public function __construct(private JsonStoreService $store = new JsonStoreService()) {}
    public function public(): array {
        return array_replace($this->defaults(), $this->store->read('settings')[0] ?? []);
    }
    public function savePublic(array $settings): void {
        $current = $this->public();
        $clean = array_replace($current, [
            'shipping_mode' => $settings['shipping_mode'] ?? $current['shipping_mode'],
            'flat_rate' => (float)($settings['flat_rate'] ?? $current['flat_rate']),
            'currency' => strtoupper(trim((string)($settings['currency'] ?? $current['currency']))),
            'timezone' => trim((string)($settings['timezone'] ?? $current['timezone'])),
            'contact_email' => trim((string)($settings['contact_email'] ?? $current['contact_email'])),
            'contact_phone' => trim((string)($settings['contact_phone'] ?? $current['contact_phone'])),
            'whatsapp_number' => preg_replace('/\D+/', '', (string)($settings['whatsapp_number'] ?? $current['whatsapp_number'])),
            'admin_notification_email' => trim((string)($settings['admin_notification_email'] ?? $current['admin_notification_email'])),
            'smtp_host' => trim((string)($settings['smtp_host'] ?? $current['smtp_host'])),
            'smtp_port' => (int)($settings['smtp_port'] ?? $current['smtp_port']),
            'smtp_encryption' => strtolower(trim((string)($settings['smtp_encryption'] ?? $current['smtp_encryption']))),
            'smtp_username' => trim((string)($settings['smtp_username'] ?? $current['smtp_username'])),
            'smtp_from_email' => trim((string)($settings['smtp_from_email'] ?? $current['smtp_from_email'])),
            'smtp_from_name' => trim((string)($settings['smtp_from_name'] ?? $current['smtp_from_name'])),
        ]);
        $password = (string)($settings['smtp_password'] ?? '');
        if ($password !== '') {
            $clean['smtp_password'] = $password;
        }
        $this->store->write('settings', [$clean]);
    }
    private function defaults(): array {
        return [
            'shipping_mode'=>'free',
            'flat_rate'=>0,
            'currency'=>'INR',
            'timezone'=>'Asia/Kolkata',
            'contact_email'=>'sripanchamispiritual@gmail.com',
            'contact_phone'=>'',
            'whatsapp_number'=>'',
            'admin_notification_email'=>'',
            'smtp_host'=>'',
            'smtp_port'=>587,
            'smtp_encryption'=>'tls',
            'smtp_username'=>'',
            'smtp_password'=>'',
            'smtp_from_email'=>'',
            'smtp_from_name'=>'Sri Panchami Spiritual',
        ];
    }
}
