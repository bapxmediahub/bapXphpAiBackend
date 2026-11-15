<?php
namespace App\Services;

/**
 * Redacted, provider-neutral execution evidence; stored in each conversation JSON.
 * Observed spans are recorded at execution boundaries; legacy summaries remain explicitly stage-grouped.
 * No prompts, raw SQL, PII, credentials, or hidden model reasoning.
 */
final class AgentTraceService
{
    public const VERSION = 2;
    private static ?array $active = null;
    private static array $clocks = [];
    private static ?\Closure $checkpoint = null;
    private static int $beganNs = 0;

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
            $value = isset($result['error']) ? null : ($result[$key] ?? null);
            if (!is_string($value) || !preg_match('/^[a-z0-9_-]{1,40}$/i', $value)) continue;
            $filters[$key] = $value;
        }
        if (!isset($result['error']) && isset($result['days']) && is_int($result['days'])) $filters['days'] = max(0, min(36500, $result['days']));
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
        $facts=self::aggregateFacts($name,$result);
        return [
            'observed_at'=>self::now(),
            'facts'=>$facts,
            'facts_sha256'=>hash('sha256',json_encode(['filters'=>$filters,'facts'=>$facts],JSON_THROW_ON_ERROR)),
            'tool_version_sha256'=>hash_file('sha256',app_path('app/Services/AgentToolRegistry.php')),
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
                'observed_at'=>is_string($evidence['observed_at']??null)&&preg_match('/^\d{4}-\d{2}-\d{2}T[0-9:.]+Z$/D',$evidence['observed_at'])?$evidence['observed_at']:null,
                'facts'=>self::aggregateFacts($name,(array)($evidence['facts']??[])),
                'facts_sha256'=>self::validDigest($evidence['facts_sha256']??null),
                'tool_version_sha256'=>self::validDigest($evidence['tool_version_sha256']??null),
                'result_count' => is_int($evidence['result_count'] ?? null) ? max(0, $evidence['result_count']) : null,
                'source_path' => (is_string($evidence['source_path'] ?? null)
                    && preg_match('#^/admin/[a-z0-9/?=&_%-]+$#i',$evidence['source_path']))
                    ? mb_substr($evidence['source_path'],0,250) : null,
            ],
        ];
    }

    private static function validDigest(mixed $value): ?string {
        return is_string($value)&&preg_match('/^[a-f0-9]{64}$/D',$value)?$value:null;
    }
    private static function aggregateFacts(string $name,array $result): array {
        if(isset($result['error']))return [];
        $keys=match($name){
            'sales_summary'=>['orders','revenue','units','gross','refunded'],
            'gst_register_summary'=>['issued_invoices','review_invoices','taxable','tax','cgst','sgst','igst'],
            'support_enquiries'=>['total'],
            'store_count','customer_ticket_lookup','own_ticket_status'=>['count'],
            'product_status'=>['price','offer_price'],
            default=>[],
        };
        $facts=[];
        foreach($keys as $key){$value=$result[$key]??($result['totals'][$key]??null);
            if((is_int($value)||is_float($value))&&is_finite((float)$value))$facts[$key]=$value;}
        return $facts;
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

    /** Request-scoped state: reset at each classic PHP HTTP request, never shared across actors. */
    public static function begin(string $traceId, ?callable $checkpoint = null, array $limits = []): void
    {
        if (!preg_match('/^[a-f0-9]{16}$/D', $traceId)) throw new \InvalidArgumentException('Invalid trace ID');
        self::reset();
        self::$beganNs=hrtime(true);
        self::$active = ['version'=>self::VERSION, 'trace_id'=>$traceId,
            'ordering'=>'observed_execution', 'started_at'=>self::now(), 'spans'=>[],
            'instruction_sha256'=>null, 'instruction_manifest'=>null,
            'budget'=>[
                'max_model_calls'=>min(12,max(1,(int)($limits['max_model_calls']??6))),
                'max_duration_ms'=>min(120000,max(100,(int)($limits['max_duration_ms']??30000))),
                'max_observed_tokens'=>min(128000,max(1,(int)($limits['max_observed_tokens']??16000))),
                'model_calls'=>0,'observed_total_tokens'=>0,'usage_complete'=>true,
            ],
            'operational_audit'=>['recorded'=>0,'not_persisted'=>0],
        ];
        self::$checkpoint = $checkpoint === null ? null : \Closure::fromCallable($checkpoint);
    }

    /** Record the exact role bundle actually loaded for a model prompt, not a PHP-file surrogate. */
    public static function observeInstructionManifest(array $manifest): void
    {
        if (self::$active === null) return;
        if (!in_array($manifest['surface'] ?? '', ['support','admin'], true)
            || !preg_match('/^[a-f0-9]{64}$/D',(string)($manifest['sha256'] ?? ''))) {
            throw new \InvalidArgumentException('Invalid runtime instruction fingerprint');
        }
        self::$active['instruction_sha256'] = $manifest['sha256'];
        self::$active['instruction_manifest'] = $manifest;
        self::persist();
    }

    public static function reset(): void { self::$active=null; self::$clocks=[]; self::$checkpoint=null; self::$beganNs=0; }
    public static function activeTraceId(): ?string { return self::$active['trace_id'] ?? null; }
    public static function snapshot(): array { return self::$active ?? ['version'=>self::VERSION,'spans'=>[]]; }
    private static function now(): string {
        return (new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }
    private static function persist(): void {
        if (self::$checkpoint !== null) (self::$checkpoint)(self::snapshot());
    }

    /** Begin is checkpointed before the caller runs its provider/tool/action body. */
    public static function startSpan(string $type, string $name): ?string
    {
        if (self::$active === null) return null;
        if (!in_array($type,['retrieval','tool','model_attempt','action','evaluation','response'],true)) {
            throw new \InvalidArgumentException('Unsupported trace span');
        }
        if (count(self::$active['spans'])>=128) throw new \RuntimeException('Agent trace step budget exceeded');
        if (!in_array($type,['evaluation','response'],true) && self::remainingMs()<=0) {
            self::$active['budget']['exhausted']='deadline';throw new \RuntimeException('Agent execution deadline reached');
        }
        if ($type==='model_attempt') {
            $budget=&self::$active['budget'];
            if($budget['model_calls']>=$budget['max_model_calls'] || $budget['observed_total_tokens']>=$budget['max_observed_tokens']) {
                $budget['exhausted']='provider_budget';throw new \RuntimeException('Agent provider budget exhausted');
            }
            $budget['model_calls']++;unset($budget);
        }
        $id=bin2hex(random_bytes(8));
        if ($type==='tool' && !array_key_exists($name,self::SOURCES)) $name='unknown_tool';
        if ($type==='model_attempt' && !SecretService::validModelId($name)) $name='configured_provider';
        $name=preg_match('/^[a-zA-Z0-9_.:\/-]{1,100}$/D',$name)?$name:'observed_stage';
        $span=['span_id'=>$id,'parent_span_id'=>self::$active['trace_id'],
            'sequence'=>count(self::$active['spans'])+1,'type'=>$type,'name'=>$name,
            'started_at'=>self::now(),'ended_at'=>null,'duration_ms'=>null,'status'=>'running'];
        if ($type==='tool') {
            foreach(array_reverse(self::$active['spans']) as $prior) {
                if($prior['type']==='model_attempt'){$span['caused_by_span_id']=$prior['span_id'];break;}
            }
        }
        self::$clocks[$id]=hrtime(true);
        self::$active['spans'][]=$span;
        self::persist();
        return $id;
    }

    public static function endSpan(?string $id, string $status, array $attributes = []): void
    {
        if ($id===null || self::$active===null) return;
        if (!in_array($status,['complete','error','pass','needs_review','produced','empty'],true)) $status='error';
        foreach(self::$active['spans'] as &$span) {
            if($span['span_id']!==$id)continue;
            if($span['ended_at']!==null) return;
            $span['ended_at']=self::now();
            $span['duration_ms']=max(0,round((hrtime(true)-(self::$clocks[$id]??hrtime(true)))/1_000_000,3));
            $span['status']=$status;
            foreach(['http_status','result_count','characters','version'] as $key) {
                if(isset($attributes[$key]) && is_int($attributes[$key]))$span[$key]=max(0,$attributes[$key]);
            }
            if(is_array($attributes['evidence']??null)) {
                $span['evidence']=self::safeTool(['name'=>$span['name'],'status'=>'complete','evidence'=>$attributes['evidence']])['evidence'];
            }
            if(is_array($attributes['usage']??null)) {
                $span['usage']=[];
                foreach(['input_tokens','output_tokens','total_tokens','cached_input_tokens'] as $key) {
                    $v=$attributes['usage'][$key]??null;
                    $span['usage'][$key]=is_int($v)&&$v>=0&&$v<=1000000000?$v:null;
                }
            }
            if($span['type']==='model_attempt') {
                $u=$span['usage']??[];
                $total=$u['total_tokens']??(isset($u['input_tokens'],$u['output_tokens'])?$u['input_tokens']+$u['output_tokens']:null);
                if($total===null)self::$active['budget']['usage_complete']=false;
                else self::$active['budget']['observed_total_tokens']+=(int)$total;
                if(($digest=self::validDigest($attributes['request_sha256']??null))!==null)$span['request_sha256']=$digest;
            }
            if(isset($attributes['first_byte_ms']) && is_numeric($attributes['first_byte_ms'])) {
                $span['first_byte_ms']=max(0,round((float)$attributes['first_byte_ms'],3));
            }
            if(isset($attributes['route']) && in_array($attributes['route'],['local','model','fallback','private_account','draft','error','answered','escalated'],true))$span['route']=$attributes['route'];
            break;
        }
        unset($span);
        self::persist();
    }

    public static function remainingMs(): int {
        if(self::$active===null)return 120000;
        return max(0,self::$active['budget']['max_duration_ms']-(int)((hrtime(true)-self::$beganNs)/1_000_000));
    }
    /** Optional logging never invokes mandatory checkpoints or retries business actions. */
    public static function noteAuditDelivery(bool $persisted): void {
        if(self::$active!==null)self::$active['operational_audit'][$persisted?'recorded':'not_persisted']++;
    }

    /** Counts only: never retains provider message bodies or invents a missing cost/token value. */
    public static function usageFromResponse(string|false $body): array
    {
        $decoded=is_string($body)?json_decode($body,true):null;
        $u=is_array($decoded)?($decoded['usage']??[]):[];
        $g=is_array($decoded)?($decoded['usageMetadata']??[]):[];
        if(!is_array($u))$u=[];if(!is_array($g))$g=[];
        $details=is_array($u['prompt_tokens_details']??null)?$u['prompt_tokens_details']:[];
        $values=['input_tokens'=>$u['prompt_tokens']??$u['input_tokens']??$g['promptTokenCount']??null,
            'output_tokens'=>$u['completion_tokens']??$u['output_tokens']??$g['candidatesTokenCount']??null,
            'total_tokens'=>$u['total_tokens']??$g['totalTokenCount']??null,
            'cached_input_tokens'=>$details['cached_tokens']??$g['cachedContentTokenCount']??null];
        foreach($values as &$v)if(!is_int($v)||$v<0||$v>1000000000)$v=null;
        unset($v);return $values;
    }

    /** A structured linked span list, not invented timing or model-based correctness. */
    public static function build(
        string $traceId, string $outcome, array $tools, array $attempts,
        array $evaluation, string $answer, array $retrieval = []
    ): array {
        if (self::activeTraceId() === $traceId) {
            $response=self::startSpan('response','response');
            self::endSpan($response,trim($answer)===''?'empty':'produced',[
                'route'=>$outcome,'characters'=>mb_strlen($answer)]);
            return self::snapshot();
        }
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
            'characters'=>mb_strlen($answer), 'status'=>trim($answer)===''?'empty':'produced',
        ];
        return ['version'=>self::VERSION,'trace_id'=>$traceId,'ordering'=>'grouped_by_stage_not_wall_clock',
            'spans'=>$spans];
    }
}
