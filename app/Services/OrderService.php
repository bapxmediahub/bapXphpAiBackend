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

    /** Working days a customer should expect, quoted in the shipment email. */
    public const DELIVERY_DAYS_DOMESTIC = '1 to 14 days';
    public const DELIVERY_DAYS_INTERNATIONAL = 'up to 24 days';

    /**
     * @param array $tracking ['tracking_id' => string, 'tracking_url' => string]
     *
     * Marking an order shipped requires both a courier tracking id and the courier's
     * tracking page. Without them the customer is told their parcel is on its way with
     * no way to follow it, and support has nothing to answer "where is my order" with.
     */
    public function updateStatus(string $id, string $status, ?\DateTimeImmutable $now = null, array $tracking = []): array {
        // Validate before touching the database: the tracking rule does not depend on
        // the order, and failing first avoids a pointless read (and lets the rule be
        // tested without database access).
        if ($status === 'shipped') {
            $trackingId = trim((string)($tracking['tracking_id'] ?? ''));
            $trackingUrl = trim((string)($tracking['tracking_url'] ?? ''));
            if ($trackingId === '') throw new \InvalidArgumentException('A courier tracking ID is required to mark an order shipped.');
            if ($trackingUrl === '') throw new \InvalidArgumentException('A courier tracking link is required to mark an order shipped.');
            if (!filter_var($trackingUrl, FILTER_VALIDATE_URL)) throw new \InvalidArgumentException('The courier tracking link must be a valid URL.');
        }

        $orders = $this->all();
        $updated = null;
        $now ??= new \DateTimeImmutable();

        foreach ($orders as &$order) {
            if (($order['id'] ?? '') !== $id) continue;
            $order['status'] = $status;
            $order['updated_at'] = $now->format('c');
            if (in_array($status, ['shipped', 'delivered'], true)) {
                if (!empty($tracking['tracking_id'])) $order['tracking_id'] = trim((string)$tracking['tracking_id']);
                if (!empty($tracking['tracking_url'])) $order['tracking_url'] = trim((string)$tracking['tracking_url']);
                if (!empty($tracking['courier_name'])) $order['courier_name'] = trim((string)$tracking['courier_name']);
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
