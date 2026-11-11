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
        private ?OrderService $orders = null,
        private string $ownerQuestion = '',
        private ?string $probeProductSlug = null
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
        $declarations = [
            [
                'name' => 'find_order',
                'description' => 'Look up one order by its exact full id, or the most recent orders for a customer email explicitly provided by the owner. '
                    . 'Returns status, total, items, courier and tracking.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'string', 'description' => 'The full order id explicitly provided by the owner.'],
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
                'name' => 'support_enquiries',
                'description' => 'Customer support enquiries: how many there are, their status split, and how many '
                    . 'concern orders, delivery, payment or refunds. Use this for any question about enquiries, '
                    . 'tickets or what customers are asking. Says plainly when there are none.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'description' => 'Narrow to a topic: orders, delivery, payment, refund, product. Omit for all.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'search_articles',
                'description' => 'Search published blog and help articles by keyword. Returns titles and their paths '
                    . 'so an answer can point at real published content.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Words to search for.'],
                    ],
                    'required' => ['query'],
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
        if ($this->probeProductSlug === null) return $declarations;
        return array_values(array_filter($declarations, static fn(array $tool): bool => $tool['name'] === 'product_status'));
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
        if ($this->probeProductSlug !== null) {
            if ($name !== 'product_status') return ['error' => 'Tool unavailable in probe.'];
            $args = ['slug' => $this->probeProductSlug];
        }
        try {
            return match ($name) {
                'find_order' => $this->findOrder($args),
                'product_status' => $this->productStatus($args),
                'sales_summary' => $this->salesSummary($args),
                'support_enquiries' => $this->supportEnquiries($args),
                'search_articles' => $this->searchArticles($args),
                'coupon_status' => $this->couponStatus($args),
                default => ['error' => 'No such tool: ' . $name],
            };
        } catch (\Throwable $e) {
            error_log('Agent tool ' . $name . ' failed (' . get_class($e) . ')');
            return ['error' => 'That lookup is temporarily unavailable.'];
        }
    }

    /**
     * Answer the common aggregate questions which are already backed by an allowlisted
     * local tool. Sending these to the provider adds latency and a failure point without
     * adding judgement: the owner asked for a figure the application can calculate.
     *
     * Returns null for anything that needs language interpretation or a scoped lookup,
     * so those requests continue through the configured model and privacy checks.
     */
    public function aggregateAnswer(string $question): ?string
    {
        $intent = self::aggregateIntent($question);
        if ($intent === null) return null;
        $days = self::aggregateDays($question);
        $args = $days === 0
            ? ['since' => (new \DateTimeImmutable('first day of this month midnight'))->format('c')]
            : ['days' => $days];
        $summary = $intent === 'order_count'
            ? ['orders' => $this->countOrders($days)]
            : $this->salesSummary($args);
        return self::aggregateAnswerFromSummary($question, $summary, $days);
    }

    private function countOrders(int $days): int
    {
        $orders = $this->orders->all();
        if ($days >= 36500) return count($orders);

        $now = new \DateTimeImmutable();
        $since = $days === 0 ? $now->modify('first day of this month midnight') : $now->modify("-{$days} days");
        $count = 0;
        foreach ($orders as $order) {
            $created = trim((string)($order['created_at'] ?? ''));
            if ($created === '') continue;
            try {
                $date = new \DateTimeImmutable($created);
                if ($date >= $since && $date <= $now) $count++;
            } catch (\Exception) {
                continue;
            }
        }
        return $count;
    }

    /** @param array<string,mixed> $summary */
    public static function aggregateAnswerFromSummary(string $question, array $summary, int $days = 30): ?string
    {
        $intent = self::aggregateIntent($question);
        if ($intent === null) return null;

        $period = match (true) {
            $days === 0 => 'this month',
            $days >= 36500 => 'across all recorded orders',
            default => "in the last {$days} days",
        };
        $orders = max(0, (int)($summary['orders'] ?? 0));
        if ($intent === 'order_count') {
            $scope = $days >= 36500 ? 'Includes all recorded statuses.' : 'Includes all statuses with a recorded creation date in that period.';
            return "The store recorded {$orders} " . ($orders === 1 ? 'order' : 'orders') . " {$period}. " . $scope;
        }
        $revenue = max(0, (float)($summary['revenue'] ?? 0));
        if ($intent === 'revenue') {
            return 'Recorded sales revenue ' . $period . ' is ₹' . number_format($revenue, 2)
                . " across {$orders} orders. Cancelled and refunded orders are excluded. "
                . 'Next step: review the Orders list if you need the status breakdown.';
        }

        $best = is_array($summary['best_sellers'] ?? null) ? $summary['best_sellers'] : [];
        if ($best === []) {
            return 'There are no sold product units to rank ' . $period
                . '. Cancelled and refunded orders are excluded. Next step: check recent Orders for payment or fulfilment holds.';
        }
        $items = [];
        foreach (array_slice($best, 0, 5, true) as $name => $units) {
            $items[] = trim((string)$name) . ' — ' . max(0, (int)$units) . ' unit' . ((int)$units === 1 ? '' : 's');
        }
        return 'Best sellers by units ' . $period . ': ' . implode('; ', $items)
            . '. Cancelled and refunded orders are excluded. Next step: check stock for the leading products.';
    }

    private static function aggregateIntent(string $question): ?string
    {
        if (preg_match('/\b(?:best[ -]?sell(?:er|ers|ing)?|top[ -]?sell(?:er|ers|ing)?|products?\s+sell(?:s|ing)?\s+(?:the\s+)?(?:best|most))\b/i', $question)) {
            return 'best_sellers';
        }
        $orderCountPattern = '/^\s*
            (?:how\ many\ orders(?:\ (?:have\ we\ had|did\ we\ get|were\ placed|did\ we\ receive))?
                |(?:what\ is\ the\ )?(?:total\ orders|order\ count))
            (?:\ (?:in\ |over\ |for\ )?(?:the\ )?
                (?:(?:last|past)\ [1-9]\d{0,3}\ days?|this\ month|all[ -]time|ever|overall))?
            [?!.]*\s*$/ix';
        if (preg_match($orderCountPattern, $question)) return 'order_count';
        if (preg_match('/\b(?:revenue|sales total|total sales|sales value)\b/i', $question)) return 'revenue';
        return null;
    }

    private static function aggregateDays(string $question): int
    {
        if (preg_match('/\b(?:all[ -]?time|ever|overall)\b/i', $question)) return 36500;
        if (preg_match('/\b(?:last|past)\s+(\d{1,4})\s+days?\b/i', $question, $match)) {
            return max(1, min(36500, (int)$match[1]));
        }
        if (preg_match('/\bthis\s+month\b/i', $question)) return 0;
        return 30;
    }

    private function findOrder(array $args): array
    {
        return self::scopedOrderLookup($this->orders->all(), $args, $this->ownerQuestion);
    }

    /**
     * Keep provider-bound order data within both the identifier and fields the owner
     * explicitly requested. Public for fixture-based privacy tests; it performs no I/O.
     */
    public static function scopedOrderLookup(array $orders, array $args, string $ownerQuestion): array
    {
        $id = strtolower(trim((string)($args['order_id'] ?? '')));
        $email = strtolower(trim((string)($args['customer_email'] ?? '')));
        if ($id === '' && $email === '') return ['error' => 'Give an order id or a customer email.'];

        if (!self::ownerRequestedLookup($ownerQuestion, $id, $email)) return ['error' => 'The owner must explicitly request this exact order id or customer email.'];

        $matches = [];
        $includeCity = preg_match('/\b(?:city|address|location)\b/i', $ownerQuestion) === 1;
        $includeTracking = preg_match('/\b(?:track|tracking|courier|delivery|shipment|shipped|where)\b/i', $ownerQuestion) === 1;
        foreach ($orders as $order) {
            $orderId = strtolower((string)($order['id'] ?? ''));
            if ($id !== '' && $orderId !== $id) continue;
            if ($email !== '' && strtolower((string)($order['customer_email'] ?? '')) !== $email) continue;
            $matches[] = self::summariseOrder($order, $includeCity, $includeTracking);
            if (count($matches) >= 10) break;
        }
        if ($matches === []) return ['found' => 0, 'message' => 'No order matched.'];
        return ['found' => count($matches), 'orders' => $matches];
    }

    /** Reject identifiers invented by the model; the owner must name them in this request. */
    public static function ownerRequestedLookup(string $question, string $id, string $email): bool
    {
        if ($id === '' && $email === '') return false;
        if (!preg_match('/\b(?:order|orders|tracking|shipment|delivery|purchase|customer)\b/i', $question)) return false;
        foreach ([$id, $email] as $identifier) {
            if ($identifier === '') continue;
            if (!preg_match('/(?<![a-z0-9@._-])' . preg_quote($identifier, '/') . '(?![a-z0-9@._-])/i', $question)) return false;
        }
        return true;
    }

    private static function summariseOrder(array $order, bool $includeCity, bool $includeTracking): array
    {
        $items = [];
        foreach (($order['items'] ?? []) as $item) {
            $items[] = [
                'name' => (string)($item['name'] ?? $item['slug'] ?? ''),
                'qty' => (int)($item['qty'] ?? 1),
                'line_total' => (float)($item['line_total'] ?? 0),
            ];
        }
        $summary = [
            'id' => (string)($order['id'] ?? ''),
            'status' => (string)($order['status'] ?? ''),
            'total' => (float)($order['total'] ?? 0),
            'placed_on' => substr((string)($order['created_at'] ?? ''), 0, 10),
            'items' => $items,
        ];
        if ($includeCity) $summary['shipping_city'] = (string)($order['shipping_city'] ?? '');
        if ($includeTracking) {
            $summary['courier'] = (string)($order['courier_name'] ?? '');
            $summary['tracking_id'] = (string)($order['tracking_id'] ?? '');
        }
        return $summary;
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
        try {
            $since = !empty($args['since'])
                ? new \DateTimeImmutable((string)$args['since'])
                : (new \DateTimeImmutable())->modify('-' . $days . ' days');
        } catch (\Throwable) {
            $since = (new \DateTimeImmutable())->modify('-30 days');
            $days = 30;
        }

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
            $refundedPaise = OrderService::refundedPaise($order);
            $net = max(0, (float)($order['total'] ?? 0) - $refundedPaise / 100);
            $revenue += $net;
            if ($net <= 0 && $refundedPaise > 0) continue;
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

    /** Words that mark an enquiry as being about a topic. */
    private const ENQUIRY_TOPICS = [
        'orders'   => ['order', 'ordered', 'purchase', 'invoice'],
        'delivery' => ['deliver', 'delivery', 'shipping', 'shipment', 'shipped', 'courier', 'track', 'tracking', 'parcel'],
        'payment'  => ['payment', 'paid', 'card', 'upi', 'razorpay', 'charge'],
        'refund'   => ['refund', 'cancel', 'cancellation', 'return', 'money back'],
        'product'  => ['product', 'pendant', 'ring', 'stock', 'size', 'item'],
    ];

    /**
     * Support enquiries, counted and grouped.
     *
     * "How many enquiries came for orders" previously answered 5 — which was the order
     * count from the statistics blob, not enquiries at all. There were none to count.
     * This returns the real figure, says so when it is zero, and states the words it
     * matched on, because "order-related" is an interpretation and the owner should be
     * able to see which one was used.
     */
    private function supportEnquiries(array $args): array
    {
        $topic = strtolower(trim((string)($args['topic'] ?? '')));
        $tickets = [];
        foreach ((new SupportTicketService($this->store))->all() as $ticket) {
            // A record holding nothing but an id is not an enquiry. Production has one,
            // and counting it would overstate a number the owner acts on.
            if (count(array_filter($ticket, fn($v, $k) => $k !== 'id' && $v !== '' && $v !== null, ARRAY_FILTER_USE_BOTH)) === 0) continue;
            $tickets[] = $ticket;
        }

        if ($tickets === []) {
            return [
                'total' => 0,
                'message' => 'No customer support enquiries have been recorded yet. '
                    . 'The support_tickets collection is empty apart from one placeholder row holding only an id.',
            ];
        }

        $byStatus = [];
        $byTopic = array_fill_keys(array_keys(self::ENQUIRY_TOPICS), 0);
        $unanswered = 0;
        $matched = [];
        foreach ($tickets as $ticket) {
            $status = (string)($ticket['status'] ?? 'open');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            if (trim((string)($ticket['reply'] ?? '')) === '') $unanswered++;
            $blob = strtolower(trim((string)($ticket['message'] ?? '') . ' ' . (string)($ticket['reply'] ?? '')));
            foreach (self::ENQUIRY_TOPICS as $name => $words) {
                foreach ($words as $word) {
                    if (!str_contains($blob, $word)) continue;
                    $byTopic[$name]++;
                    if ($name === $topic || $topic === '') $matched[] = ['id' => (string)($ticket['id'] ?? ''), 'status' => $status];
                    break;
                }
            }
        }

        $out = [
            'total' => count($tickets),
            'by_status' => $byStatus,
            'by_topic' => $byTopic,
            'awaiting_reply' => $unanswered,
            'topic_words_used' => self::ENQUIRY_TOPICS,
        ];
        if ($topic !== '') {
            $out['topic'] = $topic;
            $out['matching'] = $byTopic[$topic] ?? 0;
            $out['matching_tickets'] = array_slice($matched, 0, 20);
        }
        return $out;
    }

    /**
     * Published articles matching a query.
     *
     * Scored on how many of the query's own words appear in the title and summary, so
     * an answer can point at real content. BlogService::all() is the public filter, so
     * an unpublished post or one in a switched-off category cannot be cited.
     */
    private function searchArticles(array $args): array
    {
        $query = strtolower(trim((string)($args['query'] ?? '')));
        if ($query === '') return ['error' => 'Give something to search for.'];
        $words = array_filter(
            preg_split('/[^a-z0-9]+/', $query) ?: [],
            fn(string $w): bool => mb_strlen($w) >= 3
        );
        if ($words === []) return ['found' => 0, 'message' => 'Nothing specific enough to search for.'];

        $scored = [];
        foreach ((new BlogService())->all(false) as $post) {
            $title = strtolower((string)($post['title'] ?? ''));
            $summary = strtolower((string)($post['excerpt'] ?? $post['summary'] ?? ''));
            $score = 0;
            foreach ($words as $word) {
                if (str_contains($title, $word)) $score += 2;   // a title match is the stronger signal
                if (str_contains($summary, $word)) $score += 1;
            }
            if ($score === 0) continue;
            $scored[] = [
                'title' => (string)($post['title'] ?? ''),
                'path' => '/blog/' . (string)($post['slug'] ?? ''),
                'category' => (string)($post['category'] ?? ''),
                'score' => $score,
            ];
        }
        usort($scored, fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        if ($scored === []) return ['found' => 0, 'message' => 'No published article matched.'];
        return ['found' => count($scored), 'articles' => array_slice($scored, 0, 5)];
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
