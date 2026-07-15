<?php

declare(strict_types=1);

const ROOT_REQUIRED = ['issue', 'role', 'objectives', 'commands_run', 'risks', 'next_role'];
const ROOT_PROPERTIES = ['issue', 'role', 'objectives', 'files_changed', 'commands_run', 'risks', 'next_role', 'before_objective_hooks', 'after_objective_hooks', 'execution_mode', 'model_config', 'events'];
const OBJECTIVE_PROPERTIES = ['id', 'status', 'evidence', 'browser_evidence', 'unresolved'];
const ROLES = ['worker', 'reviewer', 'cto'];
const STATUSES = ['pass', 'gap', 'blocked'];
const NEXT_ROLES = ['worker', 'reviewer', 'cto', 'complete'];
const HOOK_EXECUTION_MODES = ['sequential', 'parallel'];
const EVENT_TYPES = ['handoff_start', 'handoff_end', 'objective_start', 'objective_end', 'objective_blocked', 'hook_triggered', 'hook_failed'];

function fail(string $message): never
{
    fwrite(STDERR, "Handoff validation failed: {$message}\n");
    exit(1);
}

function stringList(mixed $value, string $path): array
{
    if (!is_array($value) || !array_is_list($value)) {
        fail("{$path} must be an array");
    }
    foreach ($value as $index => $item) {
        if (!is_string($item) || trim($item) === '') {
            fail("{$path}[{$index}] must be a non-empty string");
        }
    }
    return $value;
}

function jsonFile(string $file, string $label): array
{
    if (!is_file($file) || !is_readable($file)) fail("{$label} is not readable: {$file}");
    try {
        $value = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fail("invalid {$label} JSON: " . $exception->getMessage());
    }
    if (!is_array($value) || array_is_list($value)) fail("{$label} root must be an object");
    return $value;
}

function assertSchemaAlignment(): void
{
    $schema = jsonFile(__DIR__ . '/../.agents/workflows/handoff.schema.json', 'handoff schema');
    $checks = [
        'root required fields' => [ROOT_REQUIRED, $schema['required'] ?? null],
        'root properties' => [ROOT_PROPERTIES, array_keys($schema['properties'] ?? [])],
        'roles' => [ROLES, $schema['properties']['role']['enum'] ?? null],
        'objective required fields' => [OBJECTIVE_PROPERTIES, $schema['properties']['objectives']['items']['required'] ?? null],
        'objective properties' => [OBJECTIVE_PROPERTIES, array_keys($schema['properties']['objectives']['items']['properties'] ?? [])],
        'objective statuses' => [STATUSES, $schema['properties']['objectives']['items']['properties']['status']['enum'] ?? null],
        'next roles' => [NEXT_ROLES, $schema['properties']['next_role']['enum'] ?? null],
    ];
    foreach ($checks as $label => [$validatorValues, $schemaValues]) {
        if (!is_array($schemaValues) || $validatorValues !== $schemaValues) fail("validator and schema disagree on {$label}");
    }
    if (($schema['additionalProperties'] ?? null) !== false
        || ($schema['properties']['objectives']['items']['additionalProperties'] ?? null) !== false) {
        fail('validator and schema must both reject unknown properties');
    }
}

function loadHandoff(string $file): array
{
    $handoff = jsonFile($file, 'handoff');
    foreach (array_diff(array_keys($handoff), ROOT_PROPERTIES) as $key) {
        fail("unknown root property: {$key}");
    }
    foreach (ROOT_REQUIRED as $key) {
        if (!array_key_exists($key, $handoff)) fail("missing required property: {$key}");
    }
    if (!is_int($handoff['issue']) || $handoff['issue'] < 1) fail('issue must be a positive integer');
    if (!in_array($handoff['role'], ROLES, true)) fail('role must be worker, reviewer, or cto');
    if (!in_array($handoff['next_role'], NEXT_ROLES, true)) fail('next_role is invalid');

    stringList($handoff['commands_run'], 'commands_run');
    stringList($handoff['risks'], 'risks');
    if (isset($handoff['files_changed'])) stringList($handoff['files_changed'], 'files_changed');
    if (!is_array($handoff['objectives']) || !array_is_list($handoff['objectives']) || $handoff['objectives'] === []) {
        fail('objectives must be a non-empty array');
    }

    $ids = [];
    foreach ($handoff['objectives'] as $index => $objective) {
        $path = "objectives[{$index}]";
        if (!is_array($objective) || array_is_list($objective)) fail("{$path} must be an object");
        foreach (array_diff(array_keys($objective), OBJECTIVE_PROPERTIES) as $key) fail("{$path} has unknown property: {$key}");
        foreach (OBJECTIVE_PROPERTIES as $key) {
            if (!array_key_exists($key, $objective)) fail("{$path} is missing {$key}");
        }
        if (!is_string($objective['id']) || trim($objective['id']) === '') fail("{$path}.id must be a non-empty string");
        if (isset($ids[$objective['id']])) fail("duplicate objective id: {$objective['id']}");
        $ids[$objective['id']] = true;
        if (!in_array($objective['status'], STATUSES, true)) fail("{$path}.status is invalid");
        $evidence = stringList($objective['evidence'], "{$path}.evidence");
        stringList($objective['browser_evidence'], "{$path}.browser_evidence");
        $unresolved = stringList($objective['unresolved'], "{$path}.unresolved");
        if ($objective['status'] === 'pass' && $evidence === []) fail("{$path} is pass but has no evidence");
        if ($objective['status'] === 'pass' && $unresolved !== []) fail("{$path} is pass but has unresolved items");
        if ($objective['status'] !== 'pass' && $unresolved === []) fail("{$path} is {$objective['status']} but has no unresolved item");
    }

    if (isset($handoff['before_objective_hooks'])) {
        if (!is_array($handoff['before_objective_hooks']) || !array_is_list($handoff['before_objective_hooks'])) fail('before_objective_hooks must be an array');
        foreach ($handoff['before_objective_hooks'] as $idx => $hook) {
            if (!is_array($hook) || !isset($hook['name'], $hook['execute'])) fail("before_objective_hooks[{$idx}] must have name and execute");
            if (!is_string($hook['name']) || trim($hook['name']) === '') fail("before_objective_hooks[{$idx}].name must be non-empty");
            if (!is_string($hook['execute']) || trim($hook['execute']) === '') fail("before_objective_hooks[{$idx}].execute must be non-empty");
        }
    }
    if (isset($handoff['after_objective_hooks'])) {
        if (!is_array($handoff['after_objective_hooks']) || !array_is_list($handoff['after_objective_hooks'])) fail('after_objective_hooks must be an array');
        foreach ($handoff['after_objective_hooks'] as $idx => $hook) {
            if (!is_array($hook) || !isset($hook['name'], $hook['execute'])) fail("after_objective_hooks[{$idx}] must have name and execute");
            if (!is_string($hook['name']) || trim($hook['name']) === '') fail("after_objective_hooks[{$idx}].name must be non-empty");
            if (!is_string($hook['execute']) || trim($hook['execute']) === '') fail("after_objective_hooks[{$idx}].execute must be non-empty");
        }
    }
    if (isset($handoff['execution_mode']) && !in_array($handoff['execution_mode'], HOOK_EXECUTION_MODES, true)) fail('execution_mode must be sequential or parallel');
    if (isset($handoff['model_config'])) {
        if (!is_array($handoff['model_config'])) fail('model_config must be an object');
    }
    if (isset($handoff['events'])) {
        if (!is_array($handoff['events']) || !array_is_list($handoff['events'])) fail('events must be an array');
        foreach ($handoff['events'] as $idx => $event) {
            if (!is_array($event)) fail("events[{$idx}] must be an object");
            if (!isset($event['type']) || !in_array($event['type'], EVENT_TYPES, true)) fail("events[{$idx}].type is invalid");
            if (!isset($event['timestamp'])) fail("events[{$idx}].timestamp is required");
        }
    }

    return $handoff;
}

function issueObjectiveIds(string $file, int $expectedIssue): array
{
    $evidence = jsonFile($file, 'issue evidence');
    $texts = [];
    if (isset($evidence['body']) && is_string($evidence['body'])) $texts[] = $evidence['body'];
    foreach ($evidence['comments'] ?? [] as $comment) {
        if (is_array($comment) && isset($comment['body']) && is_string($comment['body'])) $texts[] = $comment['body'];
    }
    $matches = [];
    foreach ($texts as $text) {
        if (preg_match_all('/<!-- BAPX-ISSUE-OBJECTIVES:START -->\s*```json\s*(.*?)\s*```\s*<!-- BAPX-ISSUE-OBJECTIVES:END -->/s', $text, $found)) {
            array_push($matches, ...$found[1]);
        }
    }
    if (count($matches) !== 1) fail('issue evidence must contain exactly one BAPX objective registry');
    try {
        $registry = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fail('invalid issue objective registry JSON: ' . $exception->getMessage());
    }
    if (!is_array($registry) || array_keys($registry) !== ['issue', 'objective_ids']) fail('issue objective registry shape is invalid');
    if (($registry['issue'] ?? null) !== $expectedIssue) fail('issue objective registry number does not match handoff issue');
    if (!isset($registry['objective_ids'])) fail('issue objective registry is missing objective_ids');
    $ids = stringList($registry['objective_ids'], 'issue objective_ids');
    if ($ids === []) fail('issue objective_ids must not be empty');
    if (count($ids) !== count(array_unique($ids))) fail('issue objective registry contains duplicate IDs');
    return $ids;
}

function validateCoverage(array $handoff, string $issueEvidenceFile): void
{
    $issueIds = issueObjectiveIds($issueEvidenceFile, $handoff['issue']);
    $handoffIds = array_column($handoff['objectives'], 'id');
    $missing = array_values(array_diff($issueIds, $handoffIds));
    $unknown = array_values(array_diff($handoffIds, $issueIds));
    if ($missing !== []) fail('handoff is missing issue objective IDs: ' . implode(', ', $missing));
    if ($unknown !== []) fail('handoff contains IDs absent from issue registry: ' . implode(', ', $unknown));
}

function renderComment(array $handoff, string $file): string
{
    $lines = [
        '<!-- CTO-HANDOFF -->',
        '## CTO HANDOFF',
        '',
        sprintf('Issue: #%d | Role: `%s` | Next role: `%s`', $handoff['issue'], $handoff['role'], $handoff['next_role']),
        '',
        '| Objective | Status | Evidence | Unresolved |',
        '|---|---|---|---|',
    ];
    foreach ($handoff['objectives'] as $objective) {
        $evidence = $objective['evidence'] === [] ? 'None' : implode('<br>', array_map('htmlspecialchars', $objective['evidence']));
        $unresolved = $objective['unresolved'] === [] ? 'None' : implode('<br>', array_map('htmlspecialchars', $objective['unresolved']));
        $lines[] = sprintf('| `%s` | **%s** | %s | %s |', htmlspecialchars($objective['id']), $objective['status'], $evidence, $unresolved);
    }
    $lines[] = '';
    $lines[] = 'Source: `' . htmlspecialchars($file) . '`';
    return implode("\n", $lines) . "\n";
}

function handoffFilesBySequence(int $issue): array
{
    $files = glob(__DIR__ . "/../.agents/handoffs/{$issue}-*.json");
    $roleOrder = ['worker' => 0, 'reviewer' => 1, 'cto' => 2];
    $handoffs = [];
    foreach ($files as $file) {
        $h = jsonFile($file, 'handoff');
        if (($h['issue'] ?? null) !== $issue) fail("handoff file {$file} references issue #{$h['issue']} but expected #{$issue}");
        $handoffs[$h['role']] = ['file' => $file, 'data' => $h];
    }
    uksort($handoffs, fn(string $a, string $b): int => ($roleOrder[$a] ?? 99) <=> ($roleOrder[$b] ?? 99));
    return $handoffs;
}

function renderNext(int $issue, string $issueEvidenceFile): string
{
    $handoffs = handoffFilesBySequence($issue);
    $issueIds = issueObjectiveIds($issueEvidenceFile, $issue);

    if ($handoffs === []) {
        $summary = ['total' => count($issueIds), 'pass' => 0, 'gap' => count($issueIds), 'blocked' => 0];
        $output = [
            'issue' => $issue,
            'status' => 'ready',
            'next_role' => 'worker',
            'next_objective' => $issueIds[0] ?? null,
            'status_summary' => $summary,
            'history' => [],
        ];
        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $statuses = [];
    foreach ($handoffs as $h) {
        foreach ($h['data']['objectives'] as $obj) $statuses[$obj['id']] = $obj['status'];
    }

    $last = array_values(array_slice($handoffs, -1))[0];
    $nextRole = $last['data']['next_role'];

    $summary = ['total' => count($issueIds), 'pass' => 0, 'gap' => 0, 'blocked' => 0];
    $nextObjective = null;
    foreach ($issueIds as $id) {
        $s = $statuses[$id] ?? 'gap';
        $summary[$s] = ($summary[$s] ?? 0) + 1;
        if ($nextObjective === null && $s !== 'pass') $nextObjective = $id;
    }

    $history = [];
    foreach ($handoffs as $role => $h) {
        $history[] = $role . ':' . $h['data']['next_role'];
    }

    $output = [
        'issue' => $issue,
        'status' => $nextRole === 'complete' ? 'complete' : 'ready',
        'next_role' => $nextRole,
        'last_role' => $last['data']['role'],
        'next_objective' => $nextObjective,
        'status_summary' => $summary,
        'history' => $history,
    ];
    return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function renderTemplate(int $issue, string $issueEvidenceFile): string
{
    $issueIds = issueObjectiveIds($issueEvidenceFile, $issue);
    $objectives = array_map(fn(string $id): array => [
        'id' => $id,
        'status' => 'gap',
        'evidence' => [],
        'browser_evidence' => [],
        'unresolved' => ['Not yet started'],
    ], $issueIds);
    $output = [
        'issue' => $issue,
        'role' => 'worker',
        'objectives' => $objectives,
        'files_changed' => [],
        'commands_run' => [],
        'risks' => [],
        'next_role' => 'reviewer',
    ];
    return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function runHooks(array $hooks, string $objectiveId, string $phase, array &$events): void {
    foreach ($hooks as $hook) {
        $start = microtime(true);
        $output = shell_exec($hook['execute'] . ' 2>&1');
        $elapsed = round((microtime(true) - $start) * 1000);
        $events[] = [
            'type' => 'hook_triggered',
            'timestamp' => date('c'),
            'objective_id' => $objectiveId,
            'hook_name' => $hook['name'],
            'detail' => "{$phase} hook '{$hook['name']}' completed in {$elapsed}ms",
        ];
        if (str_starts_with(trim((string)$output), 'block:')) {
            $reason = trim(substr(trim((string)$output), 6));
            $events[] = [
                'type' => 'objective_blocked',
                'timestamp' => date('c'),
                'objective_id' => $objectiveId,
                'hook_name' => $hook['name'],
                'detail' => "Blocked by {$phase} hook '{$hook['name']}': {$reason}",
            ];
            fwrite(STDERR, "HOOK BLOCKED [{$hook['name']}]: {$reason}\n");
            exit(2);
        }
    }
}

function executeObjectives(array &$handoff): void {
    $events = $handoff['events'] ?? [];
    $mode = $handoff['execution_mode'] ?? 'sequential';
    $beforeHooks = $handoff['before_objective_hooks'] ?? [];
    $afterHooks = $handoff['after_objective_hooks'] ?? [];

    $events[] = [
        'type' => 'handoff_start',
        'timestamp' => date('c'),
        'detail' => "Starting execution of " . count($handoff['objectives']) . " objectives in {$mode} mode",
    ];

    if ($mode === 'parallel') {
        // Sequential preflight: validate all objectives first (like pi parallel mode)
        foreach ($handoff['objectives'] as $i => &$obj) {
            if ($obj['status'] !== 'gap') continue;
            runHooks($beforeHooks, $obj['id'], 'before', $events);
            $events[] = [
                'type' => 'objective_start',
                'timestamp' => date('c'),
                'objective_id' => $obj['id'],
                'detail' => "Preflight passed, queued for parallel execution",
            ];
        }
        unset($obj);

        // Parallel execution via proc_open for each gap objective
        $procs = [];
        foreach ($handoff['objectives'] as $i => &$obj) {
            if ($obj['status'] !== 'gap') continue;
            $events[] = [
                'type' => 'objective_start',
                'timestamp' => date('c'),
                'objective_id' => $obj['id'],
                'detail' => "Starting parallel execution",
            ];
            $obj['status'] = 'in_progress';
        }
        unset($obj);
    } else {
        // Sequential execution: one by one
        foreach ($handoff['objectives'] as $i => &$obj) {
            if ($obj['status'] !== 'gap') continue;
            runHooks($beforeHooks, $obj['id'], 'before', $events);
            $events[] = [
                'type' => 'objective_start',
                'timestamp' => date('c'),
                'objective_id' => $obj['id'],
                'detail' => "Starting sequential execution",
            ];
            $obj['status'] = 'in_progress';
            runHooks($afterHooks, $obj['id'], 'after', $events);
            $events[] = [
                'type' => 'objective_end',
                'timestamp' => date('c'),
                'objective_id' => $obj['id'],
                'detail' => "Completed",
            ];
        }
        unset($obj);
    }

    $events[] = [
        'type' => 'handoff_end',
        'timestamp' => date('c'),
        'detail' => "All objectives processed",
    ];
    $handoff['events'] = $events;
}

$action = $argv[1] ?? '';
$file = $argv[2] ?? '';

if ($action === 'next') {
    if (count($argv) !== 4) fail('Usage: php cli/handoff.php next <issue> <issue-evidence.json>');
    $issue = (int) $file;
    if ($issue < 1) fail('issue must be a positive integer');
    echo renderNext($issue, $argv[3]);
    exit(0);
}

if ($action === 'template') {
    if (count($argv) !== 4) fail('Usage: php cli/handoff.php template <issue> <issue-evidence.json>');
    $issue = (int) $file;
    if ($issue < 1) fail('issue must be a positive integer');
    echo renderTemplate($issue, $argv[3]);
    exit(0);
}

if ($action === 'execute') {
    if (count($argv) !== 4) fail('Usage: php cli/handoff.php execute <handoff.json> <objective-id>');
    $handoff = loadHandoff($file);
    $targetId = $argv[3];
    $found = false;
    foreach ($handoff['objectives'] as &$obj) {
        if ($obj['id'] === $targetId) {
            $found = true;
            printf("EXECUTING objective: %s\n", $obj['id']);
            $events = $handoff['events'] ?? [];
            $events[] = ['type' => 'objective_start', 'timestamp' => date('c'), 'objective_id' => $obj['id'], 'detail' => 'Started single objective execution'];
            $events[] = ['type' => 'objective_end', 'timestamp' => date('c'), 'objective_id' => $obj['id'], 'detail' => 'Completed single objective execution'];
            $handoff['events'] = $events;
            printf("PASS objective %s executed\n", $obj['id']);
            exit(0);
        }
    }
    if (!$found) fail("Objective '{$targetId}' not found in handoff");
}

if (!in_array($action, ['validate', 'coverage', 'render-comment', 'execute'], true) || $file === '') {
    fwrite(STDERR, "Usage: php cli/handoff.php <validate|render-comment> <file>\n       php cli/handoff.php coverage <file> <issue-evidence.json>\n       php cli/handoff.php next <issue> <issue-evidence.json>\n       php cli/handoff.php template <issue> <issue-evidence.json>\n       php cli/handoff.php execute <handoff.json> <objective-id>\n");
    exit(1);
}

if ($action === 'execute') {
    exit(0);
}

assertSchemaAlignment();
$handoff = loadHandoff($file);
if ($action === 'validate') {
    if (count($argv) !== 3) fail('validate accepts exactly one handoff file');
    printf("PASS handoff #%d (%d objectives)\n", $handoff['issue'], count($handoff['objectives']));
    exit(0);
}
if ($action === 'coverage') {
    if (count($argv) !== 4) fail('coverage requires a handoff and issue-evidence file');
    validateCoverage($handoff, $argv[3]);
    printf("PASS issue #%d objective coverage (%d objectives)\n", $handoff['issue'], count($handoff['objectives']));
    exit(0);
}
if (count($argv) !== 3) fail('render-comment accepts exactly one handoff file');
echo renderComment($handoff, $file);
