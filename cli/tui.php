<?php
/**
 * Interactive terminal UI for bapXphp.
 *
 * Dependency-free by design (this project ships no composer/vendor):
 * raw stdin keypresses via `stty`, ANSI escape codes for color/cursor
 * movement. Arrow keys navigate, Enter runs the selected bapXphp command
 * with live streamed output, 'q' or Esc quits.
 */

$root = dirname(__DIR__);

if (!function_exists('posix_isatty') || !posix_isatty(STDOUT)) {
    fwrite(STDERR, "bapXphp tui needs an interactive terminal (not a pipe/redirect).\n");
    exit(1);
}

$originalStty = trim(shell_exec('stty -g 2>/dev/null') ?? '');
function restoreTty(string $originalStty): void {
    if ($originalStty !== '') {
        shell_exec('stty ' . escapeshellarg($originalStty) . ' 2>/dev/null');
    }
    echo "\033[?25h"; // show cursor
}
register_shutdown_function(fn() => restoreTty($originalStty));
foreach ([SIGINT, SIGTERM] as $sig) {
    if (function_exists('pcntl_signal')) {
        pcntl_signal($sig, function () use ($originalStty) {
            restoreTty($originalStty);
            exit(0);
        });
    }
}
shell_exec('stty -icanon -echo 2>/dev/null');
echo "\033[?25l"; // hide cursor

function gitBranch(): string {
    $b = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?? '');
    return $b !== '' ? $b : '(no git)';
}
function gitDirty(): bool {
    return trim(shell_exec('git status --porcelain 2>/dev/null') ?? '') !== '';
}
function activeHandoff(string $root): string {
    $path = $root . '/.agents/handoffs/active/current.json';
    if (!is_file($path)) return 'none';
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) return 'invalid';
    $issue = (int)($data['issue'] ?? 0);
    $role = (string)($data['workflow']['current_role'] ?? '?');
    $next = (string)($data['workflow']['next_role'] ?? '?');
    return $issue > 0 ? "#{$issue} {$role} -> {$next}" : 'invalid';
}

$menu = [
    'Orientation' => [
        ['Project overview (understand)', 'understand'],
        ['Session context (branch, status, last test)', 'context'],
    ],
    'Development' => [
        ['Full CI: lint -> test -> maps -> smoke', 'ci'],
        ['Lint only', 'lint'],
        ['Test suite only', 'test'],
        ['Regenerate + validate project maps', 'update'],
        ['Start dev server (127.0.0.1:6020)', 'serve'],
    ],
    'Agents & Handoffs' => [
        ['Show active GitHub Actions handoff', 'handoff active'],
    ],
    'Housekeeping' => [
        ['Install repository Git hooks', 'hooks install'],
        ['Show repository Git hook status', 'hooks status'],
        ['Clean untracked runtime artifacts', 'artifacts:clean --dry-run'],
        ['Repo status', 'status'],
    ],
];

$flat = [];
foreach ($menu as $section => $items) {
    $flat[] = ['header', $section];
    foreach ($items as $item) $flat[] = ['item', $item[0], $item[1]];
}
$selectable = [];
foreach ($flat as $i => $row) if ($row[0] === 'item') $selectable[] = $i;

$cursor = $selectable[0];

function render(array $flat, int $cursor, string $root): void {
    echo "\033[2J\033[H"; // clear + home
    $branch = gitBranch();
    $dirty = gitDirty() ? "\033[33mdirty\033[0m" : "\033[32mclean\033[0m";
    $handoff = activeHandoff($root);
    echo "\033[1mbapXphp\033[0m  —  branch \033[36m{$branch}\033[0m  ({$dirty})";
    echo "   handoff: \033[36m{$handoff}\033[0m\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($flat as $i => $row) {
        if ($row[0] === 'header') {
            echo "\n\033[1;35m{$row[1]}\033[0m\n";
            continue;
        }
        $label = $row[1];
        if ($i === $cursor) {
            echo "  \033[7m▸ {$label}\033[0m\n";
        } else {
            echo "    {$label}\n";
        }
    }
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "↑/↓ move   enter run   q quit\n";
}

function readKey() {
    $c = fread(STDIN, 1);
    if ($c === "\033") {
        $c2 = fread(STDIN, 1);
        if ($c2 === '[') {
            $c3 = fread(STDIN, 1);
            if ($c3 === 'A') return 'up';
            if ($c3 === 'B') return 'down';
            return 'other';
        }
        return 'esc';
    }
    if ($c === "\n" || $c === "\r") return 'enter';
    if ($c === 'q') return 'quit';
    return $c;
}

function runCommand(string $root, string $cmd): void {
    echo "\033[2J\033[H";
    shell_exec('stty ' . escapeshellarg(trim(shell_exec('stty -g') ?? '')) . ' 2>/dev/null');
    shell_exec('stty sane 2>/dev/null');
    echo "\033[?25h\$ bapXphp {$cmd}\n\n";
    passthru('cd ' . escapeshellarg($root) . ' && ./bapXphp ' . $cmd);
    echo "\n\033[2mpress any key to return to the menu\033[0m\n";
    shell_exec('stty -icanon -echo 2>/dev/null');
    fread(STDIN, 1);
    echo "\033[?25l";
}


// ── Main event loop ───────────────────────────────────────────────
while (true) {
    render($flat, $cursor, $root);
    $key = readKey();
    if ($key === "up" || $key === "down") {
        $delta = ($key === "up") ? -1 : 1;
        $new = $cursor + $delta;
        while ($new >= 0 && $new < count($flat) && $flat[$new][0] !== "item") {
            $new += $delta;
        }
        if ($new >= 0 && $new < count($flat)) $cursor = $new;
    } elseif ($key === "enter") {
        $idx = $flat[$cursor][2] ?? "";
        if ($idx) runCommand($root, $idx);
    } elseif ($key === "quit" || $key === "esc") {
        break;
    }
}
