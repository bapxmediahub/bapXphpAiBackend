<?php
namespace App\Services;

final class OrderService {
    public function __construct(
        private DatabaseService $store = new DatabaseService(),
        private ?MailQueueService $mailQueue = null
    ) {
        $this->mailQueue ??= new MailQueueService($this->store);
    }

    public function all(): array {
        return $this->store->read('orders');
    }

    public function updateStatus(string $id, string $status, ?\DateTimeImmutable $now = null): array {
        $orders = $this->all();
        $updated = null;
        $now ??= new \DateTimeImmutable();
        foreach ($orders as &$order) {
            if (($order['id'] ?? '') !== $id) continue;
            $order['status'] = $status;
            $order['updated_at'] = $now->format('c');
            if (in_array($status, ['shipped', 'delivered'], true)) {
                $order['shipped_at'] = $order['shipped_at'] ?? $now->format('c');
                $shippedAt = new \DateTimeImmutable($order['shipped_at']);
                $order['review_request_after_at'] = $shippedAt->modify('+10 days')->format('c');
                $this->mailQueue->enqueueShipmentNotification($order);
                $this->mailQueue->enqueueProductReviewRequest($order, 10);
            }
            $updated = $order;
            break;
        }
        unset($order);
        if (!$updated) {
            throw new \RuntimeException('Order not found.');
        }
        $this->store->write('orders', $orders);
        return $updated;
    }
}
