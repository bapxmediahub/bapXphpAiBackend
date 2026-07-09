<?php
namespace App\Services;

final class ReviewService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

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
        $targetSlug = trim((string)($data['target_slug'] ?? ''));
        $customerEmail = trim((string)($data['customer_email'] ?? ''));
        $sourceId = trim((string)($data['source_id'] ?? ''));
        if ($targetSlug === '') {
            throw new \InvalidArgumentException('Review target is required.');
        }
        if ($sourceId === '') {
            throw new \InvalidArgumentException('Review source is required.');
        }
        if (!$this->verifyPurchase($targetType, $targetSlug, $customerEmail, $sourceId)) {
            throw new \RuntimeException('Purchase verification failed. You must have a completed booking or order to review.');
        }
        $existing = $this->findDuplicate($targetSlug, $customerEmail, $sourceId);
        if ($existing) {
            throw new \RuntimeException('You have already submitted a review for this item.');
        }
        $record = [
            'id' => $data['id'] ?? bin2hex(random_bytes(8)),
            'target_type' => $targetType,
            'target_slug' => $targetSlug,
            'rating' => $rating,
            'review' => trim((string)($data['review'] ?? '')),
            'customer_email' => $customerEmail,
            'source_id' => $sourceId,
            'created_at' => date('c'),
        ];
        return $this->store->upsert('reviews', $record);
    }

    private function verifyPurchase(string $targetType, string $targetSlug, string $customerEmail, string $sourceId): bool {
        if ($targetType === 'astrologer') {
            foreach ($this->store->read('appointments') as $a) {
                if (($a['id'] ?? '') === $sourceId && ($a['customer_email'] ?? '') === $customerEmail && ($a['astrologer_slug'] ?? '') === $targetSlug) {
                    return true;
                }
            }
            return false;
        }
        foreach ($this->store->read('orders') as $o) {
            if (($o['id'] ?? '') === $sourceId && ($o['customer_email'] ?? '') === $customerEmail) {
                foreach ($o['items'] ?? [] as $item) {
                    if (($item['slug'] ?? '') === $targetSlug) return true;
                }
            }
        }
        return false;
    }

    private function findDuplicate(string $targetSlug, string $customerEmail, string $sourceId): ?array {
        foreach ($this->store->read('reviews') as $r) {
            if (($r['target_slug'] ?? '') === $targetSlug && ($r['customer_email'] ?? '') === $customerEmail && ($r['source_id'] ?? '') === $sourceId) {
                return $r;
            }
        }
        return null;
    }
}
