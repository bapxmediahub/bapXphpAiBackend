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
        return array_values(array_filter($this->store->read('categories'), fn(array $c): bool => !empty($c['is_collection'])));
    }

    public function collectionProducts(array $collection, array $products): array
    {
        $ids = is_array($collection['product_ids'] ?? null) ? $collection['product_ids'] : [];
        return array_values(array_filter($products, fn(array $p): bool => in_array((string)($p['id'] ?? ''), $ids, true)));
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
