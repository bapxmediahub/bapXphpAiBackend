<?php
namespace App\Services;

/**
 * What the admin agent is allowed to look up, and how.
 *
 * Until now the agent was handed one pre-computed statistics blob and nothing else. It
 * could answer "how many orders" because that number happened to be in the blob, and
 * could not answer "where is order 9426" at all — the data existed, but there was no
 * way for the model to ask for it. Making the blob bigger does not fix that: it costs
 * tokens on every message, goes stale the moment it is built, and still only answers
 * the questions somebody thought of in advance.
 *
 * Tools invert it. The model is told what it may ask for, and asks when it needs to.
 *
 * Every tool here is read-only. An agent that can change the shop on its own would be
 * one prompt away from hiding a product or cancelling an order, and the reply that
 * follows would be the owner's first notice. Writes stay with AgentDraftService, which
 * fills in a form the owner reads and saves.
 *
 * Customer email addresses are only ever returned by the tool the owner points at a
 * specific customer. The statistics context leaked them into unrelated answers once
 * already.
 */
final class AgentToolRegistry
{
    public function __construct(
        private DatabaseService $store = new DatabaseService(),
        private ?ProductService $products = null,
        private ?OrderService $orders = null
    ) {
        $this->products ??= new ProductService($this->store);
        $this->orders ??= new OrderService($this->store);
    }

    /**
     * Tool declarations in the JSON Schema shape the provider expects.
     *
     * @return array<int,array<string,mixed>>
     */
    public function declarations(): array
    {
        return [
            [
                'name' => 'find_order',
                'description' => 'Look up one order by its id, or the most recent orders for a customer email. '
                    . 'Returns status, total, items, courier and tracking.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'string', 'description' => 'The order id, whole or the first few characters.'],
                        'customer_email' => ['type' => 'string', 'description' => 'Email of the customer whose orders to list.'],
                    ],
                ],
            ],
            [
                'name' => 'product_status',
                'description' => 'Stock, price, current offer and whether a product is hidden. '
                    . 'Omit the slug to list every product.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Product slug, or part of its name.'],
                    ],
                ],
            ],
            [
                'name' => 'sales_summary',
                'description' => 'Revenue and order counts over a number of days, with the best selling products.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'description' => 'How many days back to include. Defaults to 30.'],
                    ],
                ],
            ],
            [
                'name' => 'coupon_status',
                'description' => 'Whether a coupon is currently usable, and why not if it is refused: '
                    . 'expired, not started, spend limits, usage limits.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'The coupon code.'],
                    ],
                    'required' => ['code'],
                ],
            ],
        ];
    }

    public function has(string $name): bool
    {
        foreach ($this->declarations() as $tool) {
            if ($tool['name'] === $name) return true;
        }
        return false;
    }

    /**
     * Runs a tool and returns a plain array for the model.
     *
     * A failure comes back as an `error` key rather than an exception: the model should
     * be told "no such order" and given the chance to say so, not have the whole
     * conversation collapse into a 500.
     */
    public function run(string $name, array $args): array
    {
        try {
            return match ($name) {
                'find_order' => $this->findOrder($args),
                'product_status' => $this->productStatus($args),
                'sales_summary' => $this->salesSummary($args),
                'coupon_status' => $this->couponStatus($args),
                default => ['error' => 'No such tool: ' . $name],
            };
        } catch (\Throwable $e) {
            error_log('Agent tool ' . $name . ' failed: ' . $e->getMessage());
            return ['error' => 'That lookup failed: ' . $e->getMessage()];
        }
    }

    private function findOrder(array $args): array
    {
        $id = strtolower(trim((string)($args['order_id'] ?? '')));
        $email = strtolower(trim((string)($args['customer_email'] ?? '')));
        if ($id === '' && $email === '') return ['error' => 'Give an order id or a customer email.'];

        $matches = [];
        foreach ($this->orders->all() as $order) {
            $orderId = strtolower((string)($order['id'] ?? ''));
            if ($id !== '' && !str_starts_with($orderId, $id)) continue;
            if ($email !== '' && strtolower((string)($order['customer_email'] ?? '')) !== $email) continue;
            $matches[] = $this->summariseOrder($order);
            if (count($matches) >= 10) break;
        }
        if ($matches === []) return ['found' => 0, 'message' => 'No order matched.'];
        return ['found' => count($matches), 'orders' => $matches];
    }

    private function summariseOrder(array $order): array
    {
        $items = [];
        foreach (($order['items'] ?? []) as $item) {
            $items[] = [
                'name' => (string)($item['name'] ?? $item['slug'] ?? ''),
                'qty' => (int)($item['qty'] ?? 1),
                'line_total' => (float)($item['line_total'] ?? 0),
            ];
        }
        return [
            'id' => (string)($order['id'] ?? ''),
            'status' => (string)($order['status'] ?? ''),
            'total' => (float)($order['total'] ?? 0),
            'placed_on' => substr((string)($order['created_at'] ?? ''), 0, 10),
            'customer_email' => (string)($order['customer_email'] ?? ''),
            'shipping_city' => (string)($order['shipping_city'] ?? ''),
            'courier' => (string)($order['courier_name'] ?? ''),
            'tracking_id' => (string)($order['tracking_id'] ?? ''),
            'items' => $items,
        ];
    }

    private function productStatus(array $args): array
    {
        $needle = strtolower(trim((string)($args['slug'] ?? '')));
        $out = [];
        foreach ($this->products->all() as $product) {
            $slug = strtolower((string)($product['slug'] ?? ''));
            $name = strtolower((string)($product['name'] ?? ''));
            if ($needle !== '' && !str_contains($slug, $needle) && !str_contains($name, $needle)) continue;
            $out[] = [
                'slug' => (string)($product['slug'] ?? ''),
                'name' => (string)($product['name'] ?? ''),
                'price' => (float)($product['price'] ?? 0),
                // Already normalised: an offer outside its window reads as no offer.
                'offer_price' => $product['offer_price'] !== null ? (float)$product['offer_price'] : null,
                'stock_status' => (string)($product['stock_status'] ?? ''),
                'hidden_from_shop' => !empty($product['is_hidden']),
            ];
        }
        if ($out === []) return ['found' => 0, 'message' => 'No product matched.'];
        return ['found' => count($out), 'products' => $out];
    }

    private function salesSummary(array $args): array
    {
        $days = (int)($args['days'] ?? 30);
        if ($days < 1) $days = 30;
        $since = (new \DateTimeImmutable())->modify('-' . $days . ' days');

        $counted = 0;
        $revenue = 0.0;
        $byStatus = [];
        $units = [];
        foreach ($this->orders->all() as $order) {
            $created = trim((string)($order['created_at'] ?? ''));
            if ($created !== '') {
                try { if (new \DateTimeImmutable($created) < $since) continue; }
                catch (\Throwable) {}
            }
            $status = (string)($order['status'] ?? 'unknown');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $counted++;
            // Refunded and cancelled orders are not revenue.
            if (in_array($status, ['cancelled', 'refunded'], true)) continue;
            $revenue += (float)($order['total'] ?? 0);
            foreach (($order['items'] ?? []) as $item) {
                $key = (string)($item['name'] ?? $item['slug'] ?? '');
                if ($key === '') continue;
                $units[$key] = ($units[$key] ?? 0) + (int)($item['qty'] ?? 1);
            }
        }
        arsort($units);
        return [
            'days' => $days,
            'orders' => $counted,
            'orders_by_status' => $byStatus,
            'revenue' => round($revenue, 2),
            'currency' => 'INR',
            'best_sellers' => array_slice($units, 0, 5, true),
        ];
    }

    private function couponStatus(array $args): array
    {
        $code = trim((string)($args['code'] ?? ''));
        if ($code === '') return ['error' => 'Give a coupon code.'];
        $coupons = new CouponService($this->store);
        $coupon = $coupons->find($code);
        if ($coupon === null) return ['found' => false, 'message' => 'No coupon with that code.'];

        // Judged by the same service checkout uses, so the agent cannot tell the owner a
        // coupon works while checkout refuses it.
        $usable = true;
        $reason = '';
        try {
            $coupons->assertUsable($coupon, 100000.0, $coupons->timesUsed($code), 0);
        } catch (\InvalidArgumentException $e) {
            $usable = false;
            $reason = $e->getMessage();
        }
        return [
            'found' => true,
            'code' => (string)($coupon['code'] ?? ''),
            'discount_type' => (string)($coupon['discount_type'] ?? ''),
            'discount_value' => (float)($coupon['discount_value'] ?? 0),
            'starts_at' => (string)($coupon['starts_at'] ?? ''),
            'ends_at' => (string)($coupon['ends_at'] ?? ''),
            'times_used' => $coupons->timesUsed($code),
            'usable_now' => $usable,
            'why_not' => $reason,
        ];
    }
}
