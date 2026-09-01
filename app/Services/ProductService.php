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
