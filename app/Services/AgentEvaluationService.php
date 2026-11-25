<?php
namespace App\Services;

/** Free, deterministic checks for critical policies; never ask a paid judge model. */
final class AgentEvaluationService {
    private const OWNER_REPORTS = [
        'sales_summary' => ['orders', 'revenue'],
        'order_count' => ['orders', 'count'],
        'gst_register_summary' => ['orders', 'issued_invoices'],
        'support_enquiries' => ['support_tickets', 'total'],
        'commerce_report' => ['commerce_reports', null],
    ];

    public static function evaluate(string $surface, string $question, string $answer, array $tools = [], string $outcome = 'unknown', array $retrieval = []): array {
        $checks = [];
        $checks['response_present'] = trim($answer) !== '';
        if($outcome==='error')$checks['turn_completed']=false;
        $checks['no_internal_scaffold'] = !preg_match('/<\/?(?:think|analysis|final_answer)>|\b(?:system prompt|hidden instruction)\b/i', $answer);
        $checks['no_secret_leak'] = !preg_match('/\bsk-[A-Za-z0-9_-]{16,}|-----BEGIN (?:RSA |EC )?PRIVATE KEY-----/i', $answer);
        if($surface==='support'&&$retrieval!==[])$checks['public_product_links_supported']=self::publicProductLinksSupported($answer,$retrieval);
        if($outcome==='model')$checks['public_article_links_supported']=self::publicArticleLinksSupported($answer,$tools);
        // An internal observed activity label is not an exposed model capability.
        $activityNames=AgentCapabilityPolicy::tools($surface === 'admin' ? 'enterprise' : 'support');
        if($surface==='support')$activityNames[]='customer_ticket_lookup';
        $checks['no_invalid_tool_names'] = count(array_filter($tools, static fn(array $t): bool =>
            !in_array($t['name'] ?? '', $activityNames, true)
        )) === 0;
        $evaluation = [
            'passed' => count(array_filter($checks)) === count($checks),
            'checks' => $checks,
            'version' => 3,
            'category' => 'deterministic_guardrails',
            'factual_accuracy' => 'not_evaluated',
            'groundedness' => 'not_evaluated',
        ];
        if ($surface === 'admin' && $outcome === 'model') {
            $evaluation['observations']['owner_report_lookup_observed'] = self::observedOwnerReports($tools) !== [];
        }
        return $evaluation;
    }

    /** Recognize completed, typed report evidence without interpreting the user's question. */
    private static function observedOwnerReports(array $tools): array {
        $reports = [];
        foreach ($tools as $tool) {
            $name = $tool['name'] ?? '';
            if (!is_string($name) || !isset(self::OWNER_REPORTS[$name]) || ($tool['status'] ?? null) !== 'complete') continue;
            [$source, $requiredFact] = self::OWNER_REPORTS[$name];
            $raw = $tool['evidence'] ?? [];
            if (!is_array($raw) || ($raw['source'] ?? null) !== $source || !is_int($raw['result_count'] ?? null) || $raw['result_count'] < 0) continue;
            $evidence = AgentTraceService::safeTool($tool)['evidence'];
            if ($requiredFact === null) {
                if (!isset($evidence['report'])) continue;
            } elseif (!isset($evidence['facts'][$requiredFact]) || !self::validReportPeriod($evidence['filters'])) {
                continue;
            }
            $reports[] = ['tool'=>$name, 'source'=>$source, 'filters'=>$evidence['filters'],
                'result_count'=>$evidence['result_count'], 'facts'=>$evidence['facts']];
        }
        return $reports;
    }

    private static function validReportPeriod(array $filters): bool {
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';
        foreach ([$from, $to] as $date) {
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return false;
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed === false || $parsed->format('Y-m-d') !== $date) return false;
        }
        return $from <= $to;
    }

    public static function publicProductLinksSupported(string $answer,array $retrieval): bool {
        $known=[];
        foreach($retrieval as $entry)foreach((array)($entry['sources']??[]) as $source){
            $id=(string)($source['id']??'');if(str_starts_with($id,'product:'))$known[]=substr($id,8);
        }
        preg_match_all('~/product/([^\s<>"\'\[\]\(\)]+)~u',$answer,$matches);
        foreach($matches[1] as $slug){
            if(!in_array($slug,$known,true)&&!in_array(rtrim($slug,'.,;!'),$known,true))return false;
        }
        return true;
    }

    /** Check familiar numeric whole-shop claims without inferring the model's intent or routing a request. */
    public static function publicCatalogueTotalSupported(string $answer, ?int $currentTotal): bool {
        $text = str_replace(['**', '__', '`'], '', $answer);
        $start = '(?:^|[.!?\r\n])\h*';
        $end = '(?=\h*(?:[.!?;:\r\n]|$))';
        $number = '([0-9]+(?:,[0-9]{3})*)';
        $shop = '(?:shop|store|storefront|catalog(?:ue)?)';
        $quantity = $number . '\h+(?:published\h+)?products?';
        $present = '(?:(?:there\h+are|we\h+have)\h+)?(?:currently\h+)?';
        $patterns = [
            "~{$start}(?:(?:the|our|this)\h+)?{$shop}\h+(?:(?:currently|now)\h+)?(?:has|lists|contains)\h+{$quantity}{$end}~iu",
            "~{$start}{$present}{$quantity}(?:\h+(?:published|listed))?\h+in\h+(?:the|our|this)\h+{$shop}{$end}~iu",
            "~{$start}{$present}{$number}\h+published\h+products?{$end}~iu",
            "~{$start}(?:எங்கள்\h+)?கடையில்\h+(?:தற்போது\h+)?{$number}\h+(?:வெளியிடப்பட்ட\h+)?(?:பொருட்கள்|தயாரிப்புகள்)(?:\h+உள்ளன)?{$end}~u",
        ];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[1] ?? [] as $claimedTotal) {
                if ($currentTotal === null || (int)str_replace(',', '', $claimedTotal) !== $currentTotal) return false;
            }
        }
        return true;
    }

    /** Article links in model output must be among actual completed public search results. */
    public static function publicArticleLinksSupported(string $answer,array $tools): bool {
        $known=[];
        foreach($tools as $tool){
            if(!in_array($tool['name']??'',['search_articles','read_published_article'],true)||($tool['status']??'')!=='complete')continue;
            foreach((array)($tool['evidence']['sources']??[]) as $source)
                if(is_string($source['id']??null)&&str_starts_with($source['id'],'article:'))$known[]=substr($source['id'],8);
        }
        preg_match_all('~/blog/([^\\s<>"\'\\[\\]\\(\\)]+)~u',$answer,$matches);
        foreach($matches[1] as $slug)if(!in_array($slug,$known,true)&&!in_array(rtrim($slug,'.,;!'),$known,true))return false;
        return true;
    }

    /** Repeatable golden fixtures. No provider calls or live MySQL access. */
    public static function goldenSuite(?string $fixturePath = null): array {
        $path = $fixturePath ?? app_path('tests/fixtures/agent-golden.json');
        $source = @file_get_contents($path);
        if ($source === false) throw new \RuntimeException('Agent golden fixtures unavailable');
        $dataset = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        if (($dataset['schema_version'] ?? null) !== 1 || !is_array($dataset['cases'] ?? null)) {
            throw new \RuntimeException('Invalid agent golden fixture schema');
        }
        $results = [];
        foreach ($dataset['cases'] as $case) {
            $id = (string)($case['id'] ?? '');
            if (!preg_match('/^[a-z0-9_]{4,90}$/', $id)) throw new \RuntimeException('Invalid golden case ID');
            try {
                $passed = self::goldenCase($case);
                $results[] = ['id'=>$id,'passed'=>$passed];
            } catch (\Throwable $e) {
                $results[] = ['id'=>$id,'passed'=>false,'error_type'=>$e::class];
            }
        }
        $passing = count(array_filter($results, static fn(array $r):bool=> $r['passed']));
        return [
            'schema_version'=>1,
            'dataset_hash'=>hash('sha256',$source),
            'total'=>count($results),
            'passing'=>$passing,
            'failed'=>count($results)-$passing,
            'results'=>$results,
            'metric'=>'deterministic_fixture_regressions_not_production_accuracy',
        ];
    }

    private static function goldenCase(array $case): bool {
        return match ($case['type'] ?? null) {
            'trace_scope' => AgentTraceService::toolEvidence((string)$case['tool'],(array)$case['args'],(array)$case['result'])['filters'] === (array)$case['expected_filters'],
            'provider_diagnosis' => array_map([AuditLogService::class, 'attemptDiagnosis'], $case['attempts']) === $case['expected'],
            'usage' => AgentTraceService::usageFromResponse(json_encode($case['response'],JSON_THROW_ON_ERROR)) === $case['expected'],
            'catalogue_decision' => self::fixtureCatalogueDecision($case),
            'public_links' => self::publicProductLinksSupported((string)$case['answer'],[['sources'=>$case['sources']]]) === (bool)$case['expected_pass'],
            'policy_tool' => (new SupportToolRegistry(null))->run('public_policy',(array)$case['arguments']) === $case['expected'],
            'collection' => self::fixtureCollection($case),
            'permission' => AgentCapabilityPolicy::allows(
                (string)($case['agent']??''),(string)($case['tool']??''),$case['actor']??null)
                === (bool)($case['expected_allowed']??false),
            'financial_evidence' => self::fixtureFinancialEvidence($case),
            'owner_report_evidence' => self::fixtureOwnerReportEvidence($case),
            'gst_evidence' => self::fixtureGstEvidence($case),
            'sales' => self::fixtureSales($case),
            'ticket_scope' => self::fixtureTicketScope($case),
            'report_context' => self::fixtureReportContext($case),
            'guardrail' => self::evaluate((string)($case['agent']??'support'),
                (string)($case['question']??''),(string)($case['answer']??''))['passed']
                === (bool)($case['expected_pass']??false),
            default => false,
        };
    }

    private static function fixtureCatalogueDecision(array $case): bool {
        $result=AgentContextService::catalogueSelection((array)$case['products'],(string)$case['question'],(array)($case['collections']??[]));
        return $result['intent']===$case['expected_intent'] && $result['status']===$case['expected_status']
            && array_column($result['products'],'slug')===$case['expected_slugs'];
    }

    private static function fixtureCollection(array $case): bool {
        $selected = AgentContextService::requestedCollection(
            (string)($case['question']??''),(array)($case['collections']??[]));
        if (($selected['slug']??null) !== ($case['expected_collection']??null)) return false;
        if ($selected === null) return ($case['expected_slugs'] ?? []) === [];
        $products = (new CategoryService())->collectionProducts($selected,(array)($case['products']??[]));
        return array_values(array_column($products,'slug')) === ($case['expected_slugs'] ?? []);
    }

    private static function fixtureSales(array $case): bool {
        $result = AgentToolRegistry::salesFacts(
            (array)($case['orders']??[]),(array)($case['range']??[]));
        return (int)($result['orders']??-1)===(int)($case['expected_orders']??-2)
            && (float)($result['revenue']??-1)===(float)($case['expected_revenue']??-2);
    }

    private static function fixtureOwnerReportEvidence(array $case): bool {
        $tool = $case['tool'];
        $activity = [
            'name' => $tool,
            'status' => isset($case['result']['error']) ? 'error' : 'complete',
            'evidence' => AgentTraceService::toolEvidence($tool, $case['args'], $case['result']),
        ];
        $expected = $case['expected'];
        $reports = self::observedOwnerReports([$activity]);
        $matches = false;
        foreach ($reports as $report) {
            $matches = $report['tool'] === $expected['tool'] && $report['source'] === $expected['source']
                && $report['filters'] === $expected['filters'] && $report['result_count'] === $expected['result_count']
                && $report['facts'] === $expected['facts'];
            if ($matches) break;
        }
        $evaluation = self::evaluate('admin', $case['question'], $case['answer'], [$activity], 'model');
        return $matches === $case['expected_match']
            && ($evaluation['observations']['owner_report_lookup_observed'] ?? null) === $case['expected_observed']
            && !array_key_exists('source_lookup_used', $evaluation['checks'])
            && $evaluation['factual_accuracy'] === 'not_evaluated'
            && $evaluation['groundedness'] === 'not_evaluated';
    }

    private static function fixtureFinancialEvidence(array $case): bool {
        $facts=AgentToolRegistry::salesFacts((array)$case['orders'],(array)$case['range']);
        $evidence=AgentTraceService::toolEvidence('sales_summary',(array)$case['range'],$facts);
        return (int)$facts['orders']===(int)$case['expected_orders']
            &&(float)$facts['revenue']===(float)$case['expected_revenue']
            &&$evidence['filters']===$case['range']
            &&$evidence['facts']['orders']===(int)$case['expected_orders']
            &&$evidence['facts']['revenue']===(float)$case['expected_revenue'];
    }

    private static function fixtureGstEvidence(array $case): bool {
        $range=(array)$case['range'];$orders=GstRegisterService::ordersForPeriod((array)$case['orders'],$range['from'],$range['to']);
        $totals=GstRegisterService::totals($orders);$expected=array_map('floatval',(array)$case['expected_totals']);
        $e=AgentTraceService::toolEvidence('gst_register_summary',$range,$range+['issued_invoices'=>count($orders),'totals'=>$totals]);
        return count($orders)===(int)$case['expected_count']&&$totals===$expected&&$e['result_count']===(int)$case['expected_count']
            &&$e['filters']===$range&&$e['facts']['tax']===$expected['tax'];
    }

    private static function fixtureReportContext(array $case): bool {
        $tool = new AgentToolRegistry(previousReport:(array)$case['previous_report']);
        return $tool->validatedPreviousReport() === $case['expected_context'];
    }

    private static function fixtureTicketScope(array $case): bool {
        $facts = AgentContextService::supportTicketFacts((array)$case['tickets'], null, $case['status'], $case['period']);
        return $facts['count'] === count($case['expected_recent']) && $facts['recent'] === $case['expected_recent'];
    }

    /** Staff-declared review labels, not model-generated accuracy or customer satisfaction. */
    public static function reviewSummary(array $recent): array {
        $totals=['conversations'=>count($recent),'turns'=>0,'human_reviewed'=>0,'supported'=>0,'needs_correction'=>0,
            'insufficient_evidence'=>0,'agent_assisted'=>0,'verification_tests'=>0,'unreviewed'=>0,'topics'=>[],'reasons'=>[]];
        foreach($recent as $row){
            $totals['turns']+=(int)($row['turn_count']??0);$metrics=$row['quality_review']??[];
            $topic=AgentTraceService::safeRetrieval(['intent'=>$row['intent']??'unknown'])['intent'];
            $totals['topics'][$topic]??=['conversations'=>0,'human_reviewed'=>0,'supported'=>0,'needs_correction'=>0,'insufficient_evidence'=>0];
            $totals['topics'][$topic]['conversations']++;
            foreach(['human_reviewed','supported','needs_correction','insufficient_evidence','agent_assisted','verification_tests','unreviewed'] as $key){
                $count=max(0,(int)($metrics[$key]??0));$totals[$key]+=$count;
                if(array_key_exists($key,$totals['topics'][$topic]))$totals['topics'][$topic][$key]+=$count;
            }
            foreach($metrics['reasons']??[] as $reason=>$count)if(isset(AgentConversationService::REVIEW_REASONS[$reason]))$totals['reasons'][$reason]=($totals['reasons'][$reason]??0)+max(0,(int)$count);
        }
        $totals['supported_percent']=$totals['human_reviewed']>0?round(100*$totals['supported']/$totals['human_reviewed'],1):null;
        $totals['denominator']='staff_declared_human_reviewed_turns_in_recent_conversations';
        return $totals;
    }

    /** Operates on the dashboard's explicitly bounded recent conversation sample. */
    public static function supportOutcomeSummary(array $recent): array {
        $emptyCounts = ['confirmed_resolved'=>0,'needs_followup'=>0,'escalated'=>0,'awaiting_feedback'=>0,'unknown'=>0];
        $counts = $emptyCounts;
        $topics = [];
        foreach ($recent as $row) {
            if (($row['surface']??'')!=='support') continue;
            $status = (string)($row['resolution_status']??'unknown');
            if (!array_key_exists($status,$counts)) $status='unknown';
            $counts[$status]++;
            $topic = AgentTraceService::safeRetrieval(['intent'=>$row['intent'] ?? null])['intent'];
            $topics[$topic] ??= $emptyCounts;
            $topics[$topic][$status]++;
        }
        ksort($topics);
        $total=array_sum($counts);
        return ['sample_size'=>$total,'counts'=>$counts,'topics'=>$topics,
            'confirmed_percent'=>$total > 0 ? round(100*$counts['confirmed_resolved']/$total,1) : null,
            'denominator'=>'recent_40_conversations'];
    }

}
