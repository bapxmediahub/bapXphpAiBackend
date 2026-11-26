<?php
namespace App\Services;

/** Native ordered zone matching, independently testable without checkout or payment. */
final class ShippingService {
    public const MAX_ZONES = 5;
    private const FIELDS = ['state','pins','method','fee','minimum'];

    /** Canonical bounded owner draft; no field activates shipping or changes prices. */
    public static function normaliseZones(array $zones): array {
        if (!array_is_list($zones) || count($zones) > self::MAX_ZONES) {
            throw new \InvalidArgumentException('Enter up to five ordered shipping zones.');
        }
        $normal = [];
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
            if (!in_array($zone['method'], ['flat','free'], true)) throw new \InvalidArgumentException('Choose flat or free shipping.');
            if ($zone['state'] === '' && $zone['pins'] === '' && $zone['fee'] === '' && $zone['minimum'] === '') {
                $normal[] = ['state'=>'','pins'=>'','method'=>'flat','fee'=>'','minimum'=>''];
                continue;
            }
            $region = TaxService::normaliseState($zone['state']) ?? throw new \InvalidArgumentException('Choose a valid state for zone '.($i+1).'.');
            $pins = $zone['pins'] === '' ? [] : array_map('trim', explode(',', $zone['pins']));
            if (count($pins) > 30) throw new \InvalidArgumentException('Use at most 30 PIN patterns per zone.');
            foreach ($pins as $pattern) {
                if (!preg_match('/^(?:[1-9][0-9]{5}|[1-9][0-9]{1,4}\*)$/D', $pattern)) {
                    throw new \InvalidArgumentException('Zone '.($i+1).' needs six-digit PINs or 2–5 digit prefixes ending in *.');
                }
            }
            $fee = $minimum = '';
            if ($zone['method'] === 'flat') {
                $paise = self::paise($zone['fee']);
                if ($paise === 0 || $zone['minimum'] !== '') throw new \InvalidArgumentException('Flat shipping needs a positive fee and no minimum.');
                $fee = self::decimal($paise);
            } else {
                if ($zone['fee'] !== '') throw new \InvalidArgumentException('Free shipping must not include a fee.');
                $minimum = $zone['minimum'] === '' ? '' : self::decimal(self::paise($zone['minimum']));
            }
            $normal[] = ['state'=>$region,'pins'=>implode(', ',array_values(array_unique($pins))),
                'method'=>$zone['method'],'fee'=>$fee,'minimum'=>$minimum];
        }
        while ($normal && end($normal)['state'] === '') array_pop($normal);
        return $normal;
    }

    public static function validRevision(mixed $revision): bool {
        return is_string($revision) && ($revision === '0' || preg_match('/^[a-f0-9]{32}$/D',$revision) === 1);
    }

    public static function storedDraft(mixed $value): array {
        if ($value === null) return ['revision'=>'0','zones'=>[]];
        if (!is_array($value) || count($value)!==2 || !self::validRevision($value['revision']??null)
            || $value['revision']==='0' || !is_array($value['zones']??null)) {
            throw new \RuntimeException('Stored shipping draft is unavailable for safe editing.');
        }
        try { $zones=self::normaliseZones($value['zones']); }
        catch (\InvalidArgumentException $error) { throw new \RuntimeException('Stored shipping draft is unavailable for safe editing.',0,$error); }
        return ['revision'=>$value['revision'],'zones'=>$zones];
    }

    /** A hypothetical delivery quote: saved or unsaved draft rules never activate charges. */
    public static function preview(array $zones, string $state, string $pin, string $subtotal): array {
        $state = TaxService::normaliseState($state) ?? throw new \InvalidArgumentException('Choose a valid delivery state.');
        if (!preg_match('/^[1-9][0-9]{5}$/D', $pin)) throw new \InvalidArgumentException('Enter a six-digit Indian delivery PIN.');
        $subtotalPaise = self::paise($subtotal);
        foreach (self::normaliseZones($zones) as $i=>$rule) {
            if ($rule['state'] !== $state) continue;
            $pins = $rule['pins'] === '' ? [] : explode(', ', $rule['pins']);
            $covered = $pins === [];
            foreach ($pins as $pattern) {
                if (str_ends_with($pattern,'*') ? str_starts_with($pin,substr($pattern,0,-1)) : $pin === $pattern) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) continue;
            $minimum = $rule['minimum'] === '' ? 0 : self::paise($rule['minimum']);
            if ($subtotalPaise < $minimum) return ['status'=>'minimum_not_met','zone'=>$i+1,'feePaise'=>null];
            return ['status'=>'quoted','zone'=>$i+1,'feePaise'=>$rule['fee']==='' ? 0 : self::paise($rule['fee'])];
        }
        return ['status'=>'not_covered','zone'=>null,'feePaise'=>null];
    }

    private static function decimal(int $paise): string {
        return intdiv($paise,100).'.'.str_pad((string)($paise%100),2,'0',STR_PAD_LEFT);
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
