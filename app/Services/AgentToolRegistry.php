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
    /** @var array<int,array{name:string,status:string,duration_ms:int}> */
    private array $activity = [];

    /** @return array<int,array{name:string,status:string,duration_ms:int}> */
    public function activity(): array
    {
        return $this->activity;
    }

    private array $sources = [];
    private array $completedTicketSummaries = [];

    public function ticketLookupFallback(): ?string
    {
        if (!$this->completedTicketSummaries) return null;
        return "The model could not finish its summary. These ticket lookups completed:\n"
            . implode("\n", array_map(static fn(string $summary): string => '- ' . $summary,
                array_slice(array_keys($this->completedTicketSummaries), 0, 8)));
    }

    /** @return list<array{label:string,path:string}> */
    public function sourceLinks(): array
    {
        return array_values($this->sources);
    }

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
                'description' => 'Verified paid orders, net revenue after verified refunds, status counts and best sellers by units. Use dates, a number of days, or a named period.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => self::DATE_PROPERTIES + [
                        'days' => ['type' => 'integer', 'description' => 'How many calendar days to include through today. Defaults to 30.'],
                        'period' => ['type' => 'string', 'enum' => ['this_month', 'all_time']],
                    ],
                ],
            ],
            [
                'name' => 'gst_register_summary',
                'description' => 'Read the GST invoice register for a date range, including the number of issued invoices and review flags, taxable value, and GST totals. Includes the admin report link.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => self::DATE_PROPERTIES,
                ],
            ],
            [
                'name' => 'support_enquiries',
                'description' => 'Count recorded customer support tickets by date created, topic and current status. Includes the support queue link and the keywords behind topic counts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => self::DATE_PROPERTIES + [
                        'topic' => ['type' => 'string', 'enum' => array_keys(self::ENQUIRY_TOPICS)],
                        'status' => ['type' => 'string', 'enum' => self::TICKET_STATUSES],
                    ],
                ],
            ],
            [
                'name' => 'store_count',
                'description' => 'Count recorded users, products or appointments. Returns a total without record details.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['entity' => ['type' => 'string', 'enum' => AgentContextService::OWNER_COUNT_ENTITIES]],
                    'required' => ['entity'],
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
        return $this->toolParameters($name) !== null;
    }

    private function toolParameters(string $name): ?array
    {
        foreach ($this->declarations() as $tool) {
            if ($tool['name'] === $name) return $tool['parameters'];
        }
        return null;
    }

    /** Provider tool arguments are untrusted, even when generated from our schema. */
    private function validArguments(string $name, array $args): bool
    {
        $schema = $this->toolParameters($name);
        if ($schema === null || array_diff($schema['required'] ?? [], array_keys($args))) return false;
        foreach ($args as $key => $value) {
            $property = $schema['properties'][$key] ?? [];
            $validType = match ($property['type'] ?? '') {
                'integer' => is_int($value),
                'string' => is_string($value) && mb_strlen($value) <= 256,
                default => false,
            };
            if (!$validType || (isset($property['enum']) && !in_array($value, $property['enum'], true))) return false;
        }
        return true;
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
        $startedAt = microtime(true);
        try {
            $result = $this->validArguments($name, $args) ? match ($name) {
                'find_order' => $this->findOrder($args),
                'product_status' => $this->productStatus($args),
                'sales_summary' => $this->salesSummary($args),
                'gst_register_summary' => $this->gstRegisterSummary($args),
                'store_count' => (new AgentContextService($this->store))->ownerCount($args['entity'] ?? null),
                'support_enquiries' => $this->supportEnquiries($args),
                'search_articles' => $this->searchArticles($args),
                'coupon_status' => $this->couponStatus($args),
                default => ['error' => 'Lookup unavailable.'],
            } : ['error' => 'Invalid lookup parameters.'];
            $this->activity[] = [
                'name' => $name,
                'status' => isset($result['error']) ? 'error' : 'complete',
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ];
            if ($name === 'support_enquiries' && !isset($result['error'])) {
                $this->completedTicketSummaries[self::ticketSummary($result)] = true;
            }
            if (!isset($result['error']) && isset($result['report_url'])) {
                $label = match ($name) {
                    'gst_register_summary' => 'GST invoice register',
                    'support_enquiries' => 'AI Support Tickets',
                    'sales_summary' => 'Sales report',
                    default => null,
                };
                if ($label !== null) $this->sources[$result['report_url']] = ['label' => $label, 'path' => $result['report_url']];
            }
            return $result;
        } catch (\Throwable $e) {
            error_log('Agent tool ' . $name . ' failed (' . get_class($e) . ')');
            $this->activity[] = [
                'name' => $name,
                'status' => 'error',
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ];
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
        if (($report = $this->reportAnswer($question)) !== null) return $report;
        if (preg_match('/\b(?:gst|tax invoice|support tickets?|enquir(?:y|ies)|inquir(?:y|ies))\b/i', $question)) return null;
        $intent = self::aggregateIntent($question);
        if ($intent === null) return null;
        $days = self::aggregateDays($question);
        $args = match (true) {
            $days === 0 => ['period' => 'this_month'],
            $days >= 36500 => ['period' => 'all_time'],
            default => ['days' => $days],
        };
        $summary = $intent === 'order_count'
            ? ['orders' => $this->countOrders($days)]
            : $this->run('sales_summary', $args);
        if (isset($summary['error'])) return $summary['error'];
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
                . " across {$orders} paid orders after verified refunds.";
        }

        $best = is_array($summary['best_sellers'] ?? null) ? $summary['best_sellers'] : [];
        if ($best === []) {
            return 'There are no sold product units to rank ' . $period
                . ' in the paid orders included by the sales report.';
        }
        $items = [];
        foreach (array_slice($best, 0, 5, true) as $name => $units) {
            $items[] = trim((string)$name) . ' — ' . max(0, (int)$units) . ' unit' . ((int)$units === 1 ? '' : 's');
        }
        return 'Best sellers by units ' . $period . ': ' . implode('; ', $items)
            . '. Units reflect paid orders before returns.';
    }

    private static function aggregateIntent(string $question): ?string
    {
        $period = '(?: (?:in |over |for )?(?:the )?(?:(?:last|past) [1-9]\d{0,3} days?|this month|all[ -]time|ever|overall))?';
        if (preg_match('/^\s*(?:(?:show(?: me)?(?: the)? |list(?: the)? )?(?:best[ -]?sell(?:ers?|ing products)|top[ -]?sell(?:ers?|ing products))|(?:which|what) products sell(?: the)? (?:best|most))' . $period . '[?!.]*\s*$/i', $question)) {
            return 'best_sellers';
        }
        $orderCountPattern = '/^\s*
            (?:how\ many\ orders(?:\ (?:have\ we\ had|did\ we\ get|were\ placed|did\ we\ receive))?
                |(?:what\ is\ the\ )?(?:total\ orders|order\ count))
            (?:\ (?:in\ |over\ |for\ )?(?:the\ )?
                (?:(?:last|past)\ [1-9]\d{0,3}\ days?|this\ month|all[ -]time|ever|overall))?
            [?!.]*\s*$/ix';
        if (preg_match($orderCountPattern, $question)) return 'order_count';
        if (preg_match('/^\s*(?:(?:what is|show(?: me)?|calculate) (?:our |the )?)?(?:total |net )?(?:revenue|sales total|total sales|sales value)' . $period . '[?!.]*\s*$/i', $question)) return 'revenue';
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
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Kolkata'));
        $period = $args['period'] ?? null;
        $days = $args['days'] ?? null;
        $hasDates = isset($args['from']) || isset($args['to']);
        if (($period !== null && ($days !== null || $hasDates)) || ($days !== null && $hasDates)) {
            return ['error' => 'Choose sales dates, a number of days, or a named period.'];
        }
        if ($days !== null && (!is_int($days) || $days < 1 || $days > 36500)) {
            return ['error' => 'Use a positive whole number of sales days up to 36500.'];
        }
        if ($period !== null && !in_array($period, ['this_month', 'all_time'], true)) {
            return ['error' => 'Choose this_month or all_time for the named sales period.'];
        }

        $range = match ($period) {
            'this_month' => ['from' => $today->modify('first day of this month')->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
            'all_time' => ['from' => '0001-01-01', 'to' => $today->format('Y-m-d')],
            default => self::reportDates($days === null ? $args : ['from' => $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d')], 36500),
        };
        if ($range === null) return ['error' => 'Use valid sales dates (YYYY-MM-DD) through today.'];

        return self::salesFacts($this->orders->all(), $range);
    }

    public static function salesFacts(array $orders, array $range): array
    {
        $from = $range['from'];
        $to = $range['to'];
        $sales = StoreStatsService::sales($orders, $from, $to);
        $products = array_slice(StoreStatsService::productsPurchased($orders, $from, $to), 0, 5);
        return $range + [
            'orders' => array_sum(array_column($sales, 'orders')),
            'orders_by_status' => StoreStatsService::orderStatusCounts($orders, $from, $to),
            'revenue' => round(array_sum(array_column($sales, 'net')), 2),
            'currency' => 'INR',
            'best_sellers' => array_column($products, 'units', 'product'),
            'report_url' => '/admin/tax-report?' . http_build_query(['report' => 'sales'] + $range),
        ];
    }

    private const DATE_PROPERTIES = [
        'from' => ['type' => 'string', 'description' => 'Inclusive start date YYYY-MM-DD. Defaults to 29 days before today.'],
        'to' => ['type' => 'string', 'description' => 'Inclusive end date YYYY-MM-DD. Defaults to today (India time).'],
    ];

    private const DATE_ERROR = 'Use valid start and end dates (YYYY-MM-DD) covering at most 366 days through today.';
    private const TICKET_STATUSES = ['open', 'answered', 'closed', 'unanswered'];

    /** Topic matching uses whole words from the original customer enquiry. */
    private const ENQUIRY_TOPICS = [
        'orders'   => ['order', 'orders', 'ordered', 'purchase', 'invoice'],
        'delivery' => ['deliver', 'delivered', 'delivery', 'shipping', 'shipment', 'shipped', 'courier', 'track', 'tracking', 'parcel'],
        'payment'  => ['payment', 'payments', 'paid', 'card', 'upi', 'razorpay', 'charge'],
        'refund'   => ['refund', 'refunds', 'cancel', 'cancellation', 'return', 'money back'],
        'product'  => ['product', 'products', 'pendant', 'ring', 'stock', 'size', 'item'],
    ];

    /** Recognize only simple count and summary requests so qualifiers reach the model. */
    private function reportAnswer(string $question): ?string
    {
        $question = strtolower(trim($question, " \t\n\r?! ."));
        $period = '';
        if (preg_match('/(?:\s+(?:in|for|over))?\s+(?:the\s+)?(this month|last month|(?:last|past) [1-9]\d{0,2} days?)$/D', $question, $matches, PREG_OFFSET_CAPTURE)) {
            $period = $matches[1][0];
            $question = substr($question, 0, $matches[0][1]);
        }

        $tool = null;
        $filters = [];
        if (preg_match('/^(?:(?:show(?: me)?(?: the)?|summarize|what is the) )?gst (?:invoice register|register|summary|reconciliation|total|totals|tax total|invoice totals)$/D', $question)
            || preg_match('/^(?:summarize|summarise) gst$/D', $question)
            || preg_match('/^how many (?:gst )?invoices (?:need review|require review|were issued)$/D', $question)) {
            $tool = 'gst_register_summary';
        } elseif (preg_match('/^(?:how many|count) (?:(open|answered|closed|unanswered) )?(?:(?:support|customer) )?(?:tickets?|enquir(?:y|ies)|inquir(?:y|ies))(?: (?:came )?(?:about|for) (orders?|delivery|payment|refunds?|products?))?(?: are there| do we have)?$/D', $question, $matches)) {
            $tool = 'support_enquiries';
            if (!empty($matches[1])) $filters['status'] = $matches[1];
            if (!empty($matches[2])) {
                $topic = rtrim($matches[2], 's');
                $filters['topic'] = $topic === 'order' ? 'orders' : $topic;
            }
        } elseif ($period === '' && preg_match('/^(?:how many|count|total) (?:registered )?(users|accounts|products|appointments)(?: (?:are there|do we have))?$/D', $question, $matches)) {
            $entity = $matches[1] === 'accounts' ? 'users' : $matches[1];
            $result = $this->run('store_count', ['entity' => $entity]);
            return $result['error'] ?? 'Recorded ' . $entity . ': ' . $result['count'] . '.';
        }
        if ($tool === null) return null;

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Kolkata'));
        if ($period === 'this month') $filters['from'] = $today->modify('first day of this month')->format('Y-m-d');
        if ($period === 'last month') {
            $filters['from'] = $today->modify('first day of last month')->format('Y-m-d');
            $filters['to'] = $today->modify('last day of last month')->format('Y-m-d');
        }
        if (preg_match('/\d+/', $period, $days)) {
            $filters['from'] = $today->modify('-' . ((int)$days[0] - 1) . ' days')->format('Y-m-d');
        }

        $result = $this->run($tool, $filters);
        if (isset($result['error'])) return $result['error'];
        $dates = $result['from'] . ' to ' . $result['to'];
        if ($tool === 'support_enquiries') return self::ticketSummary($result);

        $totals = $result['totals'];
        $money = static fn(float $amount): string => '₹' . number_format($amount, 2);
        return "From {$dates}, the register has {$result['issued_invoices']} issued invoices. "
            . "The invoice lines export flags {$result['review_invoices']} for review. "
            . 'Taxable value is ' . $money($totals['taxable']) . ' and total GST is ' . $money($totals['tax'])
            . ' (CGST ' . $money($totals['cgst']) . ', SGST ' . $money($totals['sgst']) . ', IGST ' . $money($totals['igst']) . ').';
    }

    private static function ticketSummary(array $result): string
    {
        $status = $result['status'] !== '' ? $result['status'] . ' ' : '';
        $topic = $result['topic'] !== '' ? ' about ' . $result['topic'] : '';
        $dates = $result['from'] . ' to ' . $result['to'];
        $summary = "There were {$result['total']} {$status}support tickets{$topic} created from {$dates}.";
        if ($status === '' && $result['by_status'] !== []) {
            $counts = [];
            foreach ($result['by_status'] as $state => $count) $counts[] = $count . ' ' . $state;
            $summary .= ' Current statuses: ' . implode(', ', $counts) . '.';
        }
        if ($topic !== '') $summary .= ' Topic counts use keywords in the original enquiry.';
        return $summary;
    }

    private static function reportDates(array $args, int $maxDays = 366): ?array
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Kolkata'));
        $from = $args['from'] ?? $today->modify('-29 days')->format('Y-m-d');
        $to = $args['to'] ?? $today->format('Y-m-d');
        foreach ([$from, $to] as $value) {
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $today->getTimezone());
            if ($parsed === false || $parsed->format('Y-m-d') !== $value) return null;
        }
        if ($from > $to || $to > $today->format('Y-m-d')) return null;
        $earliest = (new \DateTimeImmutable($to, $today->getTimezone()))->modify('-' . ($maxDays - 1) . ' days')->format('Y-m-d');
        return $from >= $earliest ? ['from' => $from, 'to' => $to] : null;
    }

    /** Bind a single relative calendar window to the owner's words, in India time. */
    private function withOwnerRelativeWindow(array $args): array
    {
        $words = ['one'=>1, 'two'=>2, 'three'=>3, 'four'=>4, 'five'=>5,
            'six'=>6, 'seven'=>7, 'eight'=>8, 'nine'=>9, 'ten'=>10];
        $number = '\d{1,3}|' . implode('|', array_keys($words));
        $pattern = '/\b(?:last|past)\s+(' . $number . ')\s+days?\b/i';
        if (preg_match_all($pattern, $this->ownerQuestion, $matches) !== 1) return $args;
        $rest = preg_replace($pattern, '', $this->ownerQuestion);
        if (preg_match('/\b(?:since|between|before|after|yesterday|previous|excluding|except|last|past|week|month|quarter|year)\b|\d{4}-\d{2}-\d{2}/i', $rest)) {
            return $args;
        }
        $value = strtolower($matches[1][0]);
        $days = $words[$value] ?? (int)$value;
        if ($days < 1 || $days > 366) return $args;

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Kolkata'));
        $end = $today->format('Y-m-d');
        $extraDay = $today->modify('-' . $days . ' days')->format('Y-m-d');
        if (isset($args['from']) || isset($args['to'])) {
            // Correct the observed inclusive off-by-one. Preserve deliberate subranges
            // such as a model asking for one day at a time for a daily breakdown.
            if (($args['from'] ?? null) !== $extraDay || ($args['to'] ?? $end) !== $end) return $args;
        }
        $args['from'] = $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $args['to'] = $end;
        return $args;
    }

    private function gstRegisterSummary(array $args): array
    {
        $range = self::reportDates($this->withOwnerRelativeWindow($args));
        if ($range === null) return ['error' => self::DATE_ERROR];
        $orders = GstRegisterService::ordersForPeriod($this->orders->all(), $range['from'], $range['to']);
        $settings = (new SettingsService($this->store))->public();
        $reasons = GstRegisterService::reviewReasons($orders, $settings);

        return $range + [
            'issued_invoices' => count($orders),
            'delivery_state_review_invoices' => count($reasons),
            'review_invoices' => GstRegisterService::reviewCount($orders, $reasons),
            'totals' => GstRegisterService::totals($orders),
            'currency' => 'INR',
            'report_url' => '/admin/tax-report?' . http_build_query(['report' => 'tax'] + $range),
        ];
    }

    private function supportEnquiries(array $args): array
    {
        $range = self::reportDates($this->withOwnerRelativeWindow($args));
        if ($range === null) return ['error' => self::DATE_ERROR];
        $topic = $args['topic'] ?? '';
        $status = $args['status'] ?? '';
        if (!is_string($topic) || ($topic !== '' && !isset(self::ENQUIRY_TOPICS[$topic]))) {
            return ['error' => 'Choose an available support ticket topic.'];
        }
        if (!is_string($status) || ($status !== '' && !in_array($status, self::TICKET_STATUSES, true))) {
            return ['error' => 'Choose open, answered, closed or unanswered for support ticket status.'];
        }

        return self::supportEnquiryCounts((new SupportTicketService($this->store))->all(), $range, $topic, $status);
    }

    public static function supportEnquiryCounts(array $tickets, array $range, string $topic = '', string $status = ''): array
    {
        $total = 0;
        $byStatus = [];
        $byTopic = array_fill_keys(array_keys(self::ENQUIRY_TOPICS), 0);
        $unanswered = 0;
        $zone = new \DateTimeZone('Asia/Kolkata');
        $patterns = array_map(
            static fn(array $words): string => '/\b(?:' . implode('|', array_map(static fn(string $word): string => preg_quote($word, '/'), $words)) . ')\b/iu',
            self::ENQUIRY_TOPICS
        );
        foreach ($tickets as $ticket) {
            $message = trim((string)($ticket['message'] ?? ''));
            if ($message === '') continue;
            $createdAt = (string)($ticket['created_at'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:$|[T ])/D', $createdAt)) continue;
            try {
                $date = (new \DateTimeImmutable($createdAt, $zone))->setTimezone($zone)->format('Y-m-d');
            } catch (\Exception) {
                continue;
            }
            if ($date < $range['from'] || $date > $range['to']) continue;

            $ticketStatus = $ticket['status'] ?? 'open';
            if (!in_array($ticketStatus, ['open', 'answered', 'closed'], true)) $ticketStatus = 'unknown';
            $awaitingReply = $ticketStatus === 'open' && trim((string)($ticket['reply'] ?? '')) === '';
            if ($status !== '' && ($status === 'unanswered' ? !$awaitingReply : $status !== $ticketStatus)) continue;

            $topics = [];
            foreach ($patterns as $name => $pattern) {
                if (preg_match($pattern, $message)) $topics[] = $name;
            }
            if ($topic !== '' && !in_array($topic, $topics, true)) continue;
            foreach ($topics as $name) $byTopic[$name]++;
            $total++;
            $byStatus[$ticketStatus] = ($byStatus[$ticketStatus] ?? 0) + 1;
            if ($awaitingReply) $unanswered++;
        }
        return $range + [
            'total' => $total,
            'by_status' => $byStatus,
            'by_topic' => $byTopic,
            'awaiting_reply' => $unanswered,
            'topic' => $topic,
            'status' => $status,
            'topic_words_used' => self::ENQUIRY_TOPICS,
            'report_url' => '/admin/support-tickets',
        ];
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
