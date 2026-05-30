<?php
namespace App\Services;

final class ReviewService {
    public function __construct(private JsonStoreService $store = new JsonStoreService()) {}

    public function saveAstrologerReview(array $data): array {
        return $this->save('astrologer', $data);
    }

    public function saveProductReview(array $data): array {
        return $this->save('product', $data);
    }

    public function summary(string $targetType, string $targetSlug): array {
        $reviews = array_values(array_filter(
            $this->store->read('reviews'),
            fn($review) => ($review['target_type'] ?? '') === $targetType && ($review['target_slug'] ?? '') === $targetSlug
        ));
        $count = count($reviews);
        $average = $count === 0 ? 0.0 : round(array_sum(array_map(fn($review) => (int)($review['rating'] ?? 0), $reviews)) / $count, 1);
        return ['average' => $average, 'count' => $count, 'reviews' => $reviews];
    }

    public function productReviewIsDue(array $order, ?\DateTimeImmutable $now = null): bool {
        $status = strtolower((string)($order['status'] ?? ''));
        if (!in_array($status, ['shipped', 'delivered'], true)) return false;
        $after = trim((string)($order['review_request_after_at'] ?? ''));
        if ($after === '') return false;
        $now ??= new \DateTimeImmutable('now');
        return new \DateTimeImmutable($after) <= $now;
    }

    private function save(string $targetType, array $data): array {
        $rating = max(1, min(5, (int)($data['rating'] ?? 0)));
        $record = [
            'id' => $data['id'] ?? bin2hex(random_bytes(8)),
            'target_type' => $targetType,
            'target_slug' => trim((string)($data['target_slug'] ?? '')),
            'rating' => $rating,
            'review' => trim((string)($data['review'] ?? '')),
            'customer_email' => trim((string)($data['customer_email'] ?? '')),
            'source_id' => trim((string)($data['source_id'] ?? '')),
            'created_at' => date('c'),
        ];
        if ($record['target_slug'] === '') {
            throw new \InvalidArgumentException('Review target is required.');
        }
        return $this->store->upsert('reviews', $record);
    }
}
