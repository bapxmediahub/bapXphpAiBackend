<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/lib/LegacyMediaMigration.php';

$args = array_slice($argv, 1);
if ($args !== [] && $args !== ['--apply']) {
    fwrite(STDERR, "Usage: ./bapXphp media:reconcile [--apply]\n"); exit(2);
}
$apply = $args === ['--apply'];
try {
    $input = LegacyMediaMigration::input(dirname(__DIR__));
    $result = LegacyMediaMigration::run($input, new App\Services\DatabaseService($apply), $apply);
} catch (PDOException $error) {
    fwrite(STDERR, "Direct MySQL reconciliation failed. Check connectivity, permissions and transaction support.\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, "Media reconciliation failed: " . $error->getMessage() . "\n");
    exit(1);
}
echo json_encode(['mode'=>$apply ? 'apply' : 'preflight'] + $result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
exit($result['complete'] ? 0 : 1);
