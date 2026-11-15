<?php
namespace App\Services;

/** Free, deterministic checks for critical policies; never ask a paid judge model. */
final class AgentEvaluationService {
    public static function evaluate(string $surface, string $question, string $answer, array $tools = [], string $outcome = 'unknown'): array {
        $checks = [];
        $checks['response_present'] = trim($answer) !== '';
        $checks['no_internal_scaffold'] = !preg_match('/<\/?(?:think|analysis|final_answer)>|\b(?:system prompt|hidden instruction)\b/i', $answer);
        $checks['no_secret_leak'] = !preg_match('/\bsk-[A-Za-z0-9_-]{16,}|-----BEGIN (?:RSA |EC )?PRIVATE KEY-----/i', $answer);
        $checks['no_invalid_tool_names'] = count(array_filter($tools, static fn(array $t): bool =>
            !in_array($t['name'] ?? '', AgentCapabilityPolicy::tools($surface === 'admin' ? 'enterprise' : 'support'), true)
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
            'collection' => self::fixtureCollection($case),
            'permission' => AgentCapabilityPolicy::allows(
                (string)($case['agent']??''),(string)($case['tool']??''),$case['actor']??null)
                === (bool)($case['expected_allowed']??false),
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

    private static function fixtureReportContext(array $case): bool {
        $tool = new AgentToolRegistry(ownerQuestion:(string)($case['question']??''),
            previousReport:(array)($case['previous_report']??[]));
        $scope = $tool->followUpContext();
        return is_array($scope) && ($scope['from']??null) === ($case['expected_from']??null)
            && ($scope['to']??null) === ($case['expected_to']??null);
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
