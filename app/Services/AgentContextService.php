<?php
namespace App\Services;

final class AgentContextService {
    public function __construct(private DatabaseService $store = new DatabaseService(), private SchemaService $schema = new SchemaService()) {}

    public function forUserEmail(string $email, string $message = ''): array {
        $email = strtolower(trim($email));
        if ($email === '') return ['user'=>null, 'orders'=>[], 'settings'=>$this->publicSettings(), 'site'=>$this->siteContext($message)];
        return [
            'user' => $this->firstOwned('users', 'email', $email),
            'orders' => $this->owned('orders', 'customer_email', $email),
            'settings' => $this->publicSettings(),
            'site' => $this->siteContext($message),
        ];
    }

    /** Owner analytics contain aggregate figures only; never include customer identifiers. */
    public function forOwnerAnalytics(): string {
        return self::ownerAnalyticsFromRecords($this->store->read('orders'), count($this->store->read('users')),
            count($this->store->read('products')), count($this->store->read('appointments')), count($this->store->read('support_tickets')));
    }

    public static function ownerAnalyticsFromRecords(array $orders, int $userCount, int $productCount, int $appointmentCount, int $ticketCount): string {
        $confirmed = array_filter($orders, fn($o) => ($o['status'] ?? '') === 'confirmed');
        $pending = array_filter($orders, fn($o) => ($o['status'] ?? '') === 'pending');
        $total = array_sum(array_column($orders, 'total'));
        $units = [];
        $revenue = [];
        foreach ($confirmed as $order) {
            foreach (($order['items'] ?? []) as $item) {
                $name = (string)($item['name'] ?? $item['slug'] ?? '');
                if ($name === '') continue;
                $units[$name] = ($units[$name] ?? 0) + (int)($item['qty'] ?? 1);
                $revenue[$name] = ($revenue[$name] ?? 0) + (float)($item['line_total'] ?? 0);
            }
        }
        arsort($units);
        $top = '';
        foreach (array_slice($units, 0, 5, true) as $name => $count) {
            $top .= "\n  - {$name}: {$count} sold, ₹" . number_format($revenue[$name] ?? 0, 2);
        }
        if ($top === '') $top = "\n  - no confirmed sales yet";
        $orderCount = count($orders);
        return "Site data (aggregate statistics):\n"
            . '- Total users: ' . $userCount . "\n"
            . '- Total orders: ' . $orderCount . ' (confirmed: ' . count($confirmed) . ', pending: ' . count($pending) . ")\n"
            . '- Products: ' . $productCount . "\n"
            . '- Appointments: ' . $appointmentCount . "\n"
            . '- Support tickets: ' . $ticketCount . "\n"
            . '- Total revenue: ₹' . number_format($total, 2) . "\n"
            . '- Confirmed revenue: ₹' . number_format(array_sum(array_column($confirmed, 'total')), 2) . "\n"
            . '- Pending revenue (unconfirmed): ₹' . number_format(array_sum(array_column($pending, 'total')), 2) . "\n"
            . '- Average order value: ₹' . number_format($orderCount ? $total / $orderCount : 0, 2) . "\n"
            . '- Top 5 products by units sold:' . $top;
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

    /** Match only exact public catalogue aliases, not loose words that can collide. */
    public static function matchesNamedProduct(string $message, array $product): bool
    {
        $normalise = static fn(string $value): string => trim((string)preg_replace('/[^a-z0-9]+/i', ' ', mb_strtolower($value)));
        $haystack = ' ' . $normalise($message) . ' ';
        $aliases = [
            $normalise((string)($product['name'] ?? '')),
            $normalise((string)($product['slug'] ?? '')),
        ];
        foreach (array_unique(array_filter($aliases)) as $alias) {
            // Requiring at least two words avoids treating a generic one-word slug such
            // as "ring" or "paste" as a named-product request.
            if (str_word_count($alias) >= 2 && str_contains($haystack, ' ' . $alias . ' ')) return true;
        }
        return false;
    }

    /** Prefer exact matches across the visible catalogue before applying the prompt cap. */
    public static function supportProducts(array $visibleProducts, string $message): array {
        $matches = array_filter($visibleProducts, static fn(array $product): bool => self::matchesNamedProduct($message, $product));
        if (self::isProductComparison($message)) {
            foreach (['pendants?', 'malas?|malais?', 'rings?', 'bracelets?', 'statues?|idols?'] as $type) {
                $pattern = '/\b(?:' . $type . ')\b/i';
                if (!preg_match($pattern, $message)) continue;
                $examples = array_filter($visibleProducts, static fn(array $product): bool =>
                    preg_match($pattern, (string)($product['name'] ?? '')));
                $matches += array_slice($examples, 0, 3, true);
            }
        }
        return array_slice($matches ?: $visibleProducts, 0, 20);
    }

    public static function isProductComparison(string $message): bool {
        return (bool)preg_match('/\b(?:compare|comparison|difference between|differ from|versus|vs)\b/i', $message);
    }

    private function siteContext(string $message): array {
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
        ], self::supportProducts((new ProductService($this->store))->visible(), $message));
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
