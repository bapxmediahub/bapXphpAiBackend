<?php
// Exercise the actual owner request with a synthetic provider and private temporary transcript.
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
}

namespace App\Services {
    final class SecretService {
        public function getModelConfig(): array { return ['apiKey'=>'fixture']; }
    }
    final class AiClient {
        public const MAX_TRANSFERS=32, MAX_PROVIDER_CYCLES=2, ATTEMPTS_PER_MODEL=3;
        public static function agentLimits(): array {return ['max_model_calls'=>32,'max_duration_ms'=>115000];}
        public function completeWithTools(string $prompt, AgentToolRegistry|RuntimeAgentToolRegistry $tools): string {
            return $GLOBALS['argv'][1] === 'accepted' ? 'A clear synthetic answer.' : 'Reasoning: private outline';
        }
        public function failureCategory(): ?string { return null; }
        public function transferTimings(): array { return []; }
        public static function isError(string $answer): bool { return false; }
    }
    final class AuditLogService {
        public static array $last = [];
        public function agentRun(string $surface, string $outcome, float $start, ?string $reason=null,array $attempts=[],array $tools=[],?string $traceId=null): void {
            self::$last = [$outcome, $reason];
        }
    }
}

namespace {
    function app_path(string $path = ''): string { return dirname(__DIR__, 2) . '/' . $path; }
    function storage_path(string $path): string { return $GLOBALS['directory'] . '/' . $path; }
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'App\\')) require app_path('app/' . str_replace('\\', '/', substr($class, 4)) . '.php');
    });
    $directory = sys_get_temp_dir() . '/bapx-owner-answer-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/admin/agent/ask';
    $_SESSION = ['user'=>['role'=>'admin','id'=>'fixture-owner'], 'csrf_token'=>'fixture'];
    $_POST = ['message'=>'Suggest a title for a fictional story.', '_csrf'=>'fixture'];
    ob_start();
    register_shutdown_function(static function () use ($directory): void {
        $response = json_decode(ob_get_clean(), true);
        $files = glob($directory . '/agent-conversations/*.json') ?: [];
        $turn = $files ? (json_decode(file_get_contents($files[0]), true)['turns'][0] ?? []) : [];
        echo json_encode([
            'status'=>http_response_code(), 'route'=>$response['run']['route'] ?? null,
            'error'=>isset($response['error']), 'answer'=>isset($response['answer']),
            'private_scaffold_exposed'=>str_contains(json_encode($response), 'private outline'),
            'audit'=>\App\Services\AuditLogService::$last, 'saved_outcome'=>$turn['outcome'] ?? null,
            'same_turn'=>isset($turn['turn_id']) && ($response['turn_id'] ?? null) === $turn['turn_id'],
        ]);
        foreach (glob($directory . '/agent-conversations/{*,.*}', GLOB_BRACE) ?: [] as $file) if (is_file($file)) unlink($file);
        if (is_dir($directory . '/agent-conversations')) rmdir($directory . '/agent-conversations');
        rmdir($directory);
    });
    (new \App\Controllers\AdminController())->agentAsk();
}
