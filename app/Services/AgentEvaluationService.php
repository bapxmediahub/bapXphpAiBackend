<?php
namespace App\Services;

/** Free, deterministic checks for critical policies; never ask a paid judge model. */
final class AgentEvaluationService {
    public static function evaluate(string $surface, string $question, string $answer, array $tools = [], string $outcome = 'unknown', array $retrieval = []): array {
        $checks = [];
        $checks['response_present'] = trim($answer) !== '';
        $checks['no_internal_scaffold'] = !preg_match('/<\/?(?:think|analysis|final_answer)>|\b(?:system prompt|hidden instruction)\b/i', $answer);
        $checks['no_secret_leak'] = !preg_match('/\bsk-[A-Za-z0-9_-]{16,}|-----BEGIN (?:RSA |EC )?PRIVATE KEY-----/i', $answer);
        if($surface==='support'&&$retrieval!==[])$checks['public_product_links_supported']=self::publicProductLinksSupported($answer,$retrieval);
        // An internal observed activity label is not an exposed model capability.
        $activityNames=AgentCapabilityPolicy::tools($surface === 'admin' ? 'enterprise' : 'support');
        if($surface==='support')$activityNames[]='customer_ticket_lookup';
        $checks['no_invalid_tool_names'] = count(array_filter($tools, static fn(array $t): bool =>
            !in_array($t['name'] ?? '', $activityNames, true)
        )) === 0;
        if ($surface === 'admin' && preg_match('/\b(?:revenue|sales|orders|gst|support tickets?)\b/i', $question)) {
            // Exact local controller reports can be grounded without function calls.
            // A model-synthesized number with no observed tool warrants review.
            $checks['source_lookup_used'] = $tools !== [] || $outcome === 'local';
        }
        return [
            'passed' => count(array_filter($checks)) === count($checks),
            'checks' => $checks,
            'version' => 2,
            'category' => 'deterministic_guardrails',
            'factual_accuracy' => 'not_evaluated',
            'groundedness' => 'not_evaluated',
        ];
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
            'usage' => AgentTraceService::usageFromResponse(json_encode($case['response'],JSON_THROW_ON_ERROR)) === $case['expected'],
            'catalogue_decision' => self::fixtureCatalogueDecision($case),
            'public_links' => self::publicProductLinksSupported((string)$case['answer'],[['sources'=>$case['sources']]]) === (bool)$case['expected_pass'],
            'policy_reply' => SupportBotService::policyAnswer((string)$case['question'],(array)$case['policies']) === $case['expected'],
            'collection' => self::fixtureCollection($case),
            'permission' => AgentCapabilityPolicy::allows(
                (string)($case['agent']??''),(string)($case['tool']??''),$case['actor']??null)
                === (bool)($case['expected_allowed']??false),
            'financial_answer' => self::fixtureFinancialAnswer($case),
            'gst_evidence' => self::fixtureGstEvidence($case),
            'sales' => self::fixtureSales($case),
            'ticket_intent' => (SupportBotService::ticketLookupRequest((string)($case['question']??''))!==null)
                === (bool)($case['expected_lookup']??false),
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

    private static function fixtureFinancialAnswer(array $case): bool {
        $facts=AgentToolRegistry::salesFacts((array)$case['orders'],(array)$case['range']);
        $answer=AgentToolRegistry::aggregateAnswerFromSummary((string)$case['question'],$facts,(int)$case['days']);
        $evidence=AgentTraceService::toolEvidence('sales_summary',(array)$case['range'],$facts);
        return $answer===$case['expected_answer']&&(int)$facts['orders']===(int)$case['expected_orders']
            &&(float)$facts['revenue']===(float)$case['expected_revenue']
            &&$evidence['filters']===$case['range']&&$evidence['facts']['revenue']===(float)$case['expected_revenue'];
    }

    private static function fixtureGstEvidence(array $case): bool {
        $range=(array)$case['range'];$orders=GstRegisterService::ordersForPeriod((array)$case['orders'],$range['from'],$range['to']);
        $totals=GstRegisterService::totals($orders);$expected=array_map('floatval',(array)$case['expected_totals']);
        $e=AgentTraceService::toolEvidence('gst_register_summary',$range,$range+['issued_invoices'=>count($orders),'totals'=>$totals]);
        return count($orders)===(int)$case['expected_count']&&$totals===$expected&&$e['result_count']===(int)$case['expected_count']
            &&$e['filters']===$range&&$e['facts']['tax']===$expected['tax'];
    }

    private static function fixtureReportContext(array $case): bool {
        $tool = new AgentToolRegistry(ownerQuestion:(string)($case['question']??''),
            previousReport:(array)($case['previous_report']??[]));
        $scope = $tool->followUpContext();
        return is_array($scope) && ($scope['from']??null) === ($case['expected_from']??null)
            && ($scope['to']??null) === ($case['expected_to']??null);
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
        $counts = ['confirmed_resolved'=>0,'needs_followup'=>0,'escalated'=>0,'awaiting_feedback'=>0,'unknown'=>0];
        foreach ($recent as $row) {
            if (($row['surface']??'')!=='support') continue;
            $status = (string)($row['resolution_status']??'unknown');
            if (!array_key_exists($status,$counts)) $status='unknown';
            $counts[$status]++;
        }
        $total=array_sum($counts);
        return ['sample_size'=>$total,'counts'=>$counts,
            'confirmed_percent'=>$total > 0 ? round(100*$counts['confirmed_resolved']/$total,1) : null,
            'denominator'=>'recent_40_conversations'];
    }

}
