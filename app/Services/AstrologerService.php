<?php
namespace App\Services;
final class AstrologerService { public function __construct(private DatabaseService $store = new DatabaseService()){} public function all(): array{return $this->store->read('astrologers');} public function findBySlug(string $slug): ?array{foreach($this->all() as $item) if(($item['slug']??'')===$slug) return $item; return null;} }
