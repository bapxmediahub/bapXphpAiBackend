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
        'find_order' => 'orders', 'sales_summary' => 'orders', 'commerce_report' => 'commerce_reports',
        'gst_register_summary' => 'orders', 'product_status' => 'products',
        'store_count' => 'business_counts', 'support_enquiries' => 'support_tickets',
        'customer_ticket_lookup' => 'support_tickets',
        'own_ticket_status' => 'support_tickets', 'search_articles' => 'published_blog',
        'coupon_status' => 'coupons', 'public_catalog' => 'visible_products',
        'public_policy' => 'public_policy', 'operator_guide' => 'operator_help',
        'public_catalogue_summary'=>'visible_products','public_best_sellers'=>'visible_products',
        'get_public_contact' => 'public_contact', 'list_public_services' => 'public_services',
    ];

    public static function toolEvidence(string $name, array $args, array $result): array
    {
        $filters = [];
        foreach (['from', 'to', 'period', 'topic', 'status', 'entity', 'report'] as $key) {
            $value = isset($result['error']) ? null : ($result[$key] ?? null);
            if (!is_string($value) || !preg_match('/^[a-z0-9_-]{1,40}$/i', $value)) continue;
            $filters[$key] = $value;
        }
        if (!isset($result['error']) && isset($result['days']) && is_int($result['days'])) $filters['days'] = max(0, min(36500, $result['days']));
        if ($name === 'commerce_report' && !isset($result['error']) && is_int($result['limit'] ?? null)) $filters['limit'] = $result['limit'];
        if (isset($args['order_id'])) $filters['order_id_provided'] = true;
        if (isset($args['customer_email'])) $filters['customer_email_provided'] = true;
        if (isset($args['slug'])) $filters['product_selector_provided'] = true;
        if (isset($args['query'])) $filters['search_query_provided'] = true;
        if (isset($args['code'])) $filters['coupon_code_provided'] = true;
        if (isset($args['reference'])) $filters['ticket_reference_provided'] = $args['reference'] !== null;

        $count = null;
        foreach (['found', 'count', 'total', 'total_rows', 'issued_invoices', 'orders'] as $key) {
            if (isset($result[$key]) && is_numeric($result[$key])) {
                $count = max(0, (int)$result[$key]);
                break;
            }
        }
        if ($name === 'get_public_contact' && !isset($result['error'])
            && ($result['path'] ?? null) === '/contact') $count = 1;
        $facts=self::aggregateFacts($name,$result);
        $report = $name === 'commerce_report' && !isset($result['error']) ? self::safeCommerceReport($result) : null;
        $fingerprint = ['filters'=>$filters,'facts'=>$facts];
        if ($report !== null) $fingerprint['report'] = $report;
        $evidence = [
            'observed_at'=>self::now(),
            'facts'=>$facts,
            'facts_sha256'=>hash('sha256',json_encode($fingerprint,JSON_THROW_ON_ERROR)),
            'tool_version_sha256'=>hash_file('sha256',app_path('app/Services/'.(in_array($name,AgentCapabilityPolicy::tools('support'),true)&&!in_array($name,AgentCapabilityPolicy::tools('enterprise'),true)?'SupportToolRegistry':'AgentToolRegistry').'.php')),
            'source' => self::SOURCES[$name] ?? 'unknown',
            'filters' => $filters,
            'result_count' => $count,
            'source_path' => is_string($result['report_url'] ?? null)
                && preg_match('#^/admin/[a-z0-9/?=&_%-]+$#i', $result['report_url'])
                ? mb_substr($result['report_url'], 0, 250) : null,
        ];
        if ($report !== null) $evidence['report'] = $report;
        return $evidence;
    }

    public static function safeTool(array $tool): array
    {
        $name = (string)($tool['name'] ?? '');
        $evidence = $tool['evidence'] ?? [];
        if (!is_array($evidence)) $evidence = [];
        $safe = [
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
        if ($name === 'commerce_report' && $safe['status'] === 'complete'
            && $safe['evidence']['source'] === 'commerce_reports' && is_array($evidence['report'] ?? null)) {
            $report = self::safeCommerceReport($evidence['report']);
            if ($report !== null) {
                $safe['evidence']['report'] = $report;
                // The saved payload cannot attach a different period or navigation target.
                $safe['evidence']['filters'] = ['report'=>$report['type'],'limit'=>$report['limit']]
                    + (isset($report['from']) ? ['from'=>$report['from'],'to'=>$report['to']] : []);
                $safe['evidence']['source_path'] = $report['report_url'];
                $safe['evidence']['result_count'] = $report['total_rows'];
            }
        }
        return $safe;
    }

    /**
     * Private, typed presentation evidence, never a generic saved tool-result channel.
     * Customer rows contain only fixed frequency bands. Product/coupon labels are
     * intentionally retained for this owner report; lookup arguments and identities are not.
     */
    public static function safeCommerceReport(array $result): ?array
    {
        $type = $result['type'] ?? $result['report'] ?? null;
        if (!is_string($type) || !isset(AgentToolRegistry::COMMERCE_REPORT_BASES[$type])
            || (isset($result['type'], $result['report']) && $result['type'] !== $result['report'])
            || ($result['currency'] ?? null) !== 'INR') return null;
        $limit = $result['limit'] ?? null;
        $total = $result['total_rows'] ?? null;
        $rows = $result['rows'] ?? null;
        if (!is_int($limit) || $limit < 1 || $limit > 10 || !is_int($total) || $total < 0 || $total > 1_000_000_000
            || !is_array($rows) || !array_is_list($rows) || count($rows) > $limit || count($rows) > $total
            || count($rows) !== min($limit, $total) || !is_array($result['summary'] ?? null)) return null;
        $range = [];
        if ($type === 'stock') {
            if (array_key_exists('from', $result) || array_key_exists('to', $result)) return null;
        } else {
            foreach (['from','to'] as $key) {
                $value = $result[$key] ?? null;
                if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if ($date === false || $date->format('Y-m-d') !== $value) return null;
                $range[$key] = $value;
            }
            if ($range['from'] > $range['to']
                || (new \DateTimeImmutable($range['from']))->diff(new \DateTimeImmutable($range['to']))->days > 365) return null;
        }
        $number = static function (mixed $value, bool $integer): int|float|null {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)
                || $value < 0 || $value > ($integer ? 1_000_000_000 : 1e15)
                || ($integer && floor((float)$value) !== (float)$value)) return null;
            return $integer ? (int)$value : round((float)$value, 2);
        };
        $text = static function (mixed $value, int $max): ?string {
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) return null;
            $value = trim((string)preg_replace('/[\x00-\x1F\x7F]/u', ' ', strip_tags($value)));
            return $value !== '' ? mb_substr($value, 0, $max) : null;
        };
        // true = a count, false = an amount in INR.
        $summaries = [
            'products'=>['products'=>true,'units'=>true,'gross_line_value'=>false,'allocated_net'=>false],
            'stock'=>['products'=>true,'in_stock'=>true,'out_of_stock'=>true,'inactive'=>true,'draft'=>true,'unknown_status'=>true],
            'customers'=>['customers'=>true,'repeat_customers'=>true,'paid_orders'=>true,'unidentified_orders'=>true,
                'gross_paid'=>false,'recorded_refunds'=>false,'net_paid'=>false],
            'coupons'=>['coupons'=>true,'uses'=>true,'discount'=>false,'net'=>false],
        ];
        $rowNumbers = [
            'products'=>['order_lines'=>true,'units'=>true,'gross'=>false,'net'=>false],
            'stock'=>['price'=>false],
            'customers'=>['customers'=>true,'orders'=>true,'gross'=>false,'refunded'=>false,'net'=>false],
            'coupons'=>['uses'=>true,'discount'=>false,'net'=>false],
        ];
        $summary = [];
        foreach ($summaries[$type] as $key=>$integer) {
            $value = $number($result['summary'][$key] ?? null, $integer);
            if ($value === null) return null;
            $summary[$key] = $value;
        }
        $safeRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) return null;
            $safe = [];
            $labelKey = match ($type) { 'customers'=>'band', 'coupons'=>'coupon', default=>'product' };
            $label = $text($row[$labelKey] ?? null, $type === 'coupons' ? 80 : 160);
            if ($label === null || ($type === 'customers' && !in_array($label, ['one_order','two_to_four','five_or_more'], true))) return null;
            $safe[$labelKey] = $label;
            if ($type === 'stock') {
                if (!in_array($row['status'] ?? null, ['in_stock','active','out_of_stock','inactive','draft','unknown_status'], true)
                    || !array_key_exists('offer_price', $row)) return null;
                $safe['status'] = $row['status'];
                $safe['offer_price'] = $row['offer_price'] === null ? null : $number($row['offer_price'], false);
                if ($row['offer_price'] !== null && $safe['offer_price'] === null) return null;
            }
            foreach ($rowNumbers[$type] as $key=>$integer) {
                $value = $number($row[$key] ?? null, $integer);
                if ($value === null) return null;
                $safe[$key] = $value;
            }
            $safeRows[] = $safe;
        }
        return ['type'=>$type] + $range + [
            'basis'=>AgentToolRegistry::COMMERCE_REPORT_BASES[$type], 'currency'=>'INR',
            'limit'=>$limit, 'total_rows'=>$total, 'rows'=>$safeRows, 'summary'=>$summary,
            'report_url'=>'/admin/tax-report?' . http_build_query(['report'=>$type] + $range),
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
        foreach (['from','to','period','days','topic','status','entity','report','limit',
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
        if (count(self::$active['spans'])>=128) self::stopForBudget('trace_steps', 'trace_step_budget', 'Agent trace step budget exceeded');
        if (!in_array($type,['evaluation','response'],true) && self::remainingMs()<=0) {
            self::stopForBudget('deadline', 'request_deadline', 'Agent execution deadline reached');
        }
        if ($type==='model_attempt') {
            $budget=&self::$active['budget'];
            if($budget['model_calls']>=$budget['max_model_calls'] || $budget['observed_total_tokens']>=$budget['max_observed_tokens']) {
                $limit = $budget['model_calls'] >= $budget['max_model_calls'] ? 'model_calls' : 'observed_tokens';
                self::stopForBudget($limit, 'provider_budget', 'Agent provider budget exhausted');
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

    private static function stopForBudget(string $limit, string $reason, string $message): never {
        self::$active['budget']['exhausted'] = $limit;
        self::persist();
        throw new AgentBudgetException($reason, $message);
    }

    public static function budgetReason(array $trace): ?string {
        return match ($trace['budget']['exhausted'] ?? null) {
            'deadline' => 'request_deadline',
            'model_calls', 'observed_tokens', 'provider_budget' => 'provider_budget',
            'trace_steps' => 'trace_step_budget',
            default => null,
        };
    }

    /** Only measured numeric transport metadata can enter audit and conversation records. */
    public static function attemptTimings(array $attempt): array {
        $timings = [];
        if (is_int($attempt['curl_error'] ?? null) && $attempt['curl_error'] >= 0) {
            $timings['curl_error'] = $attempt['curl_error'];
        }
        if (is_int($attempt['timeout_ms'] ?? null) && $attempt['timeout_ms'] > 0) {
            $timings['timeout_ms'] = $attempt['timeout_ms'];
        }
        if (is_numeric($attempt['first_byte_ms'] ?? null) && is_finite((float)$attempt['first_byte_ms']) && $attempt['first_byte_ms'] > 0) {
            $timings['first_byte_ms'] = round((float)$attempt['first_byte_ms'], 3);
        }
        return $timings;
    }

    public static function safeRetrieval(array $entry): array {
        $intent=in_array($entry['intent']??'',['catalogue_search','catalogue_browse','catalogue_summary','best_sellers','collection','comparison','public_policy','public_contact','public_services','own_order','own_ticket','unknown'],true)?$entry['intent']:'unknown';
        $sources=[];
        foreach(array_slice((array)($entry['sources']??[]),0,20) as $source){
            if(!is_array($source)||!preg_match('/^product:(?:[a-zA-Z0-9_.-]|%[a-fA-F0-9]{2}){1,640}$/D',(string)($source['id']??'')))continue;
            $date=$source['last_updated']??null;
            $sources[]=['id'=>$source['id'],'last_updated'=>is_string($date)&&preg_match('/^\d{4}-\d{2}-\d{2}T/',$date)&&strtotime($date)!==false?$date:null];
        }
        return ['version'=>1,'intent'=>$intent,
            'status'=>in_array($entry['status']??'',['matched','no_match','clarification','not_applicable'],true)?$entry['status']:'not_applicable',
            'reason'=>preg_match('/^[a-z_]{1,64}$/D',(string)($entry['reason']??''))?$entry['reason']:'unclassified',
            'candidate_count'=>is_numeric($entry['candidate_count'] ?? null) ? max(0, (int)$entry['candidate_count']) : null,
            'result_count'=>is_numeric($entry['result_count'] ?? null) ? max(0, (int)$entry['result_count']) : null,
            'sources'=>$sources,'result_sha256'=>self::validDigest($entry['result_sha256']??null)];
    }

    public static function endSpan(?string $id, string $status, array $attributes = []): void
    {
        if ($id===null || self::$active===null) return;
        if (!in_array($status,['complete','error','pass','needs_review','produced','empty','skipped'],true)) $status='error';
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
            if(is_array($attributes['retrieval']??null))$span['retrieval']=self::safeRetrieval($attributes['retrieval']);
            if(is_array($attributes['usage']??null)) {
                $span['usage']=[];
                foreach(['input_tokens','output_tokens','total_tokens','cached_input_tokens'] as $key) {
                    $v=$attributes['usage'][$key]??null;
                    $span['usage'][$key]=is_int($v)&&$v>=0&&$v<=1000000000?$v:null;
                }
            }
            if($span['type']==='model_attempt') {
                $span += self::attemptTimings($attributes);
                $u=$span['usage']??[];
                $total=$u['total_tokens']??(isset($u['input_tokens'],$u['output_tokens'])?$u['input_tokens']+$u['output_tokens']:null);
                if($total===null)self::$active['budget']['usage_complete']=false;
                else self::$active['budget']['observed_total_tokens']+=(int)$total;
                if(($digest=self::validDigest($attributes['request_sha256']??null))!==null)$span['request_sha256']=$digest;
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
                'status'=>$status>=200 && $status<300 && empty($attempt['curl_error']) ? 'complete' : 'error',
                'duration_ms'=>max(0,(int)round((float)($attempt['total_ms']??0))),
            ] + self::attemptTimings($attempt);
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
