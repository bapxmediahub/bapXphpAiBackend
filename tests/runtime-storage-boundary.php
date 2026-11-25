<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Direct PHP calls that can create or update a local file. Value = destination argument index. */
const RUNTIME_STORAGE_WRITERS = [
    'copy' => 1, 'rename' => 1, 'move_uploaded_file' => 1, 'stream_copy_to_stream' => 1,
    'file_put_contents' => 0, 'fopen' => 0, 'fwrite' => 0, 'fputs' => 0, 'ftruncate' => 0,
    'mkdir' => 0, 'touch' => 0, 'tempnam' => 0, 'tmpfile' => 0, 'link' => 1, 'symlink' => 1,
    'gzopen' => 0, 'bzopen' => 0, 'yaml_emit_file' => 0, 'splfileobject' => 0, 'sqlite3' => 0,
];

/** @param list<PhpToken> $tokens @return list<string> */
function runtimeStorageArguments(array $tokens, int $start): array {
    $arguments = [''];
    $depth = 0;
    for ($i = $start, $length = count($tokens); $i < $length; ++$i) {
        $token = $tokens[$i];
        if ($depth === 0 && $token->is(')')) break;
        if ($depth === 0 && $token->is(',')) {
            $arguments[] = '';
            continue;
        }
        if ($token->is(['(', '[', '{', T_ATTRIBUTE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) ++$depth;
        if ($token->is([')', ']', '}'])) --$depth;
        $arguments[array_key_last($arguments)] .= $token->text;
    }
    return $arguments;
}

/** @return list<array{writer:string, line:int}> */
function runtimeStorageWrites(string $source): array {
    $tokens = array_values(array_filter(PhpToken::tokenize($source), static fn(PhpToken $token): bool => !$token->isIgnorable()));
    $calls = [];
    $functionImport = false;
    foreach ($tokens as $index => $token) {
        $next = $tokens[$index + 1] ?? null;
        if ($token->is(T_USE) && $next?->is(T_FUNCTION)) $functionImport = true;
        if ($token->is(';')) $functionImport = false;
        if (!$token->is([T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE])) continue;
        $parts = explode('\\', strtolower($token->text));
        $function = end($parts);
        if (!isset(RUNTIME_STORAGE_WRITERS[$function])) continue;
        $previous = $tokens[$index - 1] ?? null;
        if ($functionImport) {
            $calls[] = ['writer' => 'import ' . $function, 'line' => $token->line];
            continue;
        }
        if (!$next?->is('(')) continue;
        if ($previous?->is([T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON])) continue;
        if ($previous?->is('&') && ($tokens[$index - 2] ?? null)?->is(T_FUNCTION)) continue;

        $arguments = runtimeStorageArguments($tokens, $index + 2);
        $destination = $arguments[RUNTIME_STORAGE_WRITERS[$function]] ?? '';
        if (in_array($function, ['fopen', 'gzopen', 'bzopen', 'splfileobject'], true)) {
            $mode = $arguments[1] ?? ($function === 'splfileobject' ? "'r'" : '');
            if (preg_match('/^([\x27"])(?:r|rb|rt)\1$/D', $mode)) continue;
        }
        $transient = in_array($destination, ['STDOUT', 'STDERR', '\STDOUT', '\STDERR'], true)
            || preg_match('/^([\x27"])php:\/\/(?:memory|temp|output)\1$/D', $destination);
        if ($transient && in_array($function, ['fopen', 'fwrite', 'fputs', 'file_put_contents'], true)) continue;

        $calls[] = ['writer' => $function . ' ' . $destination, 'line' => $token->line];
    }
    return $calls;
}

/** @return list<string> PHP sources loaded from the application or its route entrypoints */
function runtimeStorageSources(string $root): array {
    $sources = ['index.php'];
    foreach (['app', 'api', 'config', 'integrations', 'views'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
    }
    sort($sources, SORT_STRING);
    return $sources;
}

/** Reviewed call sites for approved operational files, assets and transient I/O. */
const APPROVED_STORAGE_WRITES = [
    // Private chat JSON, per-conversation locks and atomic replacement.
    'app/Services/AgentConversationService.php' => [
        "fopen \$root.'/.conversation-'.\$id.'.lock'" => 4,
        'fopen $path' => 2, 'mkdir $directory' => 1, 'tempnam $root' => 1,
        'file_put_contents $tmp' => 1, 'rename $filename' => 1, 'touch $file' => 1,
    ],
    // Deployment-local provider cooldowns contain no chat or business records.
    'app/Services/AiProviderHealthService.php' => [
        'mkdir $dir' => 1, "fopen \$this->path.'.lock'" => 1, 'tempnam $dir' => 1,
        'file_put_contents $tmp' => 1, 'rename $this->path' => 1,
    ],
    // Markdown articles, private Markdown revisions and their locks.
    'app/Services/BlogService.php' => [
        'mkdir $this->postsDir' => 1, 'fopen $temp' => 1, 'fwrite $handle' => 1,
        'rename $file' => 1, 'fopen $path' => 1,
    ],
    // Immutable campaign file bytes in a verified private sibling directory.
    'app/Services/CampaignDownloadService.php' => [
        'move_uploaded_file $target' => 1, 'mkdir $root' => 1, 'mkdir $folder' => 1,
    ],
    // Deployment-local environment configuration.
    'app/Services/EnvService.php' => ['file_put_contents $path' => 1],
    // Legacy repository-document cache and CLI-generated project index.
    'app/Services/GitHubDocService.php' => [
        'mkdir $this->cacheDir' => 1,
        "file_put_contents \$this->cacheDir.'/'.\$key.'.json'" => 1,
        "file_put_contents \$this->cacheDir.'/'.\$key.'.md'" => 1,
    ],
    'app/Services/KnowledgeGraphService.php' => ['file_put_contents $outputFile' => 1],
    // Image binaries and private quarantine during reviewed removal.
    'app/Services/ImageOptimizerService.php' => ['mkdir $targetDir' => 1, 'tempnam $targetDir' => 1, 'rename $webpPath' => 1],
    'app/Services/MediaService.php' => [
        'mkdir $dir' => 1, 'move_uploaded_file $destPath' => 1,
        'mkdir $trashDir' => 1, 'rename $trash' => 1, 'rename $original' => 1,
    ],
    // In-memory CSV import and network I/O.
    'app/Services/ProductImportService.php' => ['fwrite $file' => 1],
    'app/Services/SmtpMailer.php' => ['fwrite $socket' => 1],
    // Private rate-limit counters and operational directory initialization.
    'app/Services/RateLimiter.php' => [
        'mkdir $dir' => 2, "fopen \$path.'.lock'" => 1, 'tempnam $dir' => 1,
        'file_put_contents $tmp' => 1, 'rename $path' => 1, 'file_put_contents $this->file' => 1,
    ],
    'app/Services/StoragePermissionService.php' => ['mkdir $absolute' => 1, 'touch $absolute' => 1],
];

/** @return list<string> */
function runtimeStorageViolations(string $relativePath, string $source): array {
    $approved = APPROVED_STORAGE_WRITES[$relativePath] ?? [];
    $violations = [];
    foreach (runtimeStorageWrites($source) as $call) {
        $writer = $call['writer'];
        $remaining = $approved[$writer] ?? 0;
        if ($remaining === 0) {
            $violations[] = $relativePath . ':' . $call['line'] . ': unreviewed filesystem writer ' . $writer;
            continue;
        }
        $approved[$writer] = $remaining - 1;
    }
    return $violations;
}

$tests['runtime PHP file writes stay within the reviewed storage boundary'] = static function (): void {
    $root = app_path();
    $violations = [];
    foreach (runtimeStorageSources($root) as $path) {
        $source = file_get_contents($root . '/' . $path);
        if ($source === false) throw new RuntimeException('Unable to inspect runtime source: ' . $path);
        array_push($violations, ...runtimeStorageViolations($path, $source));
    }
    assertSame([], $violations, "Unreviewed filesystem writes:\n" . implode("\n", $violations));
};

$tests['storage boundary rejects new local business writers and changed destinations'] = static function (): void {
    $newStore = <<<'PHP'
<?php
\file_put_contents(storage_path('data/products.json'), json_encode($rows));
$handle = fopen(storage_path('media/products.yaml'), 'a+');
$writer = new \SplFileObject($computedYamlPath, 'c');
rename(temporaryPath('metadata', ['a', 'b']), $runtimeCatalogue);
PHP;
    assertSame([
        "file_put_contents storage_path('data/products.json')",
        "fopen storage_path('media/products.yaml')",
        'splfileobject $computedYamlPath',
        'rename $runtimeCatalogue',
    ], array_column(runtimeStorageWrites($newStore), 'writer'), 'Literal and computed destinations must be detected');
    assertSame(4, count(runtimeStorageViolations('app/Services/NewStore.php', $newStore)), 'New runtime store must fail');
    assertMinCount(1, runtimeStorageViolations('app/Services/NewStore.php', <<<'PHP'
<?php
use function strlen as countChars, \file_put_contents as persist;
persist($destination, $json);
PHP), 'Renaming a filesystem writer with an import does not exempt the call');

    $path = 'app/Services/EnvService.php';
    $approved = file_get_contents(app_path($path));
    $redirected = str_replace('file_put_contents($path,', "file_put_contents(storage_path('media/settings.yaml'),", $approved, $count);
    assertSame(1, $count, 'The reviewed call still exists');
    assertMinCount(1, runtimeStorageViolations($path, $redirected), 'An existing writer cannot silently take a new destination');
    assertMinCount(1, runtimeStorageViolations($path, $approved . "\nfile_put_contents(\$path, \$json);"), 'Duplicating an approved call is an additional write');

    $readsAndStreams = <<<'PHP'
<?php
// file_put_contents('/storage/data/fake.json', 'ignored');
$example = "fopen('sample', 'w')";
$record = file_get_contents($fixturePath);
fopen(buildPath(['a', 'b']), 'rb');
new \SplFileObject($fixturePath);
fopen('php://memory', 'w+');
fopen("php://output", 'w');
fwrite(\STDERR, 'diagnostic');
$service->copy($from, $to);
PHP;
    assertSame([], runtimeStorageViolations('app/Services/PermittedIo.php', $readsAndStreams), 'Reading, methods and transient I/O are not disk writers');
};
