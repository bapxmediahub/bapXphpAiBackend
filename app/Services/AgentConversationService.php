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
        string $outcome, array $tools = [], ?string $ticketId = null
    ): array {
        if (!in_array($surface, ['admin','support'], true)) throw new \InvalidArgumentException('Invalid agent surface');
        $id = is_string($conversationId) && preg_match('/^[a-f0-9]{32}$/D', $conversationId)
            ? $conversationId : bin2hex(random_bytes(16));
        $evaluation = AgentEvaluationService::evaluate($surface, $question, $answer, $tools);
        $turn = [
            'turn_id'=>bin2hex(random_bytes(16)),
            'trace_id'=>$traceId,
            'at'=>gmdate('c'),
            'question'=>mb_substr($question,0,2000),
            'answer'=>mb_substr($answer,0,12000),
            'outcome'=>$outcome,
            'tools'=>array_values(array_map(static fn(array $tool): array => [
                'name'=>(string)($tool['name']??''),
                'status'=>(string)($tool['status']??''),
                'duration_ms'=>(int)($tool['duration_ms']??0),
            ],array_slice($tools,0,32))),
            'evaluation'=>$evaluation,
        ];
        if ($ticketId !== null) $turn['ticket_id'] = $ticketId;
        $document = $this->db->appendAgentConversation($id, $surface, $actor, $turn);
        return ['conversation_id'=>$id,'turn_id'=>$turn['turn_id'],'evaluation'=>$evaluation,'turns'=>count($document['turns'])];
    }

    public function getForAdmin(string $id): ?array {
        return preg_match('/^[a-f0-9]{32}$/D', $id) ? $this->db->agentConversation($id) : null;
    }

    public function recent(int $limit = 40): array {
        return $this->db->agentConversations(min(max($limit,1),100));
    }
}
