<?php
declare(strict_types=1);

// Intentionally independent of app/bootstrap.php and the database. An external
// WordPress plugin archive is data for inspection, never code to load.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
require_once dirname(__DIR__).'/app/Services/WordPressPackageInspector.php';

if (count($argv) !== 2 || !str_ends_with(strtolower($argv[1]), '.zip')) {
    fwrite(STDERR, "Usage: bapXphp plugin inspect /absolute/path/plugin.zip\n");
    exit(2);
}
try {
    $result = \App\Services\WordPressPackageInspector::inspect($argv[1]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
} catch (\InvalidArgumentException $error) {
    fwrite(STDERR, 'Plugin preflight rejected: '.$error->getMessage()."\n");
    exit(1);
} catch (\Throwable $error) {
    // No exception traces, archive contents, credentials or absolute paths.
    fwrite(STDERR, 'Plugin preflight failed ('.get_class($error).").\n");
    exit(1);
}
