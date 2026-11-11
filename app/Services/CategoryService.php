<?php
namespace App\Services;

final class CategoryService
{
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array
    {
        return array_values(array_filter(
            $this->store->read('categories'),
            fn(array $category): bool => empty($category['is_collection']) && trim((string)($category['slug'] ?? '')) !== ''
                && trim((string)($category['name'] ?? '')) !== ''
        ));
    }

    public function collections(): array
    {
        $collections = array_values(array_filter($this->store->read('categories'), fn(array $c): bool => !empty($c['is_collection'])));
        usort($collections, static fn(array $left, array $right): int =>
            ((int)($left['sort_order'] ?? 0) <=> (int)($right['sort_order'] ?? 0))
            ?: strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''))
        );
        return $collections;
    }

    public function collectionProducts(array $collection, array $products): array
    {
        $ids = is_array($collection['product_ids'] ?? null) ? array_map('strval', $collection['product_ids']) : [];
        $productsById = [];
        foreach ($products as $product) $productsById[(string)($product['id'] ?? '')] = $product;
        $ordered = [];
        foreach ($ids as $id) if (isset($productsById[$id])) $ordered[] = $productsById[$id];
        return $ordered;
    }

    public function homeCollections(array $products): array
    {
        $result = [];
        foreach ($this->collections() as $collection) {
            if (empty($collection['show_home'])) continue;
            $collection['products'] = $this->collectionProducts($collection, $products);
            if ($collection['products']) $result[] = $collection;
        }
        return $result;
    }
}
