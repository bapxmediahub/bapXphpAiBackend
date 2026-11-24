<?php
namespace App\Services;

/** Native ordered zone matching, independently testable without checkout or payment. */
final class ShippingService {
    public const MAX_ZONES = 5;
    private const FIELDS = ['state','pins','method','fee','minimum'];

    /** A hypothetical delivery quote: no persisted rules, no payment or GST mutation. */
    public static function preview(array $zones, string $state, string $pin, string $subtotal): array {
        if (!array_is_list($zones) || count($zones) > self::MAX_ZONES) {
            throw new \InvalidArgumentException('Enter up to five ordered shipping zones.');
        }
        $state = TaxService::normaliseState($state) ?? throw new \InvalidArgumentException('Choose a valid delivery state.');
        if (!preg_match('/^[1-9][0-9]{5}$/D', $pin)) {
            throw new \InvalidArgumentException('Enter a six-digit Indian delivery PIN.');
        }
        $subtotalPaise = self::paise($subtotal);
        $rules = [];
        foreach ($zones as $i=>$zone) {
            if (!is_array($zone) || array_diff(array_keys($zone), self::FIELDS)) {
                throw new \InvalidArgumentException('Invalid shipping zone.');
            }
            foreach (self::FIELDS as $field) {
                if (!is_string($zone[$field] ?? null) || strlen($zone[$field]) > ($field === 'pins' ? 500 : 100)) {
                    throw new \InvalidArgumentException('Invalid zone field.');
                }
                $zone[$field] = trim($zone[$field]);
            }
            if ($zone['state'] === '' && $zone['pins'] === '' && $zone['fee'] === '' && $zone['minimum'] === '') continue;
            $region = TaxService::normaliseState($zone['state']) ?? throw new \InvalidArgumentException('Choose a valid state for zone '.($i+1).'.');
            $pins = $zone['pins'] === '' ? [] : array_map('trim', explode(',', $zone['pins']));
            if (count($pins) > 30) throw new \InvalidArgumentException('Use at most 30 PIN patterns per zone.');
            foreach ($pins as $pattern) {
                if (!preg_match('/^(?:[1-9][0-9]{5}|[1-9][0-9]{1,4}\*)$/D', $pattern)) {
                    throw new \InvalidArgumentException('Zone '.($i+1).' needs six-digit PINs or 2–5 digit prefixes ending in *.');
                }
            }
            if (!in_array($zone['method'], ['flat','free'], true)) throw new \InvalidArgumentException('Choose flat or free shipping.');
            if ($zone['method'] === 'flat') {
                $fee = self::paise($zone['fee']);
                if ($fee === 0 || $zone['minimum'] !== '') throw new \InvalidArgumentException('Flat shipping needs a positive fee and no minimum.');
                $minimum = 0;
            } else {
                if ($zone['fee'] !== '') throw new \InvalidArgumentException('Free shipping must not include a fee.');
                $fee = 0;
                $minimum = $zone['minimum'] === '' ? 0 : self::paise($zone['minimum']);
            }
            $rules[] = ['zone'=>$i+1,'state'=>$region,'pins'=>$pins,'fee'=>$fee,'minimum'=>$minimum];
        }
        foreach ($rules as $rule) {
            if ($rule['state'] !== $state) continue;
            $covered = $rule['pins'] === [];
            foreach ($rule['pins'] as $pattern) {
                if (str_ends_with($pattern,'*') ? str_starts_with($pin,substr($pattern,0,-1)) : $pin === $pattern) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) continue;
            if ($subtotalPaise < $rule['minimum']) return ['status'=>'minimum_not_met','zone'=>$rule['zone'],'feePaise'=>null];
            return ['status'=>'quoted','zone'=>$rule['zone'],'feePaise'=>$rule['fee']];
        }
        return ['status'=>'not_covered','zone'=>null,'feePaise'=>null];
    }

    /** Validate decimal rupees and convert without floating-point rounding. */
    private static function paise(string $value): int {
        if (!preg_match('/^(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new \InvalidArgumentException('Enter a non-negative INR amount with at most two decimal places.');
        }
        $parts = explode('.', $value, 2);
        return (int)$parts[0]*100 + (int)str_pad($parts[1] ?? '', 2, '0');
    }
}
