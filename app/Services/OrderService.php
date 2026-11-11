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

    /** Correct a paid review record and issue its first immutable tax snapshot. */
    public function resolveTaxReview(string $id, string $shippingState, array $settings): array {
        return $this->store->resolveOrderTaxReview($id, $shippingState, $settings);
    }

    /** Working days a customer should expect, quoted in the shipment email. */
    public const DELIVERY_DAYS_DOMESTIC = '1 to 14 days';
    public const DELIVERY_DAYS_INTERNATIONAL = 'up to 24 days';

    /** Resolve a paid order hold only after the owner confirms availability. */
    public static function reviewFulfillment(array $order, string $status, bool $confirmed, \DateTimeImmutable $now): array {
        if (!empty($order['fulfillment_review_required']) && in_array($status, ['confirmed', 'processing', 'packed', 'shipped', 'delivered'], true)) {
            if (!$confirmed) throw new \InvalidArgumentException('Confirm availability before fulfilling this held order.');
            $order['fulfillment_review_required'] = false;
            $order['fulfillment_review_reason'] = '';
            $order['fulfillment_reviewed_at'] = $now->format('c');
        }
        return $order;
    }

    /**
     * @param array $tracking ['tracking_id' => string, 'tracking_url' => string]
     *
     * Marking an order shipped requires a courier and a tracking id. Without them the
     * customer is told their parcel is on its way with no way to follow it, and support
     * has nothing to answer "where is my order" with.
     *
     * The tracking link is no longer typed. The admin picks one of the couriers in
     * CourierService and the link comes from there, so a shipment email cannot carry a
     * mistyped URL. A link supplied explicitly is still honoured, for a courier that is
     * not on the list yet.
     */
    public function updateStatus(string $id, string $status, ?\DateTimeImmutable $now = null, array $tracking = []): array {
        // Validate before touching the database: the tracking rule does not depend on
        // the order, and failing first avoids a pointless read (and lets the rule be
        // tested without database access).
        if ($status === 'shipped') {
            $trackingId = trim((string)($tracking['tracking_id'] ?? ''));
            $courier = trim((string)($tracking['courier_name'] ?? ''));
            $trackingUrl = trim((string)($tracking['tracking_url'] ?? ''));
            if ($courier === '') throw new \InvalidArgumentException('Select the courier before marking an order shipped.');
            if ($trackingId === '') throw new \InvalidArgumentException('A courier tracking ID is required to mark an order shipped.');
            if ($trackingUrl === '' && CourierService::isKnown($courier)) {
                $trackingUrl = CourierService::trackingUrl($courier);
                $tracking['tracking_url'] = $trackingUrl;
            }
            if ($trackingUrl === '') throw new \InvalidArgumentException('A courier tracking link is required for a courier that is not on the list.');
            if (!filter_var($trackingUrl, FILTER_VALIDATE_URL)) throw new \InvalidArgumentException('The courier tracking link must be a valid URL.');
        }

        $updated = $this->store->updateOrderStatus($id, $status, $now ?? new \DateTimeImmutable(), $tracking);
        if (in_array($status, ['shipped', 'delivered'], true)) {
            $this->mailQueue->enqueueShipmentNotification($updated);
            $this->mailQueue->enqueueProductReviewRequest($updated, 10);
        }
        return $updated;
    }
}
