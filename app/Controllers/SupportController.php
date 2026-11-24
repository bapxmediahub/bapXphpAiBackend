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
        $this->isApiRequest = true;
        $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
        $requestId = is_string($_POST['request_id'] ?? null) ? $_POST['request_id'] : '';
        if ($message === '' || mb_strlen($message)>2000 || ($requestId!=='' && !preg_match('/^[a-f0-9]{32}$/D',$requestId))) {
            $this->jsonResponse(['error'=>'Enter a question of up to 2,000 characters with a valid request ID.'],400);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = new \App\Services\RateLimiter();
        if (!$limiter->check('support:' . $ip, 10, 60)) {
            $this->jsonResponse(['error' => 'Too many requests. Please try again later.'], 429);
        }
        $limiter->hit('support:' . $ip);
        $startedAt = microtime(true);
        $user = (new AuthService())->user();
        // Protect authenticated support reads and conversation state with CSRF.
        if ($user !== null) $this->validateCsrf();
        $actor = \App\Services\AgentConversationService::actor('support', $user);
        $conversationId = is_string($_POST['conversation_id'] ?? null) && $_POST['conversation_id']!=='' ? $_POST['conversation_id'] : null;
        session_write_close();
        $traceId = $requestId === '' ? bin2hex(random_bytes(8))
            : substr(hash('sha256','support:'.$actor['type'].':'.$actor['id'].':'.$requestId),0,16);
        header('X-Agent-Trace: ' . $traceId);
        try {
            $conversations = new \App\Services\AgentConversationService();
            $prepared = $conversations->prepareTurn('support',$actor,$conversationId,$message,$traceId);
            $conversationId = $prepared['conversation_id'];
            if (is_array($prepared['replay'])) {
                $turn=$prepared['replay'];
                $this->jsonResponse(['reply'=>$turn['answer'],'ticket_id'=>$turn['ticket_id']??null,
                    'conversation_id'=>$conversationId,'turn_id'=>$turn['turn_id'],
                    'evaluation'=>$turn['evaluation'],'replayed'=>true,'run'=>['trace_id'=>$traceId]]
                    + (new \App\Services\AgentAnswerPresentationService())->responseData((string)$turn['answer'],'support')
                    + (($turn['outcome']??'')==='model'&&self::hasContactProposal((array)($turn['tools']??[])) ? ['ui'=>['kind'=>'contact_enquiry','subject'=>'general']] : []),
                    ($turn['outcome']??'')==='error'?503:200);
            }
            \App\Services\AgentTraceService::begin($traceId,
                static fn(array $trace)=>$conversations->checkpoint('support',$actor,$conversationId,$traceId,$trace));
            $history=$conversations->contextForActor($conversationId,'support',$actor);
            $handoff=['conversation_id'=>$conversationId,'trace_id'=>$traceId,'turn_id'=>$prepared['turn_id']??''];
            $answer = (new SupportBotService(traceId: $traceId))->answer($message, $user,$history,$handoff);
            $record = $conversations->record(
                'support', $actor, $conversationId, $message,
                (string)($answer['reply'] ?? ''), $traceId,
                !empty($answer['ticket_id']) ? 'escalated' : (string)($answer['run']['outcome'] ?? 'answered'),
                (array)($answer['run']['tools'] ?? []), isset($answer['ticket_id']) ? (string)$answer['ticket_id'] : null,
                null, (array)($answer['run']['attempts'] ?? []), (array)($answer['run']['retrieval'] ?? []), (array)($answer['knowledge']??[])
            );
            $failed=($answer['run']['outcome']??'')==='error';
            $references=$failed?null:(new \App\Services\PublicA2uiService())->forAgentEvidence((array)($answer['knowledge']??[]),(array)($answer['run']['tools']??[]),(string)($answer['reply']??''));
            $presentation = (new \App\Services\AgentAnswerPresentationService())->responseData(
                (string)($answer['reply']??''),'support',(array)($answer['actions']??[]),$references);
            $this->jsonResponse($answer + $presentation + $record,$failed?503:200);
        } catch (\LogicException $e) {
            if(!empty($user['email']) && is_string($conversationId) && preg_match('/^[a-f0-9]{32}$/D',$conversationId)) {
                try {
                    $receipt=(new \App\Services\SupportTicketService())->receipt($user['email'],['conversation_id'=>$conversationId,'trace_id'=>$traceId]);
                    if($receipt!==null && hash_equals((string)($receipt['request_hash']??''),hash('sha256',$message)))$this->jsonResponse(['reply'=>'Your earlier request created support ticket #'.$receipt['id'].'. Our team can review it; do not submit a duplicate.',
                        'ticket_id'=>$receipt['id'],'conversation_id'=>$conversationId,'replayed'=>true,'request_status'=>'action_recorded_conversation_incomplete'],202);
                } catch(\Throwable) { /* Keep uncertain actions blocked instead of guessing success. */ }
            }
            $this->jsonResponse(['error'=>'This request was already started. Its saved outcome needs checking before another action.',
                'conversation_id'=>$conversationId,'request_status'=>'pending_or_interrupted'],409);
        } catch (\Throwable $e) {
            (new \App\Services\AuditLogService())->agentRun('support', 'error', $startedAt, 'internal_error', traceId: $traceId);
            error_log('Support request or checkpoint failed (' . get_class($e) . ')');
            if (!empty($answer['ticket_id'])) {
                // Do not report a known-created ticket as failed and invite a duplicate.
                $this->jsonResponse(['reply'=>(string)$answer['reply'], 'ticket_id'=>$answer['ticket_id'],
                    'conversation_id'=>$conversationId,'request_status'=>'conversation_save_incomplete',
                    'warning'=>'The ticket exists, but conversation storage needs attention. Do not resubmit it.'],202);
            }
            $this->jsonResponse(['error'=>'The request could not be completed safely. Any saved unfinished action must be checked before retrying.',
                'conversation_id'=>$conversationId,'request_status'=>'incomplete'],503);
        }
    }

    private static function hasContactProposal(array $tools): bool {
        foreach($tools as $tool)if(($tool['name']??'')==='present_contact_enquiry'&&($tool['status']??'')==='complete')return true;
        return false;
    }

    /**
     * Customer-confirmed support resolution. CSRF + actor-owned conversation and
     * latest-turn checks; this changes one private JSON file, no SQL/chat table.
     */
    public function feedback(): void {
        $this->isApiRequest = true;
        $this->validateCsrf();
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $limiter = new \App\Services\RateLimiter();
        if (!$limiter->check('support-feedback:' . $ip, 20, 60)) {
            $this->jsonResponse(['error'=>'Too many feedback attempts.'], 429);
            return;
        }
        $limiter->hit('support-feedback:' . $ip);
        $actor = \App\Services\AgentConversationService::actor('support', (new AuthService())->user());
        try {
            $result = (new \App\Services\AgentConversationService())->rateSupportTurn(
                (string)($_POST['conversation_id'] ?? ''),
                $actor,
                (string)($_POST['turn_id'] ?? ''),
                (string)($_POST['rating'] ?? '')
            );
            $this->jsonResponse($result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error'=>$e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->jsonResponse(['error'=>'This support conversation is unavailable.'], 404);
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
