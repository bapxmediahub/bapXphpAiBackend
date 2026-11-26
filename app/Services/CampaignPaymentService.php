<?php
declare(strict_types=1);

namespace App\Services;

use App\Integrations\Razorpay\PaymentLinkCreationRejected;

/** A booking snapshots a published page and has its own receipt, independent of shop checkout. */
final class CampaignPaymentService
{
    private const TABLE = 'campaign_bookings';
    public const MAX_FAMILY_ATTEMPTS = 10;
    public const TERMINAL_PROOF_TTL = 30;

    /** The optional ports are for in-process, non-charging tests. */
    public function __construct(
        private DatabaseService $store = new DatabaseService(),
        private ?\Closure $gateway = null,
        private ?\Closure $secrets = null,
        private ?\Closure $operation = null,
    ) {}

    public static function fromEditor(mixed $enabled, mixed $amount): ?array
    {
        if ($enabled === null || $enabled === '') return null;
        if ($enabled !== '1' || !is_string($amount)
            || !preg_match('/^([0-9]{1,13})(?:\.([0-9]{1,2}))?$/D', trim($amount), $parts)) {
            throw new \InvalidArgumentException('Enter the booking amount in rupees with up to two decimal places.');
        }
        $paise = (int)$parts[1] * 100 + (int)str_pad($parts[2] ?? '', 2, '0');
        return self::definition(['amount_paise'=>$paise]);
    }

    public static function definition(mixed $config): array
    {
        $amount = is_array($config) ? ($config['amount_paise'] ?? null) : null;
        if (!is_array($config) || count($config) !== 1 || !is_int($amount)
            || $amount < RemoteCheckoutService::MIN_AMOUNT_PAISE || $amount > RemoteCheckoutService::MAX_AMOUNT_PAISE) {
            throw new \InvalidArgumentException('Enter a booking amount of at least ₹1 within the payment limit.');
        }
        return ['amount_paise'=>$amount];
    }

    public static function pageAmount(array $page): ?int
    {
        if (!array_key_exists('campaign_payment', $page)) return null;
        try {
            return self::definition($page['campaign_payment'])['amount_paise'];
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public static function rupees(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string)($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function validId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{32}$/D', $id) === 1;
    }

    /** Stable within a browser and actor, including back-button and repeated POSTs. */
    public static function browserOwner(array $session): ?string
    {
        $key = $session['campaign_payment_key'] ?? null;
        if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) return null;
        $actor = $session['user']['sub'] ?? null;
        return hash_hmac('sha256', is_string($actor) && $actor !== '' ? 'user:'.$actor : 'guest', $key);
    }

    public static function replacementId(string $rootId, string $previousId, string $owner): string
    {
        return substr(hash_hmac('sha256', 'booking-replacement:'.$rootId.':'.$previousId, $owner), 0, 32);
    }

    /** A legacy booking is its own root. The root holds only bounded successor IDs. */
    public static function familyRows(array $root, callable $read): array
    {
        $ids = $root['replacement_ids'] ?? [];
        if (isset($root['family_id']) || !self::validId((string)($root['id'] ?? ''))
            || !is_array($ids) || !array_is_list($ids) || count($ids) >= self::MAX_FAMILY_ATTEMPTS
            || count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
            throw new \RuntimeException('Invalid booking family.');
        }
        $rows = [$root];
        $previousId = $root['id'];
        foreach ($ids as $id) {
            if (!is_string($id) || !self::validId($id) || $id === $root['id']) {
                throw new \RuntimeException('Invalid booking successor.');
            }
            $row = $read($id);
            if (!is_array($row) || ($row['id'] ?? null) !== $id || ($row['family_id'] ?? null) !== $root['id']
                || ($row['previous_id'] ?? null) !== $previousId
                || $id !== self::replacementId($root['id'], $previousId, $root['owner_hash'])
                || ($row['owner_hash'] ?? null) !== $root['owner_hash']
                || ($row['slug'] ?? null) !== $root['slug'] || ($row['revision'] ?? null) !== $root['revision']
                || ($row['amount_paise'] ?? null) !== $root['amount_paise'] || ($row['mode'] ?? null) !== $root['mode']
                || ($row['currency'] ?? null) !== $root['currency'] || ($row['title'] ?? null) !== $root['title']
                || ($row['fulfillment'] ?? null) != $root['fulfillment']
                || ($row['reference_id'] ?? null) !== 'book_'.$id) {
                throw new \RuntimeException('Booking family evidence is incomplete.');
            }
            $rows[] = $row;
            $previousId = $id;
        }
        return $rows;
    }

    public static function familyState(array $rows, int $now): array
    {
        $current = $rows[array_key_last($rows)];
        $captured = array_values(array_filter($rows, static fn(array $r): bool => $r['status'] === 'captured'));
        $blockedIds = [];
        foreach ($rows as $row) {
            if (!empty($row['paid_notification_pending'])
                || ($row['id'] !== $current['id'] && $row['status'] !== 'captured'
                    && !self::terminalClear($row))) {
                $blockedIds[] = $row['id'];
            }
        }
        return [
            'current'=>$current, 'captured'=>$captured, 'blocked'=>$blockedIds !== [],
            'ids'=>array_column($rows,'id'), 'blocked_ids'=>$blockedIds,
            'limit_reached'=>count($rows) >= self::MAX_FAMILY_ATTEMPTS,
            'can_replace'=>$blockedIds === [] && $captured === [] && count($rows) < self::MAX_FAMILY_ATTEMPTS
                && self::terminalClear($current) && self::proofCurrent($current, $now),
        ];
    }

    private static function terminalClear(array $booking): bool
    {
        return in_array($booking['status'] ?? null, ['expired','cancelled'], true)
            && ($booking['terminal_clear'] ?? false) === true
            && empty($booking['paid_notification_pending']) && !isset($booking['receipt'])
            && is_string($booking['provider_link_id'] ?? null);
    }

    private static function proofCurrent(array $booking, int $now): bool
    {
        $checked = $booking['terminal_checked_at'] ?? null;
        return is_int($checked) && $checked <= $now && $checked >= $now-self::TERMINAL_PROOF_TTL;
    }

    /** Only called with the verified publication inside the booking transaction. */
    public static function snapshot(array $page, string $id, string $owner, string $mode, int $amount, int $now): array
    {
        $downloads = CampaignDownloadService::manifest($page);
        return self::claimCreation([
            'id'=>$id, 'slug'=>$page['slug'], 'revision'=>$page['revision'], 'title'=>$page['title'],
            'owner_hash'=>$owner, 'amount_paise'=>$amount, 'currency'=>'INR',
            'fulfillment'=>($downloads['enabled'] ?? false) ? $downloads['files'] : [],
            'reference_id'=>'book_'.$id, 'mode'=>$mode, 'created_at'=>gmdate('Y-m-d H:i:s', $now),
        ], $now);
    }

    /** Pure reservation decision, executed under the page and booking-family locks. */
    public static function reserve(array $page, string $id, string $slug, string $revision, string $owner,
        ?string $mode, ?string $freshFrom, ?array $root, callable $read, int $now): array
    {
        $current = null;
        if ($root !== null) {
            if (($root['id'] ?? null) !== $id || !hash_equals($root['owner_hash'], $owner)
                || $root['slug'] !== $slug || $root['revision'] !== $revision) {
                throw new \DomainException('Invalid booking identity.');
            }
            $family = self::familyState(self::familyRows($root, $read), $now);
            $current = $family['current'];
            if ($freshFrom !== null && !in_array($freshFrom, $family['ids'], true)) return ['outcome'=>'stale'];
            if ($family['captured'] !== []) return ['outcome'=>'existing', 'booking'=>$family['captured'][0]];
            if ($freshFrom !== null) {
                if ($freshFrom !== $current['id'] || !$family['can_replace']) {
                    return ['outcome'=>'existing', 'booking'=>$current];
                }
            } elseif ($current['status'] !== 'retryable' || isset($current['provider_link_id']) || $family['blocked']) {
                return ['outcome'=>'existing', 'booking'=>$current];
            }
        } elseif ($freshFrom !== null) {
            return ['outcome'=>'stale'];
        }
        $amount = self::pageAmount($page);
        $published = ($page['status'] ?? null) === 'published' && ($page['slug'] ?? null) === $slug
            && ($page['revision'] ?? null) === $revision && $amount !== null;
        if (!$published || $mode === null || ($root !== null && $root['mode'] !== $mode)) {
            return ['outcome'=>$published ? 'unavailable' : 'stale'];
        }
        if ($current !== null && $freshFrom === null) {
            return ['outcome'=>'retry', 'booking'=>self::claimCreation($current, $now)];
        }
        if ($root === null) {
            return ['outcome'=>'created', 'booking'=>self::snapshot($page, $id, $owner, $mode, $amount, $now)];
        }
        // A distinct link has its own immutable receipt and provider reference.
        // The published revision, price, mode, title and file versions must still agree.
        $nextId = self::replacementId($id, $current['id'], $owner);
        $booking = self::snapshot($page, $nextId, $owner, $mode, $amount, $now);
        if ($booking['amount_paise'] !== $root['amount_paise'] || $booking['title'] !== $root['title']
            || $booking['fulfillment'] != $root['fulfillment']) return ['outcome'=>'stale'];
        $booking['family_id'] = $id;
        $booking['previous_id'] = $current['id'];
        $root['replacement_ids'][] = $nextId;
        $root['updated_at'] = gmdate('Y-m-d H:i:s', $now);
        return ['outcome'=>'created', 'booking'=>$booking, 'root_update'=>$root];
    }

    /** Called under the family lock; an in-flight check is not clean terminal evidence. */
    public static function claimCheck(array $booking, int $now): array
    {
        $claimed = $booking['status'] !== 'captured'
            && ($booking['status'] !== 'retryable' || !empty($booking['paid_notification_pending']))
            && $booking['reconcile_after'] <= $now;
        if ($claimed) {
            $booking['reconcile_after'] = $now+30;
            $booking['reconcile_generation'] = ($booking['reconcile_generation'] ?? 0)+1;
            if (in_array($booking['status'], ['expired','cancelled'], true)) {
                $booking['terminal_clear'] = false;
                unset($booking['terminal_checked_at']);
            }
        }
        return compact('booking','claimed');
    }

    /** Called under the booking lock for a new booking or a definitively rejected POST. */
    public static function claimCreation(array $booking, int $now): array
    {
        $booking['status'] = 'preparing';
        $booking['creation_attempt'] = ($booking['creation_attempt'] ?? 0)+1;
        $booking['payment_expires_at'] = $now+RemoteCheckoutService::PAYMENT_TTL;
        $booking['reconcile_after'] = $now+30;
        $booking['updated_at'] = gmdate('Y-m-d H:i:s', $now);
        return $booking;
    }

    /** Apply the signature-independent, monotonic part of capture under the booking row lock. */
    public static function applyChange(array $booking, array $change, int $now): array
    {
        if ($booking['status'] === 'captured') return $booking;
        $status = $change['status'] ?? null;
        if ($status === 'paid_notice') {
            $booking['paid_notification_pending'] = true;
            $booking['terminal_clear'] = false;
            unset($booking['terminal_checked_at']);
            return $booking;
        }
        if (($change['creation_attempt'] ?? 0) !== ($booking['creation_attempt'] ?? 0)) return $booking;
        if ($status !== 'captured' && isset($change['reconcile_generation'])
            && $change['reconcile_generation'] !== ($booking['reconcile_generation'] ?? 0)) return $booking;
        if ($status === 'retryable') {
            if (!isset($booking['provider_link_id']) && in_array($booking['status'], ['preparing','uncertain'], true)) {
                $booking['status'] = $status;
            }
            return $booking;
        }
        if ($status === 'uncertain') {
            $booking['terminal_clear'] = false;
            unset($booking['terminal_checked_at']);
            if (!in_array($booking['status'], ['retryable','expired','cancelled'], true)) $booking['status'] = $status;
            return $booking;
        }
        $link = $change['provider_link_id'] ?? null;
        if (!in_array($status, ['pending', 'expired', 'cancelled', 'captured'], true)
            || !is_string($link) || !preg_match('/^plink_[A-Za-z0-9]{1,64}$/D', $link)
            || !RazorpayPaymentLinkService::validUrl($change['provider_url'] ?? null)
            || (isset($booking['provider_link_id']) && $booking['provider_link_id'] !== $link)) {
            throw new \InvalidArgumentException('Invalid booking provider state.');
        }
        if ($status === 'captured') {
            $paymentId = $change['receipt']['payment_id'] ?? null;
            $orderId = $change['receipt']['order_id'] ?? null;
            if (!is_string($paymentId) || !preg_match('/^pay_[A-Za-z0-9]{1,64}$/D', $paymentId)
                || !is_string($orderId) || !preg_match('/^order_[A-Za-z0-9]{1,64}$/D', $orderId)) {
                throw new \InvalidArgumentException('Invalid verified booking receipt.');
            }
            unset($booking['paid_notification_pending'], $booking['terminal_clear'], $booking['terminal_checked_at']);
            // One transition records payment and grants the frozen file versions together.
            $booking['receipt'] = ['id'=>'book_'.$booking['id'], 'payment_id'=>$paymentId,
                'order_id'=>$orderId, 'verified_at'=>gmdate('Y-m-d H:i:s', $now)];
        } elseif (in_array($booking['status'], ['expired', 'cancelled'], true) && $status === 'pending') {
            // A stale provider snapshot cannot reopen a closed payment link. It
            // also cannot preserve eligibility for another payment.
            $booking['terminal_clear'] = false;
            unset($booking['terminal_checked_at']);
            return $booking;
        }
        if (in_array($status, ['expired','cancelled'], true)) {
            $booking['terminal_clear'] = ($change['terminal_clear'] ?? false) === true
                && empty($booking['paid_notification_pending']);
            $booking['terminal_checked_at'] = $now;
        }
        $booking['status'] = $status;
        $booking['provider_link_id'] = $link;
        $booking['provider_url'] = $change['provider_url'];
        return $booking;
    }

    public function start(string $slug, string $revision, string $owner, ?callable $admit = null, ?string $freshFrom = null): array
    {
        $id = substr(hash_hmac('sha256', 'booking:'.$slug.':'.$revision, $owner), 0, 32);
        $root = $this->owned($id, $owner);
        if ($freshFrom !== null && ($root === null || !self::validId($freshFrom))) return ['outcome'=>'stale'];
        if ($root !== null) {
            $family = $this->familyFor($root);
            if ($freshFrom !== null && !in_array($freshFrom, $family['ids'], true)) return ['outcome'=>'stale'];
            if ($family['captured'] !== []) return $family['captured'][0];
            $current = $family['current'];
            // A stale confirm is a replay, never a request to start a third payment.
            if ($freshFrom !== null && $freshFrom !== $current['id']) return $current;
            if ($freshFrom === null && ($current['status'] !== 'retryable' || $family['blocked'])) return $current;
            if ($freshFrom !== null && (!in_array($current['status'], ['expired','cancelled'], true) || $family['blocked'])) return $current;
        }
        if ($admit !== null && !$admit()) return ['outcome'=>'limited'];
        if ($freshFrom !== null) {
            // Refresh the terminal proof if its bounded observation is no longer
            // available. An in-flight or failed lookup clears eligibility.
            $this->reconcile($current);
        }
        $mode = RazorpayPaymentLinkService::mode($this->secretValues());
        $result = $this->operate('begin', compact('id', 'slug', 'revision', 'owner', 'mode', 'freshFrom'));
        if (!in_array($result['outcome'], ['created','retry'], true)) return $result['booking'] ?? $result;

        $booking = $result['booking'];
        // A preceding signed payment may have settled after the database claim.
        // Check before any provider creation; no I/O has happened if suppressed.
        if (isset($booking['family_id'])) {
            $family = $this->familyFor($booking);
            if ($family['blocked'] || $family['captured'] !== []) {
                $booking = $this->saveChange($booking, ['status'=>'retryable']);
                return $family['captured'][0] ?? $booking;
            }
        }
        // The creation claim is written before this call. An uncertain POST is
        // recovered by reference and must never trigger another link creation.
        try {
            $link = $this->provider('create_link', $booking, [
                'amount'=>$booking['amount_paise'], 'currency'=>'INR', 'accept_partial'=>false,
                'reference_id'=>$booking['reference_id'], 'expire_by'=>$booking['payment_expires_at'],
                'description'=>'Campaign booking', 'notify'=>['sms'=>false, 'email'=>false], 'reminder_enable'=>false,
            ]);
        } catch (PaymentLinkCreationRejected) {
            return $this->saveChange($booking, ['status'=>'retryable']);
        } catch (\Throwable) {
            return $this->saveChange($booking, ['status'=>'uncertain']);
        }
        try {
            $change = $this->linkChange($booking, $link);
        } catch (\Throwable) {
            $change = ['status'=>'uncertain'];
        }
        return $this->saveChange($booking, $change);
    }

    public function owned(string $id, ?string $owner): ?array
    {
        if ($owner === null || !self::validId($id)) return null;
        $booking = $this->operate('read', compact('id'));
        return $booking !== null && hash_equals((string)($booking['owner_hash'] ?? ''), $owner) ? $booking : null;
    }

    /** Read a coherent bounded family after browser ownership has been checked. */
    public function familyFor(array $booking): array
    {
        $family = $this->operate('family', ['id'=>$booking['id'], 'owner'=>$booking['owner_hash']]);
        if ($family === null) throw new \RuntimeException('Booking family unavailable.');
        return $family;
    }

    public function reconcile(array $booking): array
    {
        if ($booking['status'] === 'captured'
            || ($booking['status'] === 'retryable' && empty($booking['paid_notification_pending']))) return $booking;
        $claim = $this->operate('claim', ['id'=>$booking['id']]);
        $booking = $claim['booking'];
        if (!$claim['claimed']) return $booking;
        try {
            $link = RazorpayPaymentLinkService::find($booking,
                fn(string $operation, array $args = []) => $this->provider($operation, $booking, $args));
            $change = $link === null ? ['status'=>'uncertain'] : $this->linkChange($booking, $link);
        } catch (\Throwable) {
            $change = ['status'=>'uncertain'];
        }
        return $this->saveChange($booking, $change + ['reconcile_generation'=>$booking['reconcile_generation'] ?? 0]);
    }

    private function saveChange(array $booking, array $change): array
    {
        $change['creation_attempt'] = $booking['creation_attempt'] ?? 0;
        $change['reconcile_generation'] ??= $booking['reconcile_generation'] ?? 0;
        return $this->operate('change', ['id'=>$booking['id'], 'change'=>$change]);
    }

    /** Called only after the shared endpoint verifies the notification HMAC for the mode. */
    public function receivedNotification(string $id, array $signedModes, bool $paid): void
    {
        $booking = $this->operate('read', compact('id'));
        if ($booking === null) return;
        if (!in_array($booking['mode'], $signedModes, true)) {
            throw new RemoteCartException('invalid_signature', 'Invalid payment notification.', 401);
        }
        if ($paid && $booking['status'] !== 'captured') {
            $booking = $this->saveChange($booking, ['status'=>'paid_notice']);
        }
        $booking = $this->reconcile($booking);
        if (in_array($booking['status'], ['preparing', 'uncertain'], true) || ($paid && $booking['status'] !== 'captured')) {
            throw new RemoteCartException('notification_pending', 'Payment verification will be retried.', 503, 30);
        }
    }

    public function paidFile(array $booking, string $id): ?array
    {
        if ($booking['status'] !== 'captured' || !is_array($booking['receipt'] ?? null) || !self::validId($id)) return null;
        foreach ($booking['fulfillment'] as $file) {
            if ($file['id'] === $id) return $file;
        }
        return null;
    }

    private function linkChange(array $booking, array $link): array
    {
        $providerStatus = RazorpayPaymentLinkService::validate($booking, $link);
        if ($providerStatus === 'partially_paid') throw new \RuntimeException('Unexpected partial booking payment.');
        $change = ['provider_link_id'=>$link['id'], 'provider_url'=>$link['short_url']];
        if ($providerStatus === 'paid') {
            $change['status'] = 'captured';
            $change['receipt'] = RazorpayPaymentLinkService::captured($booking, $link,
                fn(string $operation, array $args = []) => $this->provider($operation, $booking, $args));
        } else {
            $change['status'] = $providerStatus === 'created' ? 'pending' : $providerStatus;
            if (in_array($providerStatus, ['expired','cancelled'], true)) {
                $change['terminal_clear'] = $this->terminalPaymentClear($booking, $link);
            }
        }
        return $change;
    }

    /** The link's payments list contains captures only; inspect the linked order
     * as well for authorization or other unresolved payment evidence. */
    private function terminalPaymentClear(array $booking, array $link): bool
    {
        if (($link['amount_paid'] ?? null) !== 0 || !array_key_exists('payments', $link)
            || !in_array($link['payments'], [null, []], true) || !array_key_exists('order_id', $link)) return false;
        if ($link['order_id'] === null) return true;
        $order = $link['order_id'];
        if (!is_string($order) || !preg_match('/^order_[A-Za-z0-9]{1,64}$/D', $order)) return false;
        try {
            $result = $this->provider('order_payments', $booking, ['order_id'=>$order]);
        } catch (\Throwable) { return false; }
        $items = $result['items'] ?? null;
        // Razorpay documents that a failed payment may later become authorized.
        // A new link would have a different provider order, so even a failed
        // payment record is not sufficient proof that a fresh charge is safe.
        return is_array($items) && $items === [] && ($result['count'] ?? null) === 0;
    }

    private function operate(string $operation, array $args): ?array
    {
        if ($this->operation !== null) return ($this->operation)($operation, $args);
        return match ($operation) {
            'begin' => $this->store->beginCampaignBooking(...$args),
            'read' => $this->store->find(self::TABLE, $args['id']),
            'family' => $this->store->readCampaignBookingFamily($args['id'], $args['owner']),
            'claim' => $this->store->claimCampaignBookingCheck($args['id']),
            'change' => $this->store->updateCampaignBooking($args['id'], $args['change']),
        };
    }

    private function secretValues(): array
    {
        return $this->secrets !== null ? ($this->secrets)() : (new SecretService($this->store))->all();
    }

    private function provider(string $operation, array $booking, array $args): array
    {
        if ($this->gateway !== null) return ($this->gateway)($operation, $booking, $args);
        $secrets = $this->secretValues();
        DatabaseService::releaseIdleConnection();
        return RazorpayPaymentLinkService::request($secrets, $booking, $operation, $args);
    }
}
