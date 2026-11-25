<?php
declare(strict_types=1);

namespace App\Services;

use App\Integrations\Razorpay\PaymentLinkCreationRejected;

/** A booking snapshots a published page and has its own receipt, independent of shop checkout. */
final class CampaignPaymentService
{
    private const TABLE = 'campaign_bookings';

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
        if ($booking['status'] === 'captured' || ($change['creation_attempt'] ?? 0) !== ($booking['creation_attempt'] ?? 0)) {
            return $booking;
        }
        $status = $change['status'] ?? null;
        if ($status === 'retryable') {
            if (!isset($booking['provider_link_id']) && in_array($booking['status'], ['preparing','uncertain'], true)) {
                $booking['status'] = $status;
            }
            return $booking;
        }
        if ($status === 'uncertain') {
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
            // One transition records payment and grants the frozen file versions together.
            $booking['receipt'] = ['id'=>'book_'.$booking['id'], 'payment_id'=>$paymentId,
                'order_id'=>$orderId, 'verified_at'=>gmdate('Y-m-d H:i:s', $now)];
        } elseif (in_array($booking['status'], ['expired', 'cancelled'], true)) {
            // A stale provider snapshot cannot reopen a closed payment link. Late capture can still win.
            return $booking;
        }
        $booking['status'] = $status;
        $booking['provider_link_id'] = $link;
        $booking['provider_url'] = $change['provider_url'];
        return $booking;
    }

    public function start(string $slug, string $revision, string $owner, ?callable $admit = null): array
    {
        $id = substr(hash_hmac('sha256', 'booking:'.$slug.':'.$revision, $owner), 0, 32);
        $current = $this->owned($id, $owner);
        if ($current !== null && $current['status'] !== 'retryable') return $current;
        if ($admit !== null && !$admit()) return ['outcome'=>'limited'];

        $mode = RazorpayPaymentLinkService::mode($this->secretValues());
        $result = $this->operate('begin', compact('id', 'slug', 'revision', 'owner', 'mode'));
        if (!in_array($result['outcome'], ['created','retry'], true)) return $result['booking'] ?? $result;

        $booking = $result['booking'];
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

    public function reconcile(array $booking): array
    {
        if (in_array($booking['status'], ['captured','retryable'], true)) return $booking;
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
        return $this->saveChange($booking, $change);
    }

    private function saveChange(array $booking, array $change): array
    {
        $change['creation_attempt'] = $booking['creation_attempt'] ?? 0;
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
        }
        return $change;
    }

    private function operate(string $operation, array $args): ?array
    {
        if ($this->operation !== null) return ($this->operation)($operation, $args);
        return match ($operation) {
            'begin' => $this->store->beginCampaignBooking(...$args),
            'read' => $this->store->find(self::TABLE, $args['id']),
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
