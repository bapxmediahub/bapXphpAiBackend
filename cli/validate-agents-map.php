<?php
declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__);
$path = $root . '/agents.mmd';
$temporary = $root . '/.agents.mmd.tmp';

$command = sprintf(
    '%s %s %s %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($root . '/cli/generate-agents-map.php'),
    escapeshellarg($root),
    escapeshellarg($temporary)
);
exec($command, $output, $status);

if ($status !== 0 || !is_file($temporary)) {
    fwrite(STDERR, "Unable to generate agent workflow map.\n");
    exit(1);
}

$expected = (string)file_get_contents($temporary);
unlink($temporary);

if (!is_file($path) || trim((string)file_get_contents($path)) !== trim($expected)) {
    fwrite(STDERR, "Generated agent workflow map is stale. Run bapXphp update and commit agents.mmd.\n");
    exit(1);
}

echo "Agent workflow map valid\n";
