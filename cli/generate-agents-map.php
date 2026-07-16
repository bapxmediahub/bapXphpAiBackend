<?php
declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__);
$output = $argv[2] ?? $root . '/agents.mmd';
$source = $root . '/config/agents/workflow.yaml';

if (!is_file($source)) {
    fwrite(STDERR, "Missing agent workflow source: {$source}\n");
    exit(1);
}

$config = json_decode((string)file_get_contents($source), true);
if (!is_array($config)) {
    fwrite(STDERR, "Agent workflow YAML must be JSON-compatible YAML.\n");
    exit(1);
}

$id = static fn(string $value): string => preg_replace('/[^a-z0-9_]+/', '_', strtolower($value)) ?: 'node';
$quote = static fn(string $value): string => str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

$lines = [
    'flowchart LR',
    '  classDef role fill:#fff7ed,stroke:#9f1239,color:#4c0519',
    '  classDef event fill:#e0f2fe,stroke:#0369a1,color:#0c4a6e',
    '  classDef tool fill:#ecfdf5,stroke:#047857,color:#064e3b',
    '  classDef gate fill:#fef3c7,stroke:#b45309,color:#78350f',
    '',
    '  subgraph EVENTS["GitHub and deployment events"]',
];

foreach (($config['events'] ?? []) as $key => $event) {
    $lines[] = sprintf('    %s["%s"]:::event', $id((string)$key), $quote((string)($event['label'] ?? $key)));
}
$lines[] = '  end';
$lines[] = '';
$lines[] = '  subgraph ROLES["Sequential agent roles"]';
foreach (($config['roles'] ?? []) as $key => $role) {
    $label = (string)($role['label'] ?? $key);
    $contract = (string)($role['contract'] ?? '');
    $lines[] = sprintf('    %s["%s<br/><small>%s</small>"]:::role', $id((string)$key), $quote($label), $quote($contract));
}
$lines[] = '  end';
$lines[] = '';
$lines[] = '  subgraph TOOLS["Guaranteed scripts and tools"]';
foreach (($config['scripts'] ?? []) as $key => $script) {
    $lines[] = sprintf('    %s["%s<br/><small>%s</small>"]:::tool', $id((string)$key), $quote((string)($script['label'] ?? $key)), $quote((string)($script['command'] ?? '')));
}
$lines[] = '  end';
$lines[] = '';

foreach (($config['edges'] ?? []) as $edge) {
    $from = $id((string)($edge['from'] ?? ''));
    $to = $id((string)($edge['to'] ?? ''));
    $label = $quote((string)($edge['label'] ?? ''));
    $lines[] = "  {$from} -->|{$label}| {$to}";
}

$required = implode(', ', array_map('strval', $config['telemetry']['required'] ?? []));
$minimum = (int)($config['telemetry']['minimum_score'] ?? 0);
$lines[] = '';
$lines[] = sprintf('  telemetry["Telemetry<br/><small>%s</small><br/>minimum score %d"]:::gate', $quote($required), $minimum);
$lines[] = '  worker -. records .-> telemetry';
$lines[] = '  reviewer -. verifies .-> telemetry';
$lines[] = '  browser_tester -. renders .-> telemetry';
$lines[] = '  score -. aggregates .-> telemetry';

$content = implode("\n", $lines) . "\n";
file_put_contents($output, $content);
echo "Generated: {$output}\n";
