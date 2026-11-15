<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
use App\Services\AgentConversationService;

// Pure filesystem integration test; it never opens MySQL or the /remotedb bridge.
$root = $argv[2] ?? sys_get_temp_dir() . '/sps-chat-fixture-' . bin2hex(random_bytes(8));
$surface = 'support';
$owner = ['type' => 'guest', 'id' => 'fixture-owner'];
$id = $argv[3] ?? bin2hex(random_bytes(16));

if (($argv[1] ?? '') === 'worker') {
    $service = new AgentConversationService($root);
    $record = $service->record($surface, $owner, $id, 'Fixture question',
        'Fixture answer', bin2hex(random_bytes(8)), 'local');
    echo json_encode($record, JSON_THROW_ON_ERROR) . "\n";
    exit;
}

if (!@mkdir($root, 0700) || !is_dir($root)) {
    throw new RuntimeException('Could not create private conversation fixture');
}
try {
    $service = new AgentConversationService($root);
    $first = $service->record($surface, $owner, $id, 'Original question',
        'Original answer', bin2hex(random_bytes(8)), 'local');
    if ($first['turns'] !== 1) throw new RuntimeException('First turn not saved');

    $workers = [];
    for ($i = 0; $i < 8; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $root, $id], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']
        ], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start worker');
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    foreach ($workers as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Worker failed: ' . $stderr);
        $result = json_decode(trim($stdout), true, flags: JSON_THROW_ON_ERROR);
        if ($result['conversation_id'] !== $id) throw new RuntimeException('Wrong chat ID');
    }

    $document = $service->getForAdmin($id);
    if (count($document['turns'] ?? []) !== 9 || ($document['passed_turns'] ?? 0) !== 9) {
        throw new RuntimeException('Concurrent file writes lost a conversation turn');
    }
    $files = glob($root . '/*.json') ?: [];
    if (count($files) !== 1 || basename($files[0]) !== $id . '.json') {
        throw new RuntimeException('Expected exactly one JSON file per conversation');
    }
    if ((fileperms($files[0]) & 077) !== 0) throw new RuntimeException('JSON file permissions are too broad');

    try {
        $service->record('support', ['type' => 'guest', 'id' => 'different-owner'], $id,
            'Forbidden', 'Never stored', bin2hex(random_bytes(8)), 'local');
        throw new RuntimeException('Actor isolation not enforced');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Conversation not accessible to this actor') throw $e;
    }
    if (count($service->getForAdmin($id)['turns']) !== 9) {
        throw new RuntimeException('Unauthorized turn was persisted');
    }
    $summary = $service->recent(5);
    if (count($summary) !== 1 || ($summary[0]['turn_count'] ?? 0) !== 9
        || array_key_exists('turns', $summary[0])) {
        throw new RuntimeException('Session list should expose summary metadata, not transcript text');
    }
    echo "Nine concurrent turns preserved in one private JSON file; actor isolation and summaries passed.\n";
} finally {
    foreach (glob($root . '/*') ?: [] as $file) @unlink($file);
    @unlink($root . '/.conversation.lock');
    @rmdir($root);
}
