<?php
namespace App\Services;
final class SettingsService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}
    public function public(): array { try { return $this->store->read('settings')[0] ?? ['shipping_mode'=>'free','flat_rate'=>0]; } catch (\Throwable) { return ['shipping_mode'=>'free','flat_rate'=>0]; } }
    public function admin(): array { try { return $this->store->read('settings')[0] ?? []; } catch (\Throwable) { return []; } }
    public function savePublic(array $settings): void {
        $settings['id'] = 'app_settings';
        $this->store->upsert('settings', $settings, 'id');
    }
}
