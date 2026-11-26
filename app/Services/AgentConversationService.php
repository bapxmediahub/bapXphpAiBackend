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
    private static array $compatibilityLocks = [];

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

    /** Fail closed on ownership/storage before provider work or a possible support-ticket side effect. */
    public function prepareTurn(string $surface, array $actor, ?string $conversationId,
        string $question, string $traceId): array
    {
        if(!preg_match('/^[a-f0-9]{16}$/D',$traceId))throw new \InvalidArgumentException('Invalid trace ID');
        if($conversationId!==null && !preg_match(self::ID_PATTERN,$conversationId))throw new \InvalidArgumentException('Invalid conversation ID');
        $id=$conversationId??bin2hex(random_bytes(16));
        return $this->mutateCheckpoint($id,$surface,$actor,true,function(array &$document) use($question,$traceId,$id):array {
            $hash=hash('sha256',$question);
            foreach($document['turns']??[] as $turn) {
                if(($turn['trace_id']??'')!==$traceId)continue;
                if(isset($turn['request_hash'])&&!hash_equals($turn['request_hash'],$hash))throw new \InvalidArgumentException('Request identity reused for another question');
                return ['conversation_id'=>$id,'replay'=>$turn];
            }
            if(isset($document['pending_turns'][$traceId]))throw new \LogicException('Request is running or its outcome is uncertain; do not replay its actions.');
            if(count($document['turns']??[])+count($document['pending_turns']??[])>=self::MAX_TURNS)throw new \RuntimeException('Start a new conversation');
            if(count($document['pending_turns']??[])>=4)throw new \LogicException('Review unfinished requests before continuing this conversation.');
            $document['pending_turns'][$traceId]=['turn_id'=>bin2hex(random_bytes(16)),
                'trace_id'=>$traceId,'started_at'=>gmdate('c'),'status'=>'running',
                'request_hash'=>$hash,'question'=>AgentTranscriptSanitizer::scrub(mb_substr($question,0,2000))];
            $document['updated_at']=gmdate('c');
            return ['conversation_id'=>$id,'turn_id'=>$document['pending_turns'][$traceId]['turn_id'],'replay'=>null];
        });
    }

    /** Persist bounded observed evidence inside the SAME conversation before advancing execution. */
    public function checkpoint(string $surface,array $actor,string $id,string $traceId,array $trace): void
    {
        if(($trace['trace_id']??'')!==$traceId || count($trace['spans']??[])>128)throw new \InvalidArgumentException('Invalid execution checkpoint');
        $this->mutateCheckpoint($id,$surface,$actor,false,function(array &$document) use($traceId,$trace):array {
            if(!isset($document['pending_turns'][$traceId]))throw new \RuntimeException('Request checkpoint not found');
            $document['pending_turns'][$traceId]['trace']=$trace;
            $document['pending_turns'][$traceId]['updated_at']=gmdate('c');
            $document['updated_at']=gmdate('c');
            return [];
        });
    }

    private function mutateCheckpoint(string $id,string $surface,array $actor,bool $create,callable $mutate): array
    {
        if(!preg_match(self::ID_PATTERN,$id)||!in_array($surface,['admin','support'],true)
            ||!in_array($actor['type']??'',['admin','customer','guest'],true)||trim((string)($actor['id']??''))==='') {
            throw new \InvalidArgumentException('Invalid conversation identity');
        }
        $root=$this->root();$lock=@fopen($root.'/.conversation-'.$id.'.lock','c');
        if($lock===false)throw new \RuntimeException('Conversation checkpoint lock unavailable');
        try {
            @chmod($root.'/.conversation-'.$id.'.lock',0600);
            if(!self::acquireLock($lock))throw new \RuntimeException('Conversation checkpoint lock unavailable');
            $file=$root.'/'.$id.'.json';$document=self::readFile($file);
            if($document===null) {
                if(!$create)throw new \RuntimeException('Conversation not found');
                $document=['schema_version'=>2,'id'=>$id,'surface'=>$surface,'actor'=>$actor,
                    'started_at'=>gmdate('c'),'turns'=>[],'passed_turns'=>0,'pending_turns'=>[]];
            }elseif(!self::ownedBy($document,$surface,$actor))throw new \RuntimeException('Conversation not accessible to this actor');
            $result=$mutate($document);self::writeAtomic($root,$file,$document);return $result;
        }finally{self::releaseLock($lock);}
    }

    public function record(
        string $surface, array $actor, ?string $conversationId,
        string $question, string $answer, string $traceId,
        string $outcome, array $tools = [], ?string $ticketId = null,
        ?array $reportContext = null, array $attempts = [], array $retrieval = [], array $knowledge = [],
        bool $reportContextChanged = false
    ): array {
        if (!in_array($surface, ['admin', 'support'], true)) {
            throw new \InvalidArgumentException('Invalid agent surface');
        }
        $id = is_string($conversationId) && preg_match(self::ID_PATTERN, $conversationId)
            ? $conversationId : bin2hex(random_bytes(16));
        $evaluationSpan = AgentTraceService::activeTraceId() === $traceId ? AgentTraceService::startSpan('evaluation','deterministic_guardrails') : null;
        $retrieval = array_map(static fn(array $entry):array=>AgentTraceService::safeRetrieval($entry),array_slice(array_filter($retrieval,'is_array'),0,8));
        $evaluation = AgentEvaluationService::evaluate($surface, $question, $answer, $tools, $outcome, $retrieval);
        AgentTraceService::endSpan($evaluationSpan, !empty($evaluation['passed']) ? 'pass' : 'needs_review',
            ['version'=>(int)$evaluation['version']] + ($evaluation['observations'] ?? []));
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
            'retrieval' => $retrieval,
            'model_attempts' => array_values(array_map(static fn(array $attempt): array => [
                'model' => substr((string)($attempt['model'] ?? ''), 0, 100),
                'http_status' => (int)($attempt['status'] ?? 0),
                'duration_ms' => max(0, (int)round((float)($attempt['total_ms'] ?? 0))),
            ] + AgentTraceService::attemptTimings($attempt), array_slice($attempts, 0, AiClient::MAX_TRANSFERS))),
        ];
        $turn['trace'] = AgentTraceService::build($traceId,$outcome,$tools,$attempts,$evaluation,$answer,$retrieval);
        if ($ticketId !== null) $turn['ticket_id'] = $ticketId;
        if ($reportContext !== null) $turn['report_context'] = $reportContext;
        if ($surface === 'admin' && $reportContextChanged && $reportContext === null) {
            $turn['report_context_ambiguous'] = true;
        }
        $turn['knowledge']=['public_product_slugs'=>self::safeSlugs($knowledge['public_product_slugs']??[])];

        $document = $this->append($id, $surface, $actor, $turn);
        return [
            'conversation_id' => $id,
            'turn_id' => $document['turns'][array_key_last($document['turns'])]['turn_id'],
            'evaluation' => $evaluation,
            'turns' => count($document['turns']),
        ];
    }

    /** Bounded waiting prevents a stalled writer from blocking every request indefinitely. */
    private static function acquireLock($lock): bool {
        $root=dirname((string)(stream_get_meta_data($lock)['uri']??''));
        $path=$root.'/.conversation.lock';
        if(is_link($path))return false;
        $legacy=@fopen($path,'c');if($legacy===false)return false;@chmod($path,0600);
        $deadline=hrtime(true)+2_000_000_000;
        do {
            // Shared compatibility barrier permits concurrent new chats but excludes
            // an old deployment still holding the former global exclusive lock.
            if(flock($legacy,LOCK_SH|LOCK_NB)) {
                if(flock($lock,LOCK_EX|LOCK_NB)){self::$compatibilityLocks[(int)$lock]=$legacy;return true;}
                flock($legacy,LOCK_UN);
            }
            usleep(10000);
        }while(hrtime(true)<$deadline);
        fclose($legacy);return false;
    }
    private static function releaseLock($lock): void {
        $key=(int)$lock;flock($lock,LOCK_UN);fclose($lock);
        if(isset(self::$compatibilityLocks[$key])){
            $legacy=self::$compatibilityLocks[$key];flock($legacy,LOCK_UN);fclose($legacy);unset(self::$compatibilityLocks[$key]);
        }
    }

    /** Explicit owner deletion only; unfinished side effects require recovery, not erasure. */
    public function deleteForActor(string $id,string $surface,array $actor): bool {
        if(!preg_match(self::ID_PATTERN,$id))throw new \InvalidArgumentException('Invalid conversation ID');
        $root=$this->root();$lock=@fopen($root.'/.conversation-'.$id.'.lock','c');
        if($lock===false)throw new \RuntimeException('Conversation lock unavailable');
        try {
            chmod($root.'/.conversation-'.$id.'.lock',0600);
            if(!self::acquireLock($lock))throw new \RuntimeException('Conversation is busy');
            $file=$root.'/'.$id.'.json';$doc=self::readFile($file);
            if($doc===null)return false;
            if(!self::ownedBy($doc,$surface,$actor))throw new \RuntimeException('Conversation not accessible to this actor');
            if(!empty($doc['pending_turns']))throw new \RuntimeException('Resolve unfinished requests before deleting this conversation.');
            if(!@unlink($file))throw new \RuntimeException('Conversation could not be deleted');
            return true;
        }finally{self::releaseLock($lock);}
    }

    /** Cursor over filesystem metadata; at most 200 JSON documents parsed per page. */
    public function historyPage(string $surface,array $actor,int $limit=40,?string $cursor=null): array {
        $limit=min(100,max(1,$limit));$root=$this->root();$boundary=null;
        if($cursor!==null&&$cursor!=='') {
            $decoded=base64_decode(strtr($cursor,'-_','+/'),true);
            $boundary=is_string($decoded)?json_decode($decoded,true):null;
            if(!is_array($boundary)||count($boundary)!==2||!is_int($boundary[0])
                ||!is_string($boundary[1])||!preg_match(self::ID_PATTERN,$boundary[1]))throw new \InvalidArgumentException('Invalid history cursor');
        }
        $heap=new \SplMinHeap();
        foreach(new \DirectoryIterator($root) as $file){
            if(!$file->isFile()||$file->isLink()||$file->getExtension()!=='json')continue;
            $id=$file->getBasename('.json');if(!preg_match(self::ID_PATTERN,$id))continue;
            $key=[$file->getMTime(),$id];if($boundary!==null&&$key>=$boundary)continue;
            $heap->insert([$key[0],$id,$file->getPathname()]);
            if($heap->count()>201)$heap->extract();
        }
        $candidates=[];while(!$heap->isEmpty())$candidates[]=$heap->extract();$candidates=array_reverse($candidates);
        $hasMore=count($candidates)>200;$candidates=array_slice($candidates,0,200);
        $out=[];$last=null;$scanned=0;$skipped=0;
        foreach($candidates as $index=>$entry){
            $last=[$entry[0],$entry[1]];$scanned++;
            try{$doc=self::readFile($entry[2]);}catch(\Throwable){$skipped++;continue;}
            if($doc===null||!self::ownedBy($doc,$surface,$actor)||($doc['id']??'')!==$entry[1])continue;
            $out[]=['id'=>$doc['id'],'updated_at'=>$doc['updated_at']??$doc['started_at']??'',
                'turn_count'=>count($doc['turns']??[]),'pending_turn_count'=>count($doc['pending_turns']??[]),
                'preview'=>mb_substr(trim((string)($doc['turns'][0]['question']??'Conversation')),0,100)];
            if(count($out)>=$limit){$hasMore=$hasMore||$index<count($candidates)-1;break;}
        }
        return ['conversations'=>$out,'next_cursor'=>$hasMore&&$last!==null?rtrim(strtr(base64_encode(json_encode($last,JSON_THROW_ON_ERROR)),'+/','-_'),'='):null,
            'scanned_documents'=>$scanned,'skipped_unreadable'=>$skipped,'partial'=>$hasMore||$skipped>0];
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
        $lock = @fopen($root . '/.conversation-' . $id . '.lock', 'c');
        if ($lock === false) throw new \RuntimeException('Conversation lock could not be opened.');
        $tmp = false;
        try {
            @chmod($root . '/.conversation-' . $id . '.lock', 0600);
            if (!self::acquireLock($lock)) throw new \RuntimeException('Conversation lock unavailable.');
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
            if (isset($document['pending_turns'][$turn['trace_id']])) {
                $pending=$document['pending_turns'][$turn['trace_id']];
                $turn['turn_id']=$pending['turn_id'];
                $turn['request_hash']=$pending['request_hash'];
                $turn['started_at']=$pending['started_at'];
                unset($document['pending_turns'][$turn['trace_id']]);
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
            self::releaseLock($lock);
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
        $lock = @fopen($root . '/.conversation-' . $id . '.lock', 'c');
        if ($lock === false) throw new \RuntimeException('Support feedback unavailable.');
        try {
            @chmod($root . '/.conversation-' . $id . '.lock', 0600);
            if (!self::acquireLock($lock)) throw new \RuntimeException('Support feedback lock unavailable.');
            $filename = $root . '/' . $id . '.json';
            $document = self::readFile($filename);
            if ($document === null || !self::ownedBy($document, 'support', $actor)) {
                throw new \RuntimeException('Conversation not accessible to this actor');
            }
            if (!empty($document['pending_turns'])) {
                throw new \InvalidArgumentException('Wait for the current reply before rating this conversation.');
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
            self::releaseLock($lock);
        }
    }

    private static function safeSlugs(mixed $slugs): array {
        if(!is_array($slugs))return [];
        return array_slice(array_values(array_unique(array_filter($slugs,static fn($s):bool=>is_string($s)
            &&preg_match('/^[\p{L}\p{M}\p{N} _.-]{1,160}$/uD',$s)===1))),0,20);
    }
    public function contextForActor(?string $id, string $surface, array $actor, int $maxTurns = 4, int $maxBytes = 8000): array
    {
        $document = $id === null ? null : $this->getForActor($id, $surface, $actor);
        $maxTurns = max(1, min(8, $maxTurns));
        $maxBytes = max(512, min(16000, $maxBytes));
        $messages = [];
        $productSlugs = [];
        foreach (array_reverse($document['turns'] ?? []) as $turn) {
            // Only accepted model replies become prior assistant dialogue.
            if (($turn['outcome'] ?? '') !== 'model') continue;
            $pair = [
                'question' => mb_strcut((string)($turn['question'] ?? ''), 0, 800, 'UTF-8'),
                'answer' => mb_strcut((string)($turn['answer'] ?? ''), 0, 2000, 'UTF-8'),
            ];
            if (strlen(json_encode([$pair, ...$messages], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) > $maxBytes) break;
            array_unshift($messages, $pair);
            if ($productSlugs === []) {
                $productSlugs = self::safeSlugs($turn['knowledge']['public_product_slugs'] ?? []);
            }
            if (count($messages) >= $maxTurns) break;
        }
        return ['messages' => $messages, 'public_product_slugs' => $productSlugs];
    }

    public function previousReportForActor(?string $id, array $actor): ?array
    {
        return $this->previousReportStateForActor($id, $actor)['context'];
    }

    public function previousReportStateForActor(?string $id, array $actor): array
    {
        if (!is_string($id) || !preg_match(self::ID_PATTERN, $id)) return ['context'=>null,'ambiguous'=>false];
        return self::previousReportStateFromDocument($this->getForAdmin($id), $actor);
    }

    public static function previousReportFromDocument(?array $document, array $actor): ?array
    {
        return self::previousReportStateFromDocument($document, $actor)['context'];
    }

    public static function previousReportStateFromDocument(?array $document, array $actor): array
    {
        if ($document === null) return ['context'=>null,'ambiguous'=>false];
        if (!self::ownedBy($document, 'admin', $actor)) {
            throw new \RuntimeException('Conversation not accessible to this actor');
        }
        foreach (array_reverse($document['turns'] ?? []) as $turn) {
            if (($turn['outcome'] ?? '') !== 'model') continue;
            if (($turn['report_context_ambiguous'] ?? false) === true) {
                return ['context'=>null,'ambiguous'=>true];
            }
            $context = $turn['report_context'] ?? null;
            if (is_array($context) && $context !== []) return ['context'=>$context,'ambiguous'=>false];
            // Older multi-scope turns omitted the context. A completed dated
            // report without a saved unique scope must not resurrect a stale one.
            foreach ($turn['tools'] ?? [] as $tool) {
                if (($tool['status'] ?? '') !== 'complete'
                    || !in_array($tool['name'] ?? '', ['support_enquiries','gst_register_summary','sales_summary','commerce_report','order_count'], true)) continue;
                $filters = $tool['evidence']['filters'] ?? [];
                if (is_string($filters['from'] ?? null) && is_string($filters['to'] ?? null)) {
                    return ['context'=>null,'ambiguous'=>true];
                }
            }
        }
        return ['context'=>null,'ambiguous'=>false];
    }

    /** Controller route already enforces admin authentication. Never expose this folder directly. */
    public function getForAdmin(string $id): ?array
    {
        if (!preg_match(self::ID_PATTERN, $id)) return null;
        $document = self::readFile($this->root() . '/' . $id . '.json');
        return $document !== null && ($document['id'] ?? null) === $id ? $document : null;
    }

    /** Restore an actor-owned conversation. History routes may hide foreign IDs as missing. */
    public function getForActor(string $id, string $surface, array $actor, bool $hideForeign = false): ?array {
        $document = $this->getForAdmin($id);
        if ($document === null) return null;
        if (!self::ownedBy($document, $surface, $actor)) {
            if ($hideForeign) return null;
            throw new \RuntimeException('Conversation not accessible to this actor');
        }
        return $document;
    }

    /** Admin chat history, not the global monitoring list. No SQL index/table. */
    public function recentForActor(string $surface, array $actor, int $limit = 40): array {
        return $this->historyPage($surface,$actor,$limit)['conversations'];
    }

    public const REVIEW_CRITERIA = [
        'accuracy'=>'Factual accuracy', 'source_support'=>'Support from recorded sources',
        'task_completion'=>'Answers the actual request', 'policy_compliance'=>'Policy and privacy boundaries',
    ];
    public const REVIEW_REASONS = [
        'incorrect_fact'=>'Incorrect fact or total', 'wrong_scope'=>'Wrong person, product or date scope',
        'unsupported_claim'=>'Claim not supported by evidence', 'wrong_tool'=>'Wrong or missing tool',
        'policy_or_privacy'=>'Policy or privacy problem', 'missing_information'=>'Missing information',
        'poor_clarity'=>'Unclear or unhelpful response', 'other'=>'Other recorded reason',
    ];

    private static function requireReviewer(array $user): array {
        if(($user['role']??null)!=='admin'&&($user['is_admin']??null)!==true)throw new \RuntimeException('Admin review access required.');
        $identity=(string)($user['sub']??$user['id']??$user['email']??'');
        if($identity===''||strlen($identity)>200||str_contains($identity,"\0"))throw new \RuntimeException('Authenticated reviewer identity is missing.');
        return ['type'=>'admin','id'=>$identity];
    }

    /** Reviews/feedback never change the answer/evidence fingerprint they evaluate. */
    public static function evidenceFingerprint(array $turn): string {
        $evidence=array_intersect_key($turn,array_flip(['turn_id','trace_id','at','question','answer','outcome','tools','retrieval','evaluation','trace']));
        return hash('sha256',json_encode($evidence,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private static function evidenceOptions(array $turn): array {
        $options=['question'=>'Recorded request','response'=>'Recorded response'];
        foreach(array_slice((array)($turn['retrieval']??[]),0,8) as $i=>$r)$options['retrieval:'.$i]='Retrieval '.($i+1).' · '.(string)($r['intent']??'unclassified');
        foreach(array_slice((array)($turn['tools']??[]),0,32) as $i=>$tool)$options['tool:'.$i]='Tool '.($i+1).' · '.(string)($tool['name']??'unknown');
        if(!empty($turn['trace']['instruction_manifest']))$options['instructions']='Recorded runtime instruction manifest';
        return $options;
    }

    /** Permission-checked selected-turn view; not a model tool or unauthenticated file endpoint. */
    public function reviewDetail(string $id,?string $turnId,array $reviewer): ?array {
        self::requireReviewer($reviewer);
        if(!preg_match(self::ID_PATTERN,$id)||($turnId!==null&&!preg_match(self::ID_PATTERN,$turnId)))throw new \InvalidArgumentException('Invalid conversation or turn identity.');
        $document=$this->getForAdmin($id);if($document===null||empty($document['turns']))return null;
        $turnId??=(string)$document['turns'][array_key_last($document['turns'])]['turn_id'];
        foreach($document['turns'] as $turn){
            if(($turn['turn_id']??'')!==$turnId)continue;
            $choices=array_map(static fn(array $t):array=>['turn_id'=>(string)$t['turn_id'],'at'=>(string)($t['at']??''),'preview'=>mb_substr((string)($t['question']??''),0,90)],$document['turns']);
            return ['conversation_id'=>$id,'surface'=>(string)$document['surface'],'actor_type'=>(string)($document['actor']['type']??''),
                'resolution_status'=>(string)($document['resolution_status']??'unknown'),'pending_turns'=>count($document['pending_turns']??[]),
                'turn'=>$turn,'turn_choices'=>$choices,'evidence_sha256'=>self::evidenceFingerprint($turn),
                'review_revision'=>count($turn['reviews']??[]),'evidence_options'=>self::evidenceOptions($turn)];
        }
        return null;
    }

    /** Append a structured staff decision while preserving response, customer feedback and original evaluation. */
    public function reviewTurn(string $id,string $turnId,array $reviewer,int $expectedRevision,string $expectedEvidence,array $input): array {
        $actor=self::requireReviewer($reviewer);
        if(!preg_match(self::ID_PATTERN,$id)||!preg_match(self::ID_PATTERN,$turnId)||$expectedRevision<0
            ||!preg_match('/^[a-f0-9]{64}$/D',$expectedEvidence))throw new \InvalidArgumentException('Invalid answer-review identity.');
        $root=$this->root();$path=$root.'/.conversation-'.$id.'.lock';
        if(is_link($path))throw new \RuntimeException('Review lock cannot be a symlink.');
        $lock=@fopen($path,'c');if($lock===false)throw new \RuntimeException('Review storage is unavailable.');
        try {
            @chmod($path,0600);if(!self::acquireLock($lock))throw new \RuntimeException('Review lock is unavailable.');
            $file=$root.'/'.$id.'.json';$document=self::readFile($file);
            if($document===null||($document['id']??'')!==$id)throw new \InvalidArgumentException('Conversation not found.');
            foreach($document['turns']??[] as $i=>$turn){
                if(($turn['turn_id']??'')!==$turnId)continue;
                $reviews=$turn['reviews']??[];
                if(!is_array($reviews)||count($reviews)!==$expectedRevision||!hash_equals(self::evidenceFingerprint($turn),$expectedEvidence)) {
                    throw new \LogicException('The answer or review changed. Reload before saving.');
                }
                if(count($reviews)>=20)throw new \LogicException('This turn has reached its review-history limit.');
                $decision=self::normalizeReview($input,self::evidenceOptions($turn));
                $review=['schema_version'=>1,'id'=>bin2hex(random_bytes(16)),'revision'=>$expectedRevision+1,'at'=>gmdate('c'),
                    'reviewer'=>$actor,'evidence_sha256'=>$expectedEvidence]+$decision;
                $document['turns'][$i]['reviews'][]=$review;
                $document['reviewed_at']=$review['at'];
                // Reviewing an old answer is not new customer traffic. Preserve list ordering.
                $activityMtime=@filemtime($file);self::writeAtomic($root,$file,$document);
                if($activityMtime!==false)@touch($file,$activityMtime);
                return $review;
            }
            throw new \InvalidArgumentException('Completed answer not found.');
        }finally{self::releaseLock($lock);}
    }

    private static function normalizeReview(array $input,array $options): array {
        $rubric=$input['rubric']??null;
        if(!is_array($rubric)||array_diff(array_keys($rubric),array_keys(self::REVIEW_CRITERIA))!==[]||count($rubric)!==count(self::REVIEW_CRITERIA))throw new \InvalidArgumentException('Assess each review criterion explicitly.');
        $ordered=[];foreach(self::REVIEW_CRITERIA as $key=>$label){
            $value=$rubric[$key]??null;if(!in_array($value,['pass','fail','not_assessed'],true))throw new \InvalidArgumentException('Invalid review assessment.');$ordered[$key]=$value;
        }
        $reasons=$input['reasons']??[];$refs=$input['evidence_refs']??[];
        foreach([$reasons,$refs] as $values)if(!is_array($values)||count($values)>40||array_filter($values,static fn($v):bool=>!is_string($v))!==[])throw new \InvalidArgumentException('Invalid review evidence selection.');
        $reasons=array_values(array_unique($reasons));$refs=array_values(array_unique($refs));
        if(count($reasons)>8||array_diff($reasons,array_keys(self::REVIEW_REASONS))!==[]||$refs===[]||array_diff($refs,array_keys($options))!==[])throw new \InvalidArgumentException('Select only recorded evidence and supported reason codes.');
        $hasSource=count(array_filter($refs,static fn(string $r):bool=>str_starts_with($r,'tool:')||str_starts_with($r,'retrieval:')))>0;
        if(($ordered['accuracy']==='pass'||$ordered['source_support']==='pass')&&!$hasSource)throw new \InvalidArgumentException('A factual/source pass needs recorded tool or retrieval evidence.');
        $mode=$input['authoring_mode']??null;
        if(!in_array($mode,['human','agent_assisted','verification_test'],true))throw new \InvalidArgumentException('Declare how this review was authored.');
        if($mode==='human'&&($input['human_attestation']??null)!=='yes')throw new \InvalidArgumentException('Confirm that you personally checked this answer against its sources.');
        $note=$input['note']??null;
        if(!is_string($note)||mb_strlen(trim($note))<15||mb_strlen($note)>1500)throw new \InvalidArgumentException('Explain the decision in 15–1,500 characters without secrets.');
        $verdict=in_array('fail',$ordered,true)?'needs_correction':(in_array('not_assessed',$ordered,true)?'insufficient_evidence':'supported');
        if($verdict!=='supported'&&$reasons===[])throw new \InvalidArgumentException('A failed or incomplete review needs a reason.');
        return ['verdict'=>$verdict,'rubric'=>$ordered,'reasons'=>$reasons,'evidence_refs'=>$refs,
            'note'=>AgentTranscriptSanitizer::scrub(trim(strip_tags($note))),'authoring_mode'=>$mode,
            'provenance'=>$mode==='human'?'staff_declared_human':'explicit_nonhuman_or_test'];
    }

    public static function qualityReviewSummary(array $document): array {
        $result=['human_reviewed'=>0,'supported'=>0,'needs_correction'=>0,'insufficient_evidence'=>0,
            'agent_assisted'=>0,'verification_tests'=>0,'unreviewed'=>0,'reasons'=>[]];
        foreach($document['turns']??[] as $turn){
            $digest=self::evidenceFingerprint($turn);$latest=[];
            foreach(array_slice((array)($turn['reviews']??[]),0,20) as $review){
                if(!is_array($review)||!hash_equals($digest,(string)($review['evidence_sha256']??'')))continue;
                $mode=$review['authoring_mode']??'';
                if(in_array($mode,['human','agent_assisted','verification_test'],true))$latest[$mode]=$review;
            }
            if(isset($latest['verification_test']))$result['verification_tests']++;
            if(isset($latest['agent_assisted']))$result['agent_assisted']++;
            $human=$latest['human']??null;
            if(!is_array($human)||!in_array($human['verdict']??'',['supported','needs_correction','insufficient_evidence'],true)){
                if($latest===[])$result['unreviewed']++;
                continue;
            }
            $result['human_reviewed']++;$result[$human['verdict']]++;
            foreach($human['reasons']??[] as $reason)if(isset(self::REVIEW_REASONS[$reason]))$result['reasons'][$reason]=($result['reasons'][$reason]??0)+1;
        }
        return $result;
    }

    /** Link the five latest failed tool runs in this chat to their recorded turns. */
    private static function failedToolRuns(array $turns): array
    {
        $runs = [];
        foreach (array_reverse($turns) as $turn) {
            $traceId = $turn['trace_id'] ?? '';
            $turnId = $turn['turn_id'] ?? '';
            if (!is_string($traceId) || !preg_match('/^[a-f0-9]{16}$/D', $traceId)
                || !is_string($turnId) || !preg_match(self::ID_PATTERN, $turnId)) continue;
            $errors = 0;
            foreach ($turn['tools'] ?? [] as $tool) {
                if (($tool['status'] ?? null) === 'error') $errors++;
            }
            if ($errors === 0) continue;
            $runs[] = ['trace_id' => $traceId, 'turn_id' => $turnId, 'errors' => $errors];
            if (count($runs) === 5) break;
        }
        return $runs;
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
            try { $document = self::readFile($filename); }
            catch (\Throwable $e) { error_log('Agent monitor skipped unreadable session'); continue; }
            if ($document === null) continue;
            $lastTurn = ($document['turns'] ?? []) === [] ? [] : $document['turns'][array_key_last($document['turns'])];
            $pending = ($document['pending_turns'] ?? []) === [] ? null : $document['pending_turns'][array_key_last($document['pending_turns'])];
            $trace = is_array($pending) ? ($pending['trace'] ?? []) : ($lastTurn['trace'] ?? []);
            $traceSteps = [];
            foreach (array_slice((array)($trace['spans'] ?? []), 0, 128) as $span) {
                if (!is_array($span)) continue;
                $traceSteps[] = array_intersect_key($span, array_flip([
                    'sequence','type','name','status','duration_ms','started_at','ended_at','http_status','result_count','curl_error','timeout_ms','first_byte_ms'
                ])) + AgentTraceService::attemptTimings($span);
            }
            $recent[] = [
                'id' => $document['id'] ?? '',
                'surface' => $document['surface'] ?? '',
                'actor' => $document['actor'] ?? [],
                'updated_at' => $document['updated_at'] ?? $document['started_at'] ?? '',
                'turn_count' => count($document['turns'] ?? []),
                'pending_turn_count' => count($document['pending_turns'] ?? []),
                'passed_turns' => (int)($document['passed_turns'] ?? 0),
                'quality_review' => self::qualityReviewSummary($document),
                'intent' => AgentTraceService::safeRetrieval((array)($lastTurn['retrieval'][0]??[]))['intent'],
                'needs_review' => is_array($pending) || ($document['resolution_status']??'')==='needs_followup' || (isset($lastTurn['evaluation']['passed'])&&!$lastTurn['evaluation']['passed']),
                'review_reason' => is_array($pending)?'unfinished_request':(($document['resolution_status']??'')==='needs_followup'?'negative_feedback':((isset($lastTurn['evaluation']['passed'])&&!$lastTurn['evaluation']['passed'])?'guardrail_failure':null)),
                'trace_state' => is_array($pending) ? 'unfinished' : 'completed',
                'trace_ordering' => (string)($trace['ordering'] ?? 'unavailable'),
                'trace_steps' => $traceSteps,
                'budget_stop' => AgentTraceService::budgetReason($trace),
                'failed_tool_runs' => self::failedToolRuns($document['turns'] ?? []),
                'resolution_status' => in_array($document['resolution_status'] ?? '', [
                    'awaiting_feedback','confirmed_resolved','needs_followup','escalated'
                ], true) ? $document['resolution_status'] : 'unknown',
            ];
        }
        return $recent;
    }
}
