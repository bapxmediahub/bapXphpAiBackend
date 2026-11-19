<?php
namespace App\Services;

final class AuditLogService {
    private const RUN_REASONS = ['not_configured', 'timeout', 'transport', 'authentication', 'rate_limit',
        'provider_server', 'provider_request', 'provider_response', 'empty_response', 'rejected_response',
        'internal_error', 'request_deadline', 'provider_budget', 'trace_step_budget'];

    public const DIAGNOSTICS = [
        'transfer_timeout' => ['Transfer timed out', 'Check the recorded HTTP status and first-byte timing. A timeout alone cannot identify where the delay occurred.'],
        'transport_error' => ['Transport error', 'Use the cURL error number to check DNS, connectivity or TLS on the application host.'],
        'upstream_http' => ['Upstream service error', 'Check the provider status page and the attempt time before making another request.'],
        'rate_limit' => ['Provider rate or quota limit', 'Check provider quota and rate limits before retrying.'],
        'access_denied' => ['Provider denied access', 'Check the configured credentials and the account permissions for this model.'],
        'request_error' => ['Provider rejected the request', 'Check the endpoint, model and supported request fields.'],
        'other_http' => ['Unexpected HTTP status', 'Check the configured endpoint and the recorded status.'],
        'no_http' => ['No HTTP status recorded', 'Check the host connection and the trace for a transport error.'],
        'http_success' => ['HTTP success', ''],
        'request_deadline' => ['Local request deadline reached', 'Inspect elapsed time across earlier steps before retrying.'],
        'provider_budget' => ['Local provider budget reached', 'Inspect model-call and observed token totals in the conversation trace.'],
        'trace_step_budget' => ['Local trace step limit reached', 'Inspect the last recorded step before retrying any action.'],
        'empty_response' => ['No usable answer text', 'Inspect the final HTTP attempt and check the model response format.'],
        'provider_response' => ['Unusable provider response', 'Check the configured model’s response and tool-call format.'],
        'rejected_response' => ['Answer rejected by application checks', 'Inspect the recorded checks and source evidence for this turn.'],
    ];

    /** Classify the observed transfer, independently of whether the answer was accepted. */
    public static function attemptDiagnosis(array $attempt): string {
        $status = (int)($attempt['status'] ?? 0);
        $error = (int)($attempt['curl_error'] ?? 0);
        return match (true) {
            $error === 28 => 'transfer_timeout',
            $error !== 0 => 'transport_error',
            $status === 0 => 'no_http',
            $status >= 500 && $status < 600 => 'upstream_http',
            $status === 429 => 'rate_limit',
            $status === 401 || $status === 403 => 'access_denied',
            $status >= 400 && $status < 500 => 'request_error',
            $status >= 200 && $status < 300 => 'http_success',
            default => 'other_http',
        };
    }

    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array {
        return array_reverse($this->store->read('audit_events'));
    }

    /** Operational metadata only: never retain prompts, replies, identities or secrets. */
    public function agentRun(string $surface, string $outcome, float $startedAt, ?string $reason = null, array $attempts = [], array $tools = [], ?string $traceId = null): void {
        if (!in_array($surface, ['support', 'admin'], true)) return;
        if (!in_array($outcome, ['model', 'local', 'fallback', 'private_account', 'draft', 'error'], true)) return;
        $trace = AgentTraceService::snapshot();
        if ($traceId !== null && ($trace['trace_id'] ?? null) === $traceId) {
            $reason = AgentTraceService::budgetReason($trace) ?? $reason;
        }
        $meta = [
            'surface' => $surface,
            'outcome' => $outcome,
            'reason' => in_array($reason, self::RUN_REASONS, true) ? $reason : null,
            'duration_ms' => max(0, (int)round((microtime(true) - $startedAt) * 1000)),
        ];
        if (($trace = self::validTraceId($traceId)) !== null) $meta['trace_id'] = $trace;
        if ($attempts !== []) $meta['provider_attempts'] = self::providerAttempts($attempts);
        if ($tools !== []) $meta['tool_activity'] = self::toolActivity($tools);
        $this->record('run', 'agent', $surface, $meta, ['email' => 'system', 'name' => 'Agent monitoring']);
    }

    private static function validTraceId(mixed $value): ?string {
        return is_string($value) && preg_match('/^[a-f0-9]{16}$/D', $value) ? $value : null;
    }

    private static function providerAttempts(array $attempts): array {
        $safe = [];
        foreach (array_slice($attempts, 0, 9) as $attempt) {
            if (!is_array($attempt)) continue;
            $model = $attempt['model'] ?? null;
            $entry = [
                'model' => is_string($model) && SecretService::validModelId($model) ? $model : null,
                'status' => (int)($attempt['status'] ?? 0),
                'curl_error' => (int)($attempt['curl_error'] ?? 0),
                'total_ms' => max(0, (int)round((float)($attempt['total_ms'] ?? 0))),
            ];
            $safe[] = $entry + AgentTraceService::attemptTimings($attempt);
        }
        return $safe;
    }

    private static function toolActivity(array $tools): array {
        $safe = [];
        // Discovery/execution owns capability names. This alias is historical
        // observed activity only; it grants no model capability or authorization.
        $names=array_merge(AgentCapabilityPolicy::tools(AgentCapabilityPolicy::SUPPORT),
            AgentCapabilityPolicy::tools(AgentCapabilityPolicy::ENTERPRISE),['customer_ticket_lookup']);
        foreach (array_slice($tools, 0, 32) as $tool) {
            if (!is_array($tool)
                || !in_array($tool['name'] ?? null, $names, true)
                || !in_array($tool['status'] ?? null, ['complete', 'error'], true)) continue;
            $entry = [
                'name' => $tool['name'],
                'status' => $tool['status'],
                'duration_ms' => max(0, (int)($tool['duration_ms'] ?? 0)),
            ];
            if (in_array($tool['reason'] ?? null, ['invalid_parameters', 'invalid_date_range', 'lookup_unavailable', 'lookup_rejected'], true)) {
                $entry['reason'] = $tool['reason'];
            }
            $safe[] = $entry;
        }
        return $safe;
    }

    public static function agentSummary(array $events, ?int $now = null): array {
        $now ??= time();
        $cutoff = $now - 86400;
        $durations = ['support' => [], 'admin' => []];
        $summary = [];
        foreach (['support', 'admin'] as $surface) {
            $summary[$surface] = ['requests' => 0, 'model' => 0, 'local' => 0, 'fallback' => 0, 'private_account' => 0, 'draft' => 0, 'error' => 0, 'duration_ms' => 0, 'last_seen' => '', 'reasons' => [], 'latest_provider_run' => null, 'recent_runs' => [], 'provider_attempt_count'=>0, 'provider_error_count'=>0, 'tool_call_count'=>0, 'tool_error_count'=>0];
        }
        foreach ($events as $event) {
            if (($event['event'] ?? '') !== 'agent.run') continue;
            $meta = $event['meta'] ?? [];
            if (!is_array($meta)) continue;
            $surface = $meta['surface'] ?? '';
            $outcome = $meta['outcome'] ?? '';
            $created = (string)($event['created_at'] ?? '');
            $timestamp = strtotime($created);
            if (!isset($summary[$surface]) || !in_array($outcome, ['model', 'local', 'fallback', 'private_account', 'draft', 'error'], true) || $timestamp === false || $timestamp < $cutoff || $timestamp > $now) continue;
            $summary[$surface]['requests']++;
            $attempts = $meta['provider_attempts'] ?? [];
            foreach(self::providerAttempts(is_array($attempts)?$attempts:[]) as $attempt){
                $summary[$surface]['provider_attempt_count']++;
                if($attempt['status']<200||$attempt['status']>=300||$attempt['curl_error']!==0)$summary[$surface]['provider_error_count']++;
            }
            foreach(self::toolActivity(is_array($meta['tool_activity']??null)?$meta['tool_activity']:[]) as $tool){
                $summary[$surface]['tool_call_count']++;
                if($tool['status']==='error')$summary[$surface]['tool_error_count']++;
            }
            $latest = $summary[$surface]['latest_provider_run'];
            if (is_array($attempts) && $attempts !== [] && ($latest === null || $timestamp > strtotime($latest['at']))) {
                $summary[$surface]['latest_provider_run'] = [
                    'at' => $created,
                    'outcome' => $outcome,
                    'attempts' => self::providerAttempts($attempts),
                ];
            }
            $summary[$surface][$outcome]++;
            $reason = $meta['reason'] ?? null;
            if (is_string($reason) && in_array($reason, self::RUN_REASONS, true)) {
                $summary[$surface]['reasons'][$reason] = ($summary[$surface]['reasons'][$reason] ?? 0) + 1;
            }
            $duration = max(0, (int)($meta['duration_ms'] ?? 0));
            $durations[$surface][] = $duration;
            $summary[$surface]['duration_ms'] += $duration;
            $summary[$surface]['recent_runs'][] = [
                'at' => $created,
                'trace_id' => self::validTraceId($meta['trace_id'] ?? null),
                'timestamp' => $timestamp,
                'outcome' => $outcome,
                'reason' => is_string($reason) && isset($summary[$surface]['reasons'][$reason]) ? $reason : null,
                'duration_ms' => $duration,
                'attempts' => is_array($attempts) ? self::providerAttempts($attempts) : [],
                'tools' => is_array($meta['tool_activity'] ?? null) ? self::toolActivity($meta['tool_activity']) : [],
            ];
            if ($created > $summary[$surface]['last_seen']) $summary[$surface]['last_seen'] = $created;
        }
        foreach ($summary as $surface => &$row) {
            $count = $row['requests'];
            sort($durations[$surface], SORT_NUMERIC);
            $row['average_ms'] = $count ? (int)round($row['duration_ms'] / $count) : null;
            $row['p50_ms'] = $count ? $durations[$surface][(int)ceil(0.5*$count)-1] : null;
            $row['p95_ms'] = $count ? $durations[$surface][(int)ceil(0.95 * $count) - 1] : null;
            usort($row['recent_runs'], static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);
            $row['recent_runs'] = array_slice($row['recent_runs'], 0, 20);
        }
        unset($row);
        return $summary;
    }

    public function record(string $action, string $resource, string $resourceId = '', array $details = [], ?array $actor = null): array {
        $actor ??= $_SESSION['user'] ?? [];
        $event = [
            'id' => bin2hex(random_bytes(8)),
            'event' => trim($resource . '.' . $action, '.'),
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'status' => 'recorded',
            'actor_email' => $actor['email'] ?? 'system',
            'actor_name' => $actor['name'] ?? '',
            'meta' => $details,
            'created_at' => date('c'),
        ];
        // Insert just this event. The previous implementation read the whole table,
        // appended one row and rewrote every row, which is O(n) per admin action and
        // risks losing the entire audit history if the rewrite fails part-way through.
        // Audit logging must never break the operation it is recording.
        try {
            $this->store->upsert('audit_events', $event);
            AgentTraceService::noteAuditDelivery(true);
        } catch (\Throwable $e) {
            $event=self::deliveryResult($event,false);
            AgentTraceService::noteAuditDelivery(false);
            error_log('Audit log write failed (' . $e::class . ')');
        }
        return $event;
    }
    public static function deliveryResult(array $event,bool $persisted): array {
        $event['status']=$persisted?'recorded':'not_persisted';
        return $event;
    }
}
