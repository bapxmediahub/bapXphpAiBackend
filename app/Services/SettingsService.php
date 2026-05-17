<?php
namespace App\Services;
final class SettingsService {
    public function __construct(private JsonStoreService $store = new JsonStoreService()) {}
    public function public(): array { return $this->store->read('settings')[0] ?? ['shipping_mode'=>'free','flat_rate'=>0]; }
    public function savePublic(array $settings): void { $this->store->write('settings', [$settings]); }
}
