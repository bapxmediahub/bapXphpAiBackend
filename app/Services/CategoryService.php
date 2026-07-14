<?php
namespace App\Services;

final class CategoryService
{
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array
    {
        return array_values(array_filter(
            $this->store->read('categories'),
            fn(array $category): bool => trim((string)($category['slug'] ?? '')) !== ''
                && trim((string)($category['name'] ?? '')) !== ''
        ));
    }
}
