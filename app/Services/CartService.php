<?php
declare(strict_types=1);
namespace App\Services;

/** Browser cart resolution and shared quantity rules; products and prices are never stored in a cart. */
final class CartService
{
    public const MAX_QUANTITY = 99;

    public function __construct(private ?ProductService $products = null) {}

    public function items(): array { return $_SESSION['cart'] ?? []; }

    public function resolve(array $lines): array
    {
        if ($lines === []) return [];
        $service = $this->products ?? new ProductService();
        $products = $service->bySlug();
        $items = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !is_string($line['slug'] ?? null)) continue;
            $slug = $line['slug'];
            if (!isset($products[$slug])) continue;
            $product = $products[$slug];
            $quantity = max(0, min(self::MAX_QUANTITY, (int)($line['qty'] ?? 1)));
            $price = $service->priceOf($product);
            if ($quantity === 0 || !is_finite($price) || $price < 0) continue;
            // One paise-accurate amount for browser cart, checkout and agent estimates.
            $price = round($price, 2);
            $items[] = ['product'=>$product, 'slug'=>$slug, 'name'=>$product['name'],
                'image_url'=>$product['image_url'] ?? '', 'category'=>$product['category'] ?? '',
                'price'=>$product['price'], 'offer_price'=>$product['offer_price'] ?? null,
                'unit_price'=>$price, 'qty'=>$quantity, 'line_total'=>round($price * $quantity, 2)];
        }
        return $items;
    }

    public static function total(array $items): float
    {
        return round(array_sum(array_column($items, 'line_total')), 2);
    }
}
