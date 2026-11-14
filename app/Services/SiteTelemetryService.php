<?php
namespace App\Services;

/**
 * First-party commerce event counters: no IPs, emails, cookies, prompts or addresses.
 * Telemetry failure must never interrupt cart/checkout/payment operations.
 */
final class SiteTelemetryService {
    private const EVENTS = ['cart_add','checkout_initiated'];
    public function record(string $event, array $attributes = []): void {
        if (!in_array($event,self::EVENTS,true)) return;
        $safe = ['event'=>$event];
        if ($event === 'cart_add' && isset($attributes['product'])) {
            $safe['product'] = substr((string)preg_replace('/[^a-z0-9-]/i','',(string)$attributes['product']),0,80);
        }
        try {
            (new AuditLogService())->record('event','commerce','',$safe,
                ['email'=>'system','name'=>'Commerce telemetry']);
        } catch (\Throwable $error) {
            error_log('Commerce telemetry failed: '.$error::class);
        }
    }
    /** Pure aggregate over sanitized persisted events. */
    public static function counts(array $events, ?int $since = null): array {
        $since ??= time() - 86400 * 30;
        $counts = array_fill_keys(self::EVENTS,0);
        foreach ($events as $row) {
            if (($row['event']??'') !== 'commerce.event') continue;
            $event = $row['meta']['event']??'';
            if (!isset($counts[$event])) continue;
            $ts = strtotime((string)($row['created_at']??''));
            if ($ts === false || $ts < $since) continue;
            $counts[$event]++;
        }
        return $counts;
    }
}
