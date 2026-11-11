<?php
namespace App\Services;

final class AgentContextService {
    public function __construct(private DatabaseService $store = new DatabaseService(), private SchemaService $schema = new SchemaService()) {}

    public function forUserEmail(string $email): array {
        $email = strtolower(trim($email));
        if ($email === '') return ['user'=>null, 'orders'=>[], 'settings'=>$this->publicSettings(), 'site'=>$this->siteContext()];
        return [
            'user' => $this->firstOwned('users', 'email', $email),
            'orders' => $this->owned('orders', 'customer_email', $email),
            'settings' => $this->publicSettings(),
            'site' => $this->siteContext(),
        ];
    }

    /** Aggregate owner context only; no user, order, email, address or tracking rows. */
    public function forOwnerSummary(): array {
        return self::ownerSummaryFromCollections(
            $this->store->read('users'),
            $this->store->read('orders'),
            $this->store->read('products'),
            $this->store->read('appointments'),
            $this->store->read('support_tickets')
        );
    }

    /** Pure aggregation boundary, kept public so privacy behavior is fixture-testable. */
    public static function ownerSummaryFromCollections(array $users, array $orders, array $products, array $appointments, array $tickets): array {
        $confirmed = array_values(array_filter($orders, static fn(array $o): bool => ($o['status'] ?? '') === 'confirmed'));
        $pending = array_values(array_filter($orders, static fn(array $o): bool => ($o['status'] ?? '') === 'pending'));
        $totalRevenue = array_sum(array_map(static fn(array $o): float => (float)($o['total'] ?? 0), $orders));
        $confirmedRevenue = array_sum(array_map(static fn(array $o): float => (float)($o['total'] ?? 0), $confirmed));
        $pendingRevenue = array_sum(array_map(static fn(array $o): float => (float)($o['total'] ?? 0), $pending));
        $units = $revenue = [];
        foreach ($confirmed as $order) {
            foreach (($order['items'] ?? []) as $item) {
                if (!is_array($item)) continue;
                $name = trim((string)($item['name'] ?? $item['slug'] ?? ''));
                if ($name === '') continue;
                $units[$name] = ($units[$name] ?? 0) + max(0, (int)($item['qty'] ?? 1));
                $revenue[$name] = ($revenue[$name] ?? 0) + (float)($item['line_total'] ?? 0);
            }
        }
        arsort($units);
        $topProducts = [];
        foreach (array_slice($units, 0, 5, true) as $name => $quantity) {
            $topProducts[] = ['name' => $name, 'units' => $quantity, 'revenue' => round((float)($revenue[$name] ?? 0), 2)];
        }
        return [
            'users' => count($users),
            'orders' => ['total'=>count($orders), 'confirmed'=>count($confirmed), 'pending'=>count($pending)],
            'products' => count($products),
            'appointments' => count($appointments),
            'support_tickets' => count($tickets),
            'revenue' => [
                'total'=>round($totalRevenue, 2),
                'confirmed'=>round($confirmedRevenue, 2),
                'pending'=>round($pendingRevenue, 2),
                'average_order'=>count($orders) > 0 ? round($totalRevenue / count($orders), 2) : 0.0,
            ],
            'top_products' => $topProducts,
            'privacy_scope' => 'Aggregate owner metrics only. No customer or order identifiers are included.',
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
            'description' => mb_substr(strip_tags((string)($item['description'] ?? '')), 0, 2000),
            'highlights' => $item['highlights'] ?? [],
            'description_points' => $item['description_points'] ?? [],
            'specifications' => $item['specifications'] ?? [],
        // Visible only, and priced through the offer window: the support bot must never
        // recommend a product the shopper cannot buy, or quote an offer that has ended.
        ], array_slice((new ProductService($this->store))->visible(), 0, 20));
        return [
            'pages' => [
                'shop' => '/shop',
                'cart' => '/cart',
                'checkout' => '/checkout',
                'contact' => '/contact',
                'orders' => '/account/dashboard/orders',
            ],
            'products' => $products,
            'support_scope' => 'Answer only from this JSON context and public site links. Do not access tools, files, admin data, or other users.',
        ];
    }
}
