<?php
namespace App\Services;

final class AgentContextService {
    public function __construct(private DatabaseService $store = new DatabaseService(), private SchemaService $schema = new SchemaService()) {}

    public function forUserEmail(string $email): array {
        $email = strtolower(trim($email));
        if ($email === '') return ['user'=>null, 'orders'=>[], 'sessions'=>[], 'settings'=>$this->publicSettings(), 'site'=>$this->siteContext()];
        return [
            'user' => $this->firstOwned('users', 'email', $email),
            'orders' => $this->owned('orders', 'customer_email', $email),
            'sessions' => $this->owned('appointments', 'customer_email', $email),
            'settings' => $this->publicSettings(),
            'site' => $this->siteContext(),
        ];
    }

    private function owned(string $collection, string $field, string $email): array {
        $records = array_values(array_filter($this->store->read($collection), fn($item) => strtolower((string)($item[$field] ?? '')) === $email));
        $fields = $this->schema->agentContextFields($collection);
        return $fields ? array_map(fn($item) => array_intersect_key($item, array_flip($fields)), $records) : $records;
    }

    private function firstOwned(string $collection, string $field, string $email): ?array {
        $records = $this->owned($collection, $field, $email);
        return $records[0] ?? null;
    }

    private function publicSettings(): array {
        $settings = $this->store->read('settings')[0] ?? [];
        return array_intersect_key($settings, array_flip(['currency', 'timezone', 'shipping_mode', 'flat_rate']));
    }

    private function siteContext(): array {
        $products = array_map(fn($item) => [
            'name' => $item['name'] ?? '',
            'slug' => $item['slug'] ?? '',
            'url' => '/product/' . ($item['slug'] ?? ''),
            'price' => $item['offer_price'] ?? $item['price'] ?? null,
            'stock_status' => $item['stock_status'] ?? '',
        ], array_slice($this->store->read('products'), 0, 20));
        return [
            'pages' => [
                'shop' => '/shop',
                'cart' => '/cart',
                'checkout' => '/checkout',
                'astrologers' => '/consult',
                'booking_contact_form' => '/contact?subject=astrology#contact-form',
                'contact' => '/contact',
                'orders' => '/account/dashboard/orders',
                'sessions' => '/account/dashboard/sessions',
            ],
            'products' => $products,
            'support_scope' => 'Answer only from this JSON context and public site links. Do not access tools, files, admin data, or other users.',
        ];
    }
}
