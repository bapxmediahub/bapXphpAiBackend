<?php
namespace App\Controllers;
use App\Services\{AuthService,SupportBotService};

final class SupportController extends BaseController {
    public function page(): void {
        $this->seoKey = 'support';
        $supportNav = $this->loadSupportNavigation();
        $this->render('public/support', ['supportNav' => $supportNav]);
    }

    public function ask(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = new \App\Services\RateLimiter();
        if (!$limiter->check('support:' . $ip, 10, 60)) {
            $this->jsonResponse(['error' => 'Too many requests. Please try again later.'], 429);
            return;
        }
        $limiter->hit('support:' . $ip);
        $startedAt = microtime(true);
        $user = (new AuthService())->user();
        $actor = \App\Services\AgentConversationService::actor('support', $user);
        $conversationId = is_string($_POST['conversation_id'] ?? null) ? $_POST['conversation_id'] : null;
        // The agent reads the in-memory cart snapshot and does not change the session.
        session_write_close();
        $traceId = bin2hex(random_bytes(8));
        header('X-Agent-Trace: ' . $traceId);
        try {
            $answer = (new SupportBotService(traceId: $traceId))->answer($_POST['message'] ?? '', $user);
            $record = (new \App\Services\AgentConversationService())->record(
                'support', $actor, $conversationId, (string)($_POST['message'] ?? ''),
                (string)($answer['reply'] ?? ''), $traceId, 'answered',
                [], isset($answer['ticket_id']) ? (string)$answer['ticket_id'] : null
            );
            $this->jsonResponse($answer + $record);
        } catch (\Throwable $e) {
            (new \App\Services\AuditLogService())->agentRun('support', 'error', $startedAt, 'internal_error', traceId: $traceId);
            error_log('Support request or audit persistence failed (' . get_class($e) . ')');
            $this->jsonResponse(['error' => 'Unable to complete or save the support request. Please retry.'], 503);
        }
    }

    private function loadSupportNavigation(): array {
        $file = app_path('content/support-navigation.yaml');
        if (!is_file($file)) return [];
        $yaml = @file_get_contents($file);
        if ($yaml === false || $yaml === '') return [];
        $sections = [];
        $current = null;
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^\s*-\s*section:\s*(.+)$/', $line, $m)) {
                if ($current) $sections[] = $current;
                $current = ['section' => trim($m[1]), 'links' => []];
            } elseif ($current && preg_match('/^\s+-\s+(\S+):\s*(.+)$/', $line, $m)) {
                $current['links'][] = ['path' => trim($m[1]), 'label' => trim($m[2])];
            }
        }
        if ($current) $sections[] = $current;
        return $sections;
    }
}
