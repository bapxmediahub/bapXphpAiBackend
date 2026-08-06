<?php
namespace App\Services;

/**
 * The one place a coupon is judged.
 *
 * Checkout used to inline the whole rule, and the rule was two lines: does the code
 * match, and is it active. Nothing else was checked because nothing else was stored, so
 * a promo code posted once stayed redeemable by anyone, any number of times, on a cart
 * of any size, forever. The only way to stop it was for the owner to remember to untick
 * a box.
 *
 * The percentage maths was wrong in the other direction:
 *
 *     $discount = min($cartTotal * $value / 100, $value);
 *
 * For a 25% coupon that is min(500, 25) on a ₹2000 cart — the customer was given ₹25.
 * The cap was reaching for "a percentage discount, up to a maximum amount", but it
 * compared rupees against a percentage, so the percentage always won.
 *
 * Refusals carry a reason the shopper can act on. "This coupon expired on 4 Aug" and
 * "add ₹200 more to use it" are different problems, and one "invalid coupon" told them
 * neither.
 */
final class CouponService
{
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array
    {
        return $this->store->read('coupons');
    }

    /**
     * @return array{discount: float, free_shipping: bool, coupon: array}
     * @throws \InvalidArgumentException with a message written for the shopper.
     */
    public function apply(string $code, float $cartTotal, string $customerEmail = '', ?\DateTimeImmutable $now = null): array
    {
        $code = trim($code);
        if ($code === '') throw new \InvalidArgumentException('Enter a coupon code.');
        $now ??= new \DateTimeImmutable();

        $coupon = $this->find($code);
        if ($coupon === null) throw new \InvalidArgumentException('That coupon code was not recognised.');

        $this->assertUsable(
            $coupon,
            $cartTotal,
            $this->timesUsed($code),
            $customerEmail !== '' ? $this->timesUsed($code, $customerEmail) : 0,
            $now
        );

        return [
            'discount' => $this->discountFor($coupon, $cartTotal),
            'free_shipping' => !empty($coupon['free_shipping']),
            'coupon' => $coupon,
        ];
    }

    /**
     * Every rule, with the counting already done.
     *
     * Separated from apply() so the rules can be tested exactly — DatabaseService is
     * final, so a fake store is not an option, and a rule that can only be exercised
     * through a live database is a rule that does not get tested.
     *
     * @throws \InvalidArgumentException with a message written for the shopper.
     */
    public function assertUsable(
        array $coupon,
        float $cartTotal,
        int $timesUsedAll = 0,
        int $timesUsedByCustomer = 0,
        ?\DateTimeImmutable $now = null
    ): void {
        $now ??= new \DateTimeImmutable();
        if (!$this->isActive($coupon)) throw new \InvalidArgumentException('That coupon is no longer available.');
        $this->assertWithinDates($coupon, $now);
        $this->assertWithinSpend($coupon, $cartTotal);
        $this->assertWithinLimits($coupon, $timesUsedAll, $timesUsedByCustomer);
    }

    /**
     * The discount in rupees, never more than the cart itself.
     *
     * A percentage coupon may carry max_discount as a rupee ceiling — "20% off, up to
     * ₹500". That is what the old cap was reaching for and getting wrong.
     */
    public function discountFor(array $coupon, float $cartTotal): float
    {
        $value = (float)($coupon['discount_value'] ?? 0);
        $type = strtolower((string)($coupon['discount_type'] ?? 'fixed'));

        if ($type === 'percentage' || $type === 'percent') {
            $discount = $cartTotal * $value / 100;
            $ceiling = (float)($coupon['max_discount'] ?? 0);
            if ($ceiling > 0) $discount = min($discount, $ceiling);
        } else {
            $discount = $value;
        }

        return round(max(0.0, min($discount, $cartTotal)), 2);
    }

    /**
     * How many orders already used this code.
     *
     * Counted from orders rather than a counter on the coupon, because the orders are
     * the record of what actually happened — a counter drifts the first time a write
     * half-fails. A cancelled or refunded order gives its use back.
     */
    public function timesUsed(string $code, string $customerEmail = ''): int
    {
        $code = strtolower(trim($code));
        if ($code === '') return 0;
        $email = strtolower(trim($customerEmail));
        $used = 0;
        foreach ($this->store->read('orders') as $order) {
            if (strtolower((string)($order['coupon_code'] ?? '')) !== $code) continue;
            if (in_array((string)($order['status'] ?? ''), ['cancelled', 'refunded'], true)) continue;
            if ($email !== '' && strtolower((string)($order['customer_email'] ?? '')) !== $email) continue;
            $used++;
        }
        return $used;
    }

    public function find(string $code): ?array
    {
        $code = strtolower(trim($code));
        foreach ($this->all() as $coupon) {
            if (strtolower((string)($coupon['code'] ?? '')) === $code) return $coupon;
        }
        return null;
    }

    /** Both shapes are in production data: a boolean `active` and a `status` enum. */
    private function isActive(array $coupon): bool
    {
        if (array_key_exists('active', $coupon)) {
            $active = $coupon['active'];
            if (is_string($active)) return $active !== '' && $active !== '0' && strtolower($active) !== 'false';
            return (bool)$active;
        }
        return (string)($coupon['status'] ?? '') === 'active';
    }

    private function assertWithinDates(array $coupon, \DateTimeImmutable $now): void
    {
        $starts = $this->date($coupon['starts_at'] ?? '');
        if ($starts !== null && $now < $starts) {
            throw new \InvalidArgumentException('That coupon is not active yet. It starts on ' . $starts->format('j M Y') . '.');
        }
        $ends = $this->date($coupon['ends_at'] ?? '');
        if ($ends !== null) {
            // A date with no time means the whole of that day still counts. Without this
            // a coupon ending "6 Aug" would die at midnight as the 6th began.
            if (!str_contains((string)$coupon['ends_at'], ':')) $ends = $ends->setTime(23, 59, 59);
            if ($now > $ends) throw new \InvalidArgumentException('That coupon expired on ' . $ends->format('j M Y') . '.');
        }
    }

    private function assertWithinSpend(array $coupon, float $cartTotal): void
    {
        $min = (float)($coupon['min_spend'] ?? 0);
        if ($min > 0 && $cartTotal < $min) {
            throw new \InvalidArgumentException(
                'This coupon needs a cart of ₹' . number_format($min, 2)
                . '. Add ₹' . number_format($min - $cartTotal, 2) . ' more to use it.'
            );
        }
        $max = (float)($coupon['max_spend'] ?? 0);
        if ($max > 0 && $cartTotal > $max) {
            throw new \InvalidArgumentException('This coupon only applies to carts up to ₹' . number_format($max, 2) . '.');
        }
    }

    private function assertWithinLimits(array $coupon, int $timesUsedAll, int $timesUsedByCustomer): void
    {
        $limit = (int)($coupon['usage_limit'] ?? 0);
        if ($limit > 0 && $timesUsedAll >= $limit) {
            throw new \InvalidArgumentException('That coupon has been fully redeemed.');
        }
        $perCustomer = (int)($coupon['usage_limit_per_customer'] ?? 0);
        if ($perCustomer > 0 && $timesUsedByCustomer >= $perCustomer) {
            throw new \InvalidArgumentException(
                $perCustomer === 1
                    ? 'You have already used that coupon.'
                    : 'You have already used that coupon ' . $perCustomer . ' times.'
            );
        }
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        try { return new \DateTimeImmutable($value); }
        catch (\Throwable) { return null; }
    }
}
