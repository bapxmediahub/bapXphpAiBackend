<?php
namespace App\Services;

/**
 * One private JSON file per chat. Never touch business MySQL for transcripts.
 * Every append uses a directory lock and atomic replacement of the same JSON file.
 */
final class AgentConversationService
{
    private const ID_PATTERN = '/^[a-f0-9]{32}$/D';
    private const MAX_TURNS = 200;

    public function __construct(private ?string $directory = null) {}

    public static function actor(string $surface, ?array $user): array
    {
        if ($surface === 'admin') {
            if (!$user || (($user['role'] ?? '') !== 'admin' && empty($user['is_admin']))) {
                throw new \RuntimeException('Admin access required.');
            }
            $id = (string)($user['sub'] ?? $user['id'] ?? $user['email'] ?? '');
            if ($id === '') throw new \RuntimeException('Missing authenticated admin identity.');
            return ['type' => 'admin', 'id' => $id];
        }

        if ($user && !empty($user['email'])) {
            return ['type' => 'customer', 'id' => (string)($user['sub'] ?? $user['id'] ?? strtolower($user['email']))];
        }
        // Must run while the PHP session is writable, before session_write_close().
        $_SESSION['support_guest_id'] ??= bin2hex(random_bytes(16));
        return ['type' => 'guest', 'id' => $_SESSION['support_guest_id']];
    }

    public function record(
        string $surface, array $actor, ?string $conversationId,
        string $question, string $answer, string $traceId,
        string $outcome, array $tools = [], ?string $ticketId = null,
        ?array $reportContext = null, array $attempts = [], array $retrieval = []
    ): array {
        if (!in_array($surface, ['admin', 'support'], true)) {
            throw new \InvalidArgumentException('Invalid agent surface');
        }
        $id = is_string($conversationId) && preg_match(self::ID_PATTERN, $conversationId)
            ? $conversationId : bin2hex(random_bytes(16));
        $evaluation = AgentEvaluationService::evaluate($surface, $question, $answer, $tools, $outcome);
        $turn = [
            'turn_id' => bin2hex(random_bytes(16)),
            'trace_id' => $traceId,
            'at' => gmdate('c'),
            'question' => AgentTranscriptSanitizer::scrub(mb_substr($question, 0, 2000)),
            'answer' => AgentTranscriptSanitizer::scrub(mb_substr($answer, 0, 12000)),
            'outcome' => $outcome,
            'tools' => array_values(array_map(
                static fn(array $tool): array => AgentTraceService::safeTool($tool),
                array_slice(array_filter($tools,'is_array'),0,32)
            )),
            'evaluation' => $evaluation,
            'model_attempts' => array_values(array_map(static fn(array $attempt): array => [
                'model' => substr((string)($attempt['model'] ?? ''), 0, 100),
                'http_status' => (int)($attempt['status'] ?? 0),
                'duration_ms' => max(0, (int)round((float)($attempt['total_ms'] ?? 0))),
            ], array_slice($attempts, 0, 9))),
        ];
        $turn['trace'] = AgentTraceService::build($traceId,$outcome,$tools,$attempts,$evaluation,$answer,$retrieval);
        if ($ticketId !== null) $turn['ticket_id'] = $ticketId;
        if ($reportContext !== null) $turn['report_context'] = $reportContext;

        $document = $this->append($id, $surface, $actor, $turn);
        return [
            'conversation_id' => $id,
            'turn_id' => $turn['turn_id'],
            'evaluation' => $evaluation,
            'turns' => count($document['turns']),
        ];
    }

    private function root(): string
    {
        $directory = $this->directory ?? storage_path('agent-conversations');
        if (is_link($directory)) throw new \RuntimeException('Conversation storage cannot be a symlink.');
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Private conversation storage is not writable.');
        }
        $root = realpath($directory);
        if ($root === false || !is_dir($root) || !is_writable($root)) {
            throw new \RuntimeException('Private conversation storage is unavailable.');
        }
        return $root;
    }

    private static function ownedBy(array $document, string $surface, array $actor): bool
    {
        return ($document['surface'] ?? null) === $surface
            && ($document['actor']['type'] ?? null) === ($actor['type'] ?? null)
            && ($document['actor']['id'] ?? null) === ($actor['id'] ?? null);
    }

    private static function readFile(string $filename): ?array
    {
        if (is_link($filename)) throw new \RuntimeException('Conversation file cannot be a symlink.');
        if (!is_file($filename)) return null;
        $json = @file_get_contents($filename);
        if ($json === false) throw new \RuntimeException('Conversation could not be read.');
        $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($document)) throw new \RuntimeException('Conversation JSON is invalid.');
        return $document;
    }

    private function append(string $id, string $surface, array $actor, array $turn): array
    {
        if (!preg_match(self::ID_PATTERN, $id)
            || !in_array($surface, ['admin', 'support'], true)
            || !in_array($actor['type'] ?? '', ['admin', 'customer', 'guest'], true)
            || trim((string)($actor['id'] ?? '')) === '') {
            throw new \InvalidArgumentException('Invalid conversation identity');
        }
        $root = $this->root();
        // One lock shared across appends. All chat data stays in one JSON file per session.
        $lock = @fopen($root . '/.conversation.lock', 'c');
        if ($lock === false) throw new \RuntimeException('Conversation lock could not be opened.');
        $tmp = false;
        try {
            @chmod($root . '/.conversation.lock', 0600);
            if (!flock($lock, LOCK_EX)) throw new \RuntimeException('Conversation lock unavailable.');
            $filename = $root . '/' . $id . '.json';
            $document = self::readFile($filename);
            if ($document === null) {
                $document = [
                    'schema_version' => 1,
                    'id' => $id,
                    'surface' => $surface,
                    'actor' => $actor,
                    'started_at' => gmdate('c'),
                    'turns' => [],
                    'passed_turns' => 0,
                ];
            } elseif (!self::ownedBy($document, $surface, $actor)) {
                throw new \RuntimeException('Conversation not accessible to this actor');
            }

            if (count($document['turns'] ?? []) >= self::MAX_TURNS) {
                throw new \RuntimeException('Start a new conversation');
            }
            $document['turns'][] = $turn;
            // A later customer question invalidates any previous resolution state.
            $document['resolution_status'] = isset($turn['ticket_id']) ? 'escalated' : 'awaiting_feedback';
            $document['passed_turns'] = (int)($document['passed_turns'] ?? 0)
                + (!empty($turn['evaluation']['passed']) ? 1 : 0);
            $document['updated_at'] = gmdate('c');
            self::writeAtomic($root, $filename, $document);
            return $document;
        } finally {
            if ($tmp !== false && is_file($tmp)) @unlink($tmp);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function writeAtomic(string $root, string $filename, array $document): void
    {
        $tmp = tempnam($root, '.conversation-');
        if ($tmp === false) throw new \RuntimeException('Conversation temporary file unavailable');
        try {
            @chmod($tmp, 0600);
            $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            if (@file_put_contents($tmp, $json, LOCK_EX) !== strlen($json) || !@rename($tmp, $filename)) {
                throw new \RuntimeException('Conversation JSON was not saved.');
            }
        } finally {
            if (is_file($tmp)) @unlink($tmp);
        }
    }

    /** Only explicit feedback on the last answer may confirm a resolved case. */
    public function rateSupportTurn(string $id, array $actor, string $turnId, string $rating): array
    {
        if (!preg_match(self::ID_PATTERN, $id) || !preg_match(self::ID_PATTERN, $turnId)
            || !in_array($rating, ['helpful','not_helpful'], true)) {
            throw new \InvalidArgumentException('Invalid support feedback');
        }
        $root = $this->root();
        $lock = @fopen($root . '/.conversation.lock', 'c');
        if ($lock === false) throw new \RuntimeException('Support feedback unavailable.');
        try {
            @chmod($root . '/.conversation.lock', 0600);
            if (!flock($lock, LOCK_EX)) throw new \RuntimeException('Support feedback lock unavailable.');
            $filename = $root . '/' . $id . '.json';
            $document = self::readFile($filename);
            if ($document === null || !self::ownedBy($document, 'support', $actor)) {
                throw new \RuntimeException('Conversation not accessible to this actor');
            }
            $last = count($document['turns'] ?? []) - 1;
            if ($last < 0 || ($document['turns'][$last]['turn_id'] ?? '') !== $turnId) {
                throw new \InvalidArgumentException('Only the latest support reply can be rated.');
            }
            if (isset($document['turns'][$last]['feedback'])) {
                if (($document['turns'][$last]['feedback']['rating'] ?? null) !== $rating) {
                    throw new \InvalidArgumentException('This answer was already rated.');
                }
                return ['resolution_status'=>$document['resolution_status'] ?? 'awaiting_feedback', 'recorded'=>true];
            }
            if (($document['resolution_status'] ?? '') === 'escalated') {
                throw new \InvalidArgumentException('Escalated tickets are reviewed by the support team.');
            }
            $document['turns'][$last]['feedback'] = ['rating'=>$rating,'at'=>gmdate('c'),'source'=>'customer_confirmation'];
            $document['resolution_status'] = $rating === 'helpful' ? 'confirmed_resolved' : 'needs_followup';
            $document['updated_at'] = gmdate('c');
            self::writeAtomic($root,$filename,$document);
            return ['resolution_status'=>$document['resolution_status'],'recorded'=>true];
        } finally {
            flock($lock,LOCK_UN);
            fclose($lock);
        }
    }

    public function previousReportForActor(?string $id, array $actor): ?array
    {
        if (!is_string($id) || !preg_match(self::ID_PATTERN, $id)) return null;
        return self::previousReportFromDocument($this->getForAdmin($id), $actor);
    }

    public static function previousReportFromDocument(?array $document, array $actor): ?array
    {
        if ($document === null) return null;
        if (!self::ownedBy($document, 'admin', $actor)) {
            throw new \RuntimeException('Conversation not accessible to this actor');
        }
        foreach (array_reverse($document['turns'] ?? []) as $turn) {
            $context = $turn['report_context'] ?? null;
            if (is_array($context) && $context !== []) return $context;
        }
        return null;
    }

    /** Controller route already enforces admin authentication. Never expose this folder directly. */
    public function getForAdmin(string $id): ?array
    {
        if (!preg_match(self::ID_PATTERN, $id)) return null;
        $document = self::readFile($this->root() . '/' . $id . '.json');
        return $document !== null && ($document['id'] ?? null) === $id ? $document : null;
    }

    /** Restore a conversation only for the authenticated actor who owns it. */
    public function getForActor(string $id, string $surface, array $actor): ?array {
        $document = $this->getForAdmin($id);
        if ($document === null) return null;
        if (!self::ownedBy($document, $surface, $actor)) {
            throw new \RuntimeException('Conversation not accessible to this actor');
        }
        return $document;
    }

    /** Admin chat history, not the global monitoring list. No SQL index/table. */
    public function recentForActor(string $surface, array $actor, int $limit = 40): array {
        $root = $this->root();
        $files = array_values(array_filter(glob($root . '/*.json') ?: [], static fn(string $filename): bool =>
            preg_match(self::ID_PATTERN, basename($filename, '.json')) === 1));
        usort($files, static fn(string $a, string $b): int =>
            ((int)@filemtime($b) <=> (int)@filemtime($a)) ?: strcmp($b, $a));
        $out = [];
        $limit = min(100, max(1, $limit));
        foreach ($files as $filename) {
            try {
                $document = self::readFile($filename);
            } catch (\Throwable $e) {
                error_log('Agent history skipped unreadable conversation file');
                continue;
            }
            if ($document === null || !self::ownedBy($document, $surface, $actor)) continue;
            $out[] = [
                'id' => (string)($document['id'] ?? ''),
                'updated_at' => (string)($document['updated_at'] ?? $document['started_at'] ?? ''),
                'turn_count' => count($document['turns'] ?? []),
                'preview' => mb_substr(trim((string)($document['turns'][0]['question'] ?? 'Conversation')), 0, 100),
            ];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Load at most 100 recent chat documents, not every full transcript. */
    public function recent(int $limit = 40): array
    {
        $root = $this->root();
        $files = glob($root . '/*.json') ?: [];
        $files = array_values(array_filter($files, static fn(string $path): bool =>
            preg_match(self::ID_PATTERN, basename($path, '.json')) === 1));
        usort($files, static fn(string $a, string $b): int =>
            ((int)@filemtime($b) <=> (int)@filemtime($a)) ?: strcmp($b, $a));
        $recent = [];
        foreach (array_slice($files, 0, min(100, max(1, $limit))) as $filename) {
            $document = self::readFile($filename);
            if ($document === null) continue;
            $recent[] = [
                'id' => $document['id'] ?? '',
                'surface' => $document['surface'] ?? '',
                'actor' => $document['actor'] ?? [],
                'updated_at' => $document['updated_at'] ?? $document['started_at'] ?? '',
                'turn_count' => count($document['turns'] ?? []),
                'passed_turns' => (int)($document['passed_turns'] ?? 0),
                'resolution_status' => in_array($document['resolution_status'] ?? '', [
                    'awaiting_feedback','confirmed_resolved','needs_followup','escalated'
                ], true) ? $document['resolution_status'] : 'unknown',
            ];
        }
        return $recent;
    }
}
