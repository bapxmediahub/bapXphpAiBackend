<?php
namespace App\Services;

/**
 * Redacted, provider-neutral execution evidence; stored in each conversation JSON.
 * Spans describe observed stages and their parent trace, NOT a wall-clock replay.
 * No prompts, raw SQL, PII, credentials, or hidden model reasoning.
 */
final class AgentTraceService
{
    public const VERSION = 1;

    private const SOURCES = [
        'find_order' => 'orders', 'sales_summary' => 'orders',
        'gst_register_summary' => 'orders', 'product_status' => 'products',
        'store_count' => 'business_counts', 'support_enquiries' => 'support_tickets',
        'customer_ticket_lookup' => 'support_tickets',
        'own_ticket_status' => 'support_tickets', 'search_articles' => 'published_blog',
        'coupon_status' => 'coupons', 'public_catalog' => 'visible_products',
        'public_policy' => 'public_policy',
    ];

    public static function toolEvidence(string $name, array $args, array $result): array
    {
        $filters = [];
        foreach (['from', 'to', 'period', 'topic', 'status', 'entity'] as $key) {
            $value = $args[$key] ?? $result[$key] ?? null;
            if (!is_string($value) || !preg_match('/^[a-z0-9_-]{1,40}$/i', $value)) continue;
            $filters[$key] = $value;
        }
        if (isset($args['days']) && is_int($args['days'])) $filters['days'] = max(0, min(36500, $args['days']));
        if (isset($args['order_id'])) $filters['order_id_provided'] = true;
        if (isset($args['customer_email'])) $filters['customer_email_provided'] = true;
        if (isset($args['slug'])) $filters['product_selector_provided'] = true;
        if (isset($args['query'])) $filters['search_query_provided'] = true;
        if (isset($args['code'])) $filters['coupon_code_provided'] = true;
        if (isset($args['reference'])) $filters['ticket_reference_provided'] = $args['reference'] !== null;

        $count = null;
        foreach (['found', 'count', 'total', 'issued_invoices', 'orders'] as $key) {
            if (isset($result[$key]) && is_numeric($result[$key])) {
                $count = max(0, (int)$result[$key]);
                break;
            }
        }
        return [
            'source' => self::SOURCES[$name] ?? 'unknown',
            'filters' => $filters,
            'result_count' => $count,
            'source_path' => is_string($result['report_url'] ?? null)
                && preg_match('#^/admin/[a-z0-9/?=&_%-]+$#i', $result['report_url'])
                ? mb_substr($result['report_url'], 0, 250) : null,
        ];
    }

    public static function safeTool(array $tool): array
    {
        $name = (string)($tool['name'] ?? '');
        $evidence = $tool['evidence'] ?? [];
        if (!is_array($evidence)) $evidence = [];
        return [
            'name' => mb_substr($name, 0, 64),
            'status' => in_array($tool['status'] ?? null, ['complete','error'], true) ? $tool['status'] : 'error',
            'reason' => preg_match('/^[a-z_]{1,48}$/', (string)($tool['reason'] ?? '')) ? $tool['reason'] : null,
            'duration_ms' => max(0, min(120000, (int)($tool['duration_ms'] ?? 0))),
            'evidence' => [
                'source' => in_array(($evidence['source'] ?? ''), array_values(self::SOURCES), true) ? $evidence['source'] : 'unknown',
                'filters' => self::safeFilters((array)($evidence['filters'] ?? [])),
                'result_count' => is_int($evidence['result_count'] ?? null) ? max(0, $evidence['result_count']) : null,
                'source_path' => (is_string($evidence['source_path'] ?? null)
                    && preg_match('#^/admin/[a-z0-9/?=&_%-]+$#i',$evidence['source_path']))
                    ? mb_substr($evidence['source_path'],0,250) : null,
            ],
        ];
    }

    private static function safeFilters(array $filters): array
    {
        $out = [];
        foreach (['from','to','period','days','topic','status','entity',
                  'order_id_provided','customer_email_provided',
                  'product_selector_provided','search_query_provided',
                  'coupon_code_provided','ticket_reference_provided'] as $key) {
            $value = $filters[$key] ?? null;
            if (is_bool($value) || is_int($value)) $out[$key] = $value;
            elseif (is_string($value) && preg_match('/^[a-z0-9_-]{1,40}$/i',$value)) $out[$key] = $value;
        }
        return $out;
    }

    /** A structured linked span list, not invented timing or model-based correctness. */
    public static function build(
        string $traceId, string $outcome, array $tools, array $attempts,
        array $evaluation, string $answer, array $retrieval = []
    ): array {
        $spans = [];
        foreach (array_slice($retrieval, 0, 8) as $r) {
            if (!is_array($r)) continue;
            $spans[] = [
                'span_id'=>bin2hex(random_bytes(8)),
                'parent_span_id'=>$traceId,
                'type'=>'retrieval',
                'name'=>mb_substr((string)($r['source']??'public_data'),0,64),
                'status'=>($r['status']??'')==='error'?'error':'complete',
                'result_count'=>is_numeric($r['result_count']??null)?max(0,(int)$r['result_count']):null,
                'duration_ms'=>isset($r['duration_ms'])?max(0,(int)$r['duration_ms']):null,
            ];
        }
        foreach (array_slice($tools,0,32) as $raw) {
            if (!is_array($raw)) continue;
            $tool=self::safeTool($raw);
            $spans[]=[
                'span_id'=>bin2hex(random_bytes(8)),
                'parent_span_id'=>$traceId,
                'type'=>'tool',
                'name'=>$tool['name'],
                'status'=>$tool['status'],
                'duration_ms'=>$tool['duration_ms'],
                'evidence'=>$tool['evidence'],
            ];
        }
        foreach (array_slice($attempts,0,9) as $attempt) {
            if (!is_array($attempt)) continue;
            $status=(int)($attempt['status']??0);
            $spans[]=[
                'span_id'=>bin2hex(random_bytes(8)),
                'parent_span_id'=>$traceId,
                'type'=>'model_attempt',
                'model'=>mb_substr((string)($attempt['model']??''),0,100),
                'http_status'=>$status,
                'status'=>$status>=200 && $status<300 ? 'complete' : 'error',
                'duration_ms'=>max(0,(int)round((float)($attempt['total_ms']??0))),
            ];
        }
        $spans[]=[
            'span_id'=>bin2hex(random_bytes(8)), 'parent_span_id'=>$traceId,
            'type'=>'evaluation', 'version'=>(int)($evaluation['version']??0),
            'status'=>!empty($evaluation['passed'])?'pass':'needs_review',
        ];
        $spans[]=[
            'span_id'=>bin2hex(random_bytes(8)), 'parent_span_id'=>$traceId,
            'type'=>'response', 'route'=>in_array($outcome,['local','model','fallback','private_account','draft','error','answered'],true)?$outcome:'other',
            'characters'=>mb_strlen($answer), 'status'=>trim($answer)===''?'empty':'delivered',
        ];
        return ['version'=>self::VERSION,'trace_id'=>$traceId,'ordering'=>'grouped_by_stage_not_wall_clock',
            'spans'=>$spans];
    }
}
