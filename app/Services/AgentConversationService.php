<?php
namespace App\Services;

/**
 * One JSON document per conversation, stored in a private MySQL table.
 * Individual turns are appended transactionally by DatabaseService.
 */
final class AgentConversationService {
    public function __construct(private DatabaseService $db = new DatabaseService()) {}

    public static function actor(string $surface, ?array $user): array {
        if ($surface === 'admin') {
            if (!$user || (($user['role'] ?? '') !== 'admin' && empty($user['is_admin']))) {
                throw new \RuntimeException('Admin access required.');
            }
            return ['type'=>'admin','id'=>(string)($user['id'] ?? $user['email'] ?? '')];
        }
        if ($user && !empty($user['email'])) {
            return ['type'=>'customer','id'=>(string)($user['id'] ?? strtolower($user['email']))];
        }
        // Set while PHP session is writable, before session_write_close().
        $_SESSION['support_guest_id'] ??= bin2hex(random_bytes(16));
        return ['type'=>'guest','id'=>$_SESSION['support_guest_id']];
    }

    public function record(
        string $surface, array $actor, ?string $conversationId,
        string $question, string $answer, string $traceId,
        string $outcome, array $tools = [], ?string $ticketId = null, ?array $reportContext = null, array $attempts = []
    ): array {
        if (!in_array($surface, ['admin','support'], true)) throw new \InvalidArgumentException('Invalid agent surface');
        $id = is_string($conversationId) && preg_match('/^[a-f0-9]{32}$/D', $conversationId)
            ? $conversationId : bin2hex(random_bytes(16));
        $evaluation = AgentEvaluationService::evaluate($surface, $question, $answer, $tools);
        $turn = [
            'turn_id'=>bin2hex(random_bytes(16)),
            'trace_id'=>$traceId,
            'at'=>gmdate('c'),
            'question'=>AgentTranscriptSanitizer::scrub(mb_substr($question,0,2000)),
            'answer'=>AgentTranscriptSanitizer::scrub(mb_substr($answer,0,12000)),
            'outcome'=>$outcome,
            'tools'=>array_values(array_map(static fn(array $tool): array => [
                'name'=>(string)($tool['name']??''),
                'status'=>(string)($tool['status']??''),
                'duration_ms'=>(int)($tool['duration_ms']??0),
            ],array_slice($tools,0,32))),
            'evaluation'=>$evaluation,
            'model_attempts'=>array_values(array_map(static fn(array $attempt): array=>[
                'model'=>substr((string)($attempt['model']??''),0,100),
                'http_status'=>(int)($attempt['status']??0),
                'duration_ms'=>max(0,(int)round((float)($attempt['total_ms']??0))),
            ], array_slice($attempts,0,9))),
        ];
        if ($ticketId !== null) $turn['ticket_id'] = $ticketId;
        if ($reportContext !== null) $turn['report_context'] = $reportContext;
        $document = $this->db->appendAgentConversation($id, $surface, $actor, $turn);
        $this->snapshotJson($document);
        return ['conversation_id'=>$id,'turn_id'=>$turn['turn_id'],'evaluation'=>$evaluation,'turns'=>count($document['turns'])];
    }

    /**
     * Optional one-file-per-chat JSON snapshot, only in an explicitly configured
     * private directory outside the application and public document root.
     * The MySQL transaction remains the canonical durable audit record.
     */
    private function snapshotJson(array $conversation): void {
        $path = trim((string)getenv('BAPX_AGENT_JSON_DIR'));
        if ($path === '') return; // Authorized admin can still export via the UI.
        $root = realpath($path);
        $appRoot = realpath(app_path());
        $publicRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($root === false || !is_dir($root) || !str_starts_with($root,'/')
            || ($appRoot !== false && ($root === $appRoot || str_starts_with($root,$appRoot.'/')))
            || ($publicRoot !== false && ($root === $publicRoot || str_starts_with($root,$publicRoot.'/')))) {
            error_log('Agent JSON snapshot skipped: private storage path is not safe or configured.');
            return;
        }
        $id = (string)($conversation['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) return;
        $dest = $root.'/'.$id.'.json';
        $lock = @fopen($root.'/'.$id.'.lock','c');
        if ($lock === false) {
            error_log('Agent JSON snapshot lock unavailable.');
            return;
        }
        $tmp = false;
        try {
            chmod($root.'/'.$id.'.lock',0600);
            if (!flock($lock,LOCK_EX)) return;
            // Fetch the newest committed JSON while holding the per-chat file lock.
            $conversation = $this->db->agentConversation($id) ?? $conversation;
            $tmp = tempnam($root,'.agent-');
            if ($tmp === false) throw new \RuntimeException('Could not allocate snapshot');
            chmod($tmp,0600);
            $json = json_encode($conversation,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES |
                JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            if (file_put_contents($tmp,$json,LOCK_EX) === false || !rename($tmp,$dest)) {
                throw new \RuntimeException('Could not save snapshot');
            }
        } catch (\Throwable $e) {
            error_log('Agent JSON snapshot failed: '.$e::class);
        } finally {
            if ($tmp !== false && is_file($tmp)) @unlink($tmp);
            flock($lock,LOCK_UN);
            fclose($lock);
        }
    }

    /** Follow-up context is tied to the verified actor and prior server-side lookup. */
    public function previousReportForActor(?string $id, array $actor): ?array {
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) return null;
        return self::previousReportFromDocument($this->db->agentConversation($id), $actor);
    }

    /** Pure ownership validation: testable without a database. */
    public static function previousReportFromDocument(?array $document, array $actor): ?array {
        if ($document === null) return null;
        if (($document['surface'] ?? '') !== 'admin' ||
            ($document['actor']['id'] ?? null) !== ($actor['id'] ?? null) ||
            ($document['actor']['type'] ?? null) !== ($actor['type'] ?? null)) {
            throw new \RuntimeException('Conversation not accessible to this actor');
        }
        foreach (array_reverse($document['turns'] ?? []) as $turn) {
            $context = $turn['report_context'] ?? null;
            if (is_array($context) && $context !== []) return $context;
        }
        return null;
    }

    public function getForAdmin(string $id): ?array {
        return preg_match('/^[a-f0-9]{32}$/D', $id) ? $this->db->agentConversation($id) : null;
    }

    public function recent(int $limit = 40): array {
        return $this->db->agentConversations(min(max($limit,1),100));
    }
}
