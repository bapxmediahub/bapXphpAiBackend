<?php
namespace App\Services;

final class AgentContextService {
    public function __construct(private DatabaseService $store = new DatabaseService(), private SchemaService $schema = new SchemaService()) {}

    public function forUserEmail(string $email, string $message = ''): array {
        $email = strtolower(trim($email));
        return [
            'orders' => $email !== '' ? $this->supportOrders($email, $message) : [],
            'settings' => $this->publicSettings(),
            'site' => $this->siteContext($message),
        ];
    }

    public function forSupportTickets(string $email, ?string $reference, ?string $status, ?array $period = null): array {
        $tickets = (new SupportTicketService($this->store))->forUser($email);
        return self::supportTicketFacts($tickets, $reference, $status, $period);
    }

    /** Only system-generated ticket metadata may be sent to the provider. */
    public static function supportTicketFacts(array $tickets, ?string $reference, ?string $status, ?array $period = null): array {
        usort($tickets, static fn(array $a, array $b): int =>
            (strtotime((string)($b['created_at'] ?? '')) ?: 0) <=> (strtotime((string)($a['created_at'] ?? '')) ?: 0));
        $matches = [];
        $counts = ['open' => 0, 'answered' => 0, 'closed' => 0, 'unknown' => 0];
        foreach ($tickets as $ticket) {
            $id = strtolower((string)($ticket['id'] ?? ''));
            if (!preg_match('/^[a-f0-9]{16}$/D', $id)) continue;
            if ($reference !== null && $id !== $reference) continue;
            $state = strtolower((string)($ticket['status'] ?? ''));
            if (!isset($counts[$state])) $state = 'unknown';
            if ($status !== null && $status !== $state) continue;
            $created = self::recordDate($ticket['created_at'] ?? null);
            if ($period !== null && ($created === null || $created < $period[0] || $created > $period[1])) continue;
            $counts[$state]++;
            $matches[] = [
                'reference' => $id,
                'status' => $state,
                'created_date' => $created,
                'updated_date' => self::recordDate($ticket['updated_at'] ?? null),
            ];
        }
        return ['count' => count($matches), 'counts_by_status' => $counts, 'recent' => array_slice($matches, 0, 5)];
    }

    private static function recordDate(mixed $value): ?string {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:$|[T ])/', $value)) return null;
        $date = substr($value, 0, 10);
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year) ? $date : null;
    }

    public const OWNER_COUNT_ENTITIES = ['users', 'products', 'appointments'];

    public function ownerCount(mixed $entity): array {
        if (!in_array($entity, self::OWNER_COUNT_ENTITIES, true)) return ['error' => 'Choose users, products or appointments.'];
        return ['entity' => $entity, 'count' => count($this->store->read($entity))];
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

    /** Share the order state needed for support without delivery addresses or payment identifiers. */
    private function supportOrders(string $email, string $message): array {
        $orders = array_values(array_filter($this->store->read('orders'), static fn(array $order): bool =>
            strtolower(trim((string)($order['customer_email'] ?? ''))) === $email));
        if (preg_match('/\b(?:order\s*#?\s*)?([a-f0-9]{16})\b/i', $message, $match)) {
            $orders = array_values(array_filter($orders, static fn(array $order): bool =>
                strcasecmp((string)($order['id'] ?? ''), $match[1]) === 0));
        }
        usort($orders, static fn(array $a, array $b): int =>
            (strtotime((string)($b['created_at'] ?? '')) ?: 0) <=> (strtotime((string)($a['created_at'] ?? '')) ?: 0));
        $schemaFields = $this->schema->agentContextFields('orders');
        $safeFields = array_flip(array_intersect($schemaFields, ['id', 'status', 'total', 'shipped_at', 'invoice_number', 'invoice_date']));
        return array_map(static function (array $order) use ($safeFields, $schemaFields): array {
            $facts = array_intersect_key($order, $safeFields);
            if (in_array('items', $schemaFields, true)) {
                $facts['items'] = array_map(static fn(array $item): array => [
                    'name' => mb_substr(strip_tags((string)($item['name'] ?? '')), 0, 120),
                    'quantity' => (int)($item['qty'] ?? 0),
                ], array_slice(array_filter((array)($order['items'] ?? []), 'is_array'), 0, 10));
            }
            return $facts;
        }, array_slice($orders, 0, 5));
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
            'url' => '/product/' . rawurlencode((string)($item['slug'] ?? '')),
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
