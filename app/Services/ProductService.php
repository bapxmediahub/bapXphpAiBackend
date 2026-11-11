<?php
namespace App\Services;

/**
 * Products, with visibility and the offer window already applied.
 *
 * Two rules used to have nowhere to live.
 *
 * A product could not be taken off the shop. There was no status field — stock_status
 * is stock, not visibility — so the only way to stop selling something was to delete
 * it, which loses the record that past orders point at. Blog posts already follow
 * hide-never-delete; products now do too.
 *
 * An offer price had no end. offer_price applied from the moment it was typed until
 * somebody remembered to clear it, so a festival discount kept discounting for as long
 * as the owner forgot. offer_starts_at and offer_ends_at bound it.
 *
 * Both are applied here, at read time, rather than at each of the eight places that
 * read a price. The cart, the product page, the API, the sitemap and the agent were
 * all reading offer_price directly; a rule enforced in one of them and missed in
 * another is how a shopper sees one price and is charged a different one.
 */
final class ProductService
{
    private const RASI_TERMS = [
        ['mesham', 'aries'], ['rishabam', 'taurus'], ['mithunam', 'gemini'],
        ['kadagam', 'cancer'], ['simmam', 'leo'], ['kanni', 'virgo'],
        ['thulam', 'libra'], ['viruchigam', 'vrishchik', 'scorpio'],
        ['dhanusu', 'dhanu', 'sagittarius'], ['magaram', 'capricorn'],
        ['kumbam', 'aquarius'], ['meenam', 'pisces'],
    ];
    private const GEMSTONE_TERMS = [
        ['red coral', 'pavazham', 'sivapu pavalam'],
        ['yellow sapphire', 'pushparagam'], ['blue sapphire', 'neelam'],
        ['emerald', 'maragatham'], ['pearl', 'muthu'], ['ruby', 'manikyam'],
        ['hessonite', 'gomed'], ["cat's eye", 'cats eye', 'vaiduryam'], ['diamond'],
    ];

    public function __construct(
        private DatabaseService $store = new DatabaseService(),
        private ?\DateTimeImmutable $now = null
    ) {}

    /** Every product the admin owns, with the offer window applied. */
    public function all(): array
    {
        return array_map(fn(array $p): array => $this->normalise($p), $this->store->read('products'));
    }

    /** Only what a shopper may see. Hidden products stay out of listings and search. */
    public function visible(): array
    {
        return array_values(array_filter($this->all(), fn(array $p): bool => empty($p['is_hidden'])));
    }

    /** Search and order the visible products in the current category or collection. */
    public function discover(array $products, string $search, string $sort): array
    {
        $keywords = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($keywords !== []) {
            $products = array_values(array_filter($products, static function (array $product) use ($keywords): bool {
                $categories = (array)($product['categories'] ?? []);
                $text = implode(' ', [
                    $product['name'] ?? '',
                    str_replace(['-', '_'], ' ', $product['slug'] ?? ''),
                    $product['category'] ?? '',
                    implode(' ', $categories),
                    html_entity_decode(strip_tags($product['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ]);
                foreach ($keywords as $keyword) {
                    if (mb_stripos($text, $keyword) === false) return false;
                }
                return true;
            }));
        }
        if ($sort === 'name') {
            usort($products, static fn(array $a, array $b): int => strnatcasecmp($a['name'] ?? '', $b['name'] ?? ''));
        } elseif ($sort === 'price-asc' || $sort === 'price-desc') {
            $direction = $sort === 'price-asc' ? 1 : -1;
            usort($products, fn(array $a, array $b): int => $direction * ($this->priceOf($a) <=> $this->priceOf($b)));
        }
        return $products;
    }

    /** Rank only visible products using quantities from verified, non-cancelled orders. */
    public static function rankBestSellers(array $products, array $orders, int $limit = 4): array
    {
        $counts = [];
        foreach ($orders as $order) {
            if (trim((string)($order['payment_id'] ?? '')) === '' || in_array(strtolower((string)($order['status'] ?? '')), ['cancelled', 'refunded', 'failed'], true)) continue;
            $items = $order['items'] ?? [];
            if (!is_array($items)) continue;
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $slug = trim((string)($item['slug'] ?? ''));
                $qty = (int)($item['qty'] ?? 0);
                if ($slug !== '' && $qty > 0) $counts[$slug] = ($counts[$slug] ?? 0) + $qty;
            }
        }
        $ranked = array_values(array_filter($products, static fn(array $p): bool => empty($p['is_hidden']) && ($counts[trim((string)($p['slug'] ?? ''))] ?? 0) > 0));
        usort($ranked, static function (array $a, array $b) use ($counts): int {
            $left = trim((string)$a['slug']); $right = trim((string)$b['slug']);
            return ($counts[$right] <=> $counts[$left]) ?: strcmp($left, $right);
        });
        return array_slice($ranked, 0, max(0, $limit));
    }

    public function bestSellers(array $products, int $limit = 4): array
    {
        // Order records also contain addresses, contact details and payment identifiers.
        // Filter paid orders at the database; return only items and status for ranking.
        // The orders collection stores records as JSON in _data on both direct MySQL
        // and the remote database bridge.
        $rows = $this->store->query("SELECT JSON_EXTRACT(_data, '$.items') AS items, JSON_EXTRACT(_data, '$.status') AS status FROM orders
            WHERE JSON_TYPE(JSON_EXTRACT(_data, '$.payment_id')) = 'STRING'
              AND TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                  JSON_UNQUOTE(JSON_EXTRACT(_data, '$.payment_id')),
                  CHAR(0), ''), CHAR(9), ''), CHAR(10), ''), CHAR(11), ''), CHAR(13), '')) <> ''");
        $orders = array_map(static fn(array $row): array => [
            'payment_id' => 'verified',
            'status' => json_decode((string)($row['status'] ?? ''), true),
            'items' => json_decode((string)($row['items'] ?? ''), true),
        ], $rows);
        return self::rankBestSellers($products, $orders, $limit);
    }

    /**
     * A hidden product is not found by slug either: leaving the URL live would keep it
     * buyable by anyone holding the link, which is not what "hidden" means.
     */
    public function findBySlug(string $slug, bool $includeHidden = false): ?array
    {
        $slug = trim(rawurldecode($slug));
        foreach ($this->all() as $item) {
            if (($item['slug'] ?? '') !== $slug) continue;
            if (!$includeHidden && !empty($item['is_hidden'])) return null;
            return $item;
        }
        return null;
    }

    /** Slug => product, for resolving a cart. Hidden products are excluded. */
    public function bySlug(): array
    {
        $map = [];
        foreach ($this->visible() as $product) {
            $slug = (string)($product['slug'] ?? '');
            if ($slug !== '') $map[$slug] = $product;
        }
        return $map;
    }

    /** The price a shopper actually pays, offer window included. */
    public function priceOf(array $product): float
    {
        $product = isset($product['is_hidden']) ? $product : $this->normalise($product);
        $offer = (float)($product['offer_price'] ?? 0);
        $price = (float)($product['price'] ?? 0);
        return $offer > 0 ? $offer : $price;
    }

    public function save(array $item): array
    {
        return $this->store->upsert('products', $item);
    }

    public function delete(string $id): void
    {
        $items = array_filter($this->store->read('products'), fn($i) => ($i['id'] ?? '') !== $id);
        $this->store->write('products', array_values($items));
    }

    /**
     * Detect a pasted description that names a different structured zodiac or stone.
     * The structured specification remains the owner's source of truth; this guard
     * deliberately reports only explicit contradictions and makes no product claims.
     */
    public static function contentConflict(array $product): ?string
    {
        $description = strtolower(trim((string)($product['description'] ?? '')));
        $specifications = is_array($product['specifications'] ?? null) ? $product['specifications'] : [];
        if ($description === '' || $specifications === []) return null;
        $normalised = [];
        foreach ($specifications as $label => $value) $normalised[strtolower(trim((string)$label))] = strtolower(trim((string)$value));
        foreach ([
            ['label' => 'Rasi', 'value' => ($normalised['rasi'] ?? ''), 'groups' => self::RASI_TERMS],
            ['label' => 'Gemstone', 'value' => ($normalised['gemstone'] ?? ''), 'groups' => self::GEMSTONE_TERMS],
        ] as $check) {
            $expected = self::matchingTermGroup($check['value'], $check['groups']);
            if ($expected === null) continue;
            foreach ($check['groups'] as $terms) {
                if ($terms === $expected) continue;
                foreach ($terms as $term) {
                    if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $description)) {
                        return 'Description conflicts with the ' . $check['label'] . ' specification: it mentions “' . $term . '”.';
                    }
                }
            }
        }
        return null;
    }

    private static function matchingTermGroup(string $value, array $groups): ?array
    {
        foreach ($groups as $terms) {
            foreach ($terms as $term) if (str_contains($value, $term)) return $terms;
        }
        return null;
    }

    /**
     * Adds `is_hidden`, and clears `offer_price` when the offer is not running.
     *
     * Clearing rather than flagging means every existing reader — which all treat an
     * empty offer_price as "no offer" — becomes correct without being touched.
     */
    private function normalise(array $product): array
    {
        // Legacy admin records may contain accidental leading/trailing whitespace.
        // Browsers and web servers trim that whitespace from the route, which made a
        // visible product card lead to a 404 even though the product existed.
        $product['slug'] = trim((string)($product['slug'] ?? ''));
        $product['is_hidden'] = $this->isHidden($product);
        if (!empty($product['offer_price']) && !$this->offerIsRunning($product)) {
            $product['offer_price'] = null;
        }
        return $product;
    }

    private function isHidden(array $product): bool
    {
        // Absent status means visible: every product that predates this field stays on
        // the shop rather than vanishing on deploy.
        $status = strtolower(trim((string)($product['status'] ?? '')));
        if ($status === '') return false;
        return in_array($status, ['hidden', 'inactive', 'draft', 'disabled'], true);
    }

    private function offerIsRunning(array $product): bool
    {
        $now = $this->now ?? new \DateTimeImmutable();
        $starts = $this->date($product['offer_starts_at'] ?? '');
        if ($starts !== null && $now < $starts) return false;
        $ends = $this->date($product['offer_ends_at'] ?? '');
        if ($ends !== null) {
            // A date with no time means the offer runs to the end of that day.
            if (!str_contains((string)$product['offer_ends_at'], ':')) $ends = $ends->setTime(23, 59, 59);
            if ($now > $ends) return false;
        }
        return true;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        try { return new \DateTimeImmutable($value); }
        catch (\Throwable) { return null; }
    }
}
