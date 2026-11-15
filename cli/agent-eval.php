<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\AgentEvaluationService;
use App\Services\AgentInstructionService;

/**
 * Offline, repeatable agent evaluation. No model calls, customer data, or external
 * service. Returns non-zero for new failures and differences from baseline.
 */
$args = array_slice($argv, 1);
if (array_diff($args, ['--json', '--write']) !== []) {
    fwrite(STDERR, "Usage: php cli/agent-eval.php [--json] [--write]\n");
    exit(2);
}
$asJson = in_array('--json', $args, true);
$write = in_array('--write', $args, true);
$suite = AgentEvaluationService::goldenSuite();
$path = app_path('tests/fixtures/agent-golden-baseline.json');
$baseline = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
if (($baseline['schema_version'] ?? 0) !== 1 || !is_array($baseline['expected'] ?? null)) {
    throw new RuntimeException('Invalid baseline schema');
}
$actual = array_column($suite['results'], 'passed', 'id');
$changes = [];
foreach ($baseline['expected'] as $caseId => $expected) {
    if (!array_key_exists($caseId, $actual) || (bool)$actual[$caseId] !== (bool)$expected) {
        $changes[] = ['id'=>$caseId, 'expected'=>(bool)$expected, 'observed'=>$actual[$caseId] ?? null];
    }
}
foreach ($actual as $caseId => $passed) {
    if (!array_key_exists($caseId, $baseline['expected'])) {
        $changes[] = ['id'=>$caseId, 'expected'=>null, 'observed'=>(bool)$passed];
    }
}
$implementationFiles = [
    'app/Services/AgentInstructionService.php','app/Services/AgentCapabilityPolicy.php',
    'app/Services/AgentToolRegistry.php','app/Services/AgentContextService.php',
    'app/Services/SupportBotService.php','app/Services/AgentConversationService.php',
    'app/Services/AgentEvaluationService.php','app/Services/AgentTraceService.php',
    'app/Services/AiClient.php','app/Services/CategoryService.php','app/Services/ProductService.php',
    'app/Services/OrderService.php','app/Services/AuditLogService.php',
];
$implementation = [];
foreach ($implementationFiles as $source) $implementation[$source] = hash_file('sha256',app_path($source));
$instructionManifests = [
    'support'=>AgentInstructionService::manifestForSurface('support'),
    // Offline fixture evaluation uses an explicit test role, never a browser input.
    'admin'=>AgentInstructionService::manifestForSurface('admin',['role'=>'admin']),
];
foreach ($instructionManifests as $manifest) {
    foreach ($manifest['files'] as $file) $implementation[$file['path']] = $file['sha256'];
}
$result = [
    'schema_version'=>2,
    'implementation_sha256'=>hash('sha256',json_encode($implementation,JSON_THROW_ON_ERROR)),
    'source_sha256'=>$implementation,
    'instructions_sha256'=>hash('sha256',json_encode($instructionManifests,JSON_THROW_ON_ERROR)),
    'instruction_manifests'=>$instructionManifests,
    'created_at'=>gmdate('c'),
    'metric'=>'offline_deterministic_fixtures_only',
    'php_version'=>PHP_VERSION,
    'instructions_version'=>AgentInstructionService::VERSION,
    'baseline_sha256'=>hash_file('sha256', $path),
    'fixture_sha256'=>$suite['dataset_hash'],
    'passing'=>$suite['passing'],
    'total'=>$suite['total'],
    'regressions'=>$changes,
    'results'=>$suite['results'],
];
if ($write) {
    $dir = storage_path('agent-evals');
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) {
        throw new RuntimeException('Agent eval storage unavailable');
    }
    $file=$dir.'/eval-'.gmdate('Ymd-His').'-'.substr($suite['dataset_hash'],0,8).'.json';
    $tmp=tempnam($dir,'.eval-');
    if ($tmp===false) throw new RuntimeException('Agent eval temporary file unavailable');
    try {
        chmod($tmp,0600);
        if (file_put_contents($tmp,json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false
            || !rename($tmp,$file)) throw new RuntimeException('Cannot save local evaluation summary');
    } finally {
        if (is_file($tmp)) unlink($tmp);
    }
    $result['saved_to']='storage/agent-evals/'.basename($file);
}
if ($asJson) {
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),"\n";
} else {
    echo sprintf("Agent PHP golden checks %d/%d; changes from baseline: %d; fixture %s\n",
        $result['passing'],$result['total'],count($changes),substr($suite['dataset_hash'],0,12));
    foreach ($changes as $change) echo 'Mismatch: ',$change['id'],"\n";
    if (isset($result['saved_to'])) echo 'Saved: ',$result['saved_to'],"\n";
}
exit(($suite['failed']===0 && $changes===[]) ? 0 : 1);
