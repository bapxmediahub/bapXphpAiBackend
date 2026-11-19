<?php
declare(strict_types=1);

use App\Services\DatabaseService;
use App\Services\SecretService;

// Local disposable database only. All identifiers and credentials are CI fixture values.
$port = (int)(getenv('BAPX_REPORTING_TEST_PORT') ?: 33068);
function fixtureEnv(string $key, string $value): void {
    putenv("$key=$value");
    $_SERVER[$key] = $value;
}
foreach ([
    'BAPX_MYSQL_HOST'=>'127.0.0.1', 'BAPX_MYSQL_PORT'=>(string)$port,
    'BAPX_MYSQL_DB'=>'sps_order_test', 'BAPX_MYSQL_USER'=>'sps_test', 'BAPX_MYSQL_PASS'=>'sps_ci_fixture',
    'BAPX_REMOTE_DB_URL'=>'http://127.0.0.1:18975/remotedb', 'BAPX_TEST_MODE'=>'0',
] as $key=>$value) fixtureEnv($key, $value);
require dirname(__DIR__) . '/app/bootstrap.php';

function checkReporting(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
function rejectsStale(Closure $mutation): void {
    try { $mutation(); } catch (DomainException) { return; }
    throw new RuntimeException('Expected a stale grant conflict.');
}

$db = new DatabaseService(true);
$db->connection()->exec('CREATE TABLE IF NOT EXISTS secrets (
    id VARCHAR(64) PRIMARY KEY, _data JSON NOT NULL, _owner VARCHAR(255),
    _status VARCHAR(64), _created_at VARCHAR(40), _updated_at VARCHAR(40)
)');
$secrets = new SecretService($db);
checkReporting($secrets->googleReporting() === [], 'Expected an empty disposable grant.');
$secrets->saveGoogleGrant('private-fixture-refresh');
$old = $secrets->googleReporting();
rejectsStale(fn()=>$secrets->saveGoogleGrant('conflicting-token'));
$secrets->save(['reporting_fixture_setting'=>'ordinary']);
checkReporting(!isset($secrets->all()['refresh_token']), 'Integration form copied a refresh token.');
checkReporting(!str_contains(json_encode($db->read('secrets')), 'private-fixture-refresh'), 'Token appeared in plaintext storage.');
$firstTab = $secondTab = ['generation'=>$old['generation'], 'revision'=>$old['revision']];
checkReporting(\App\Controllers\AdminGoogleReportingController::matchesForm($old, $firstTab), 'Fresh form rejected.');
$secrets->saveGoogleTargets($old, 'properties/1', 'sc-domain:example.test');
checkReporting(!\App\Controllers\AdminGoogleReportingController::matchesForm($secrets->googleReporting(), $secondTab), 'Stale property form accepted after another tab saved.');

rejectsStale(fn()=>$secrets->saveGoogleTargets($old, 'properties/2', ''));

$log = tmpfile();
$bridge = proc_open([PHP_BINARY,'-S','127.0.0.1:18975','index.php'], [0=>['pipe','r'],1=>$log,2=>$log], $pipes, dirname(__DIR__));
if (!is_resource($bridge)) throw new RuntimeException('Could not start local bridge.');
fclose($pipes[0]);
try {
    for ($i=0;$i<50;$i++) {
        if ($socket=@fsockopen('127.0.0.1',18975)) { fclose($socket); break; }
        usleep(100000);
    }
    DatabaseService::releaseIdleConnection();
    fixtureEnv('BAPX_MYSQL_PORT','1');
    $remote = new SecretService(new DatabaseService());
    $grant = $remote->googleReporting();
    checkReporting($grant['refresh_token'] === 'private-fixture-refresh' && $grant['property'] === 'properties/1', 'Remote decrypted grant/targets mismatch.');
    $remote->saveGoogleTargets($grant, '', 'https://example.test/');
    rejectsStale(fn()=>$remote->saveGoogleTargets($grant, 'properties/1', ''));
    // The provider confirmed revocation for $grant while the property save above changed its IV.
    $remote->clearRevokedGoogleGrant($grant);
    checkReporting($remote->googleReporting() === [], 'Revoked grant survived a concurrent selection.');
    $remote->clearRevokedGoogleGrant($grant); // A second disconnect completed in the meantime.
    $remote->saveGoogleGrant('second-fixture');
    $remote->clearRevokedGoogleGrant($grant); // Delayed cleanup for the previous consent.
    $new = $remote->googleReporting();
    checkReporting($new['refresh_token'] === 'second-fixture' && $new['site'] === '', 'Stale request affected the new authorization.');
    $remote->clearRevokedGoogleGrant($new);
    checkReporting($remote->googleReporting() === [] && ($remote->all()['reporting_fixture_setting'] ?? '') === 'ordinary', 'Disconnect must clear only the reporting grant.');
    echo "Google reporting encrypted storage, direct MySQL, authenticated bridge and stale operations passed.\n";
} finally {
    proc_terminate($bridge);
    proc_close($bridge);
    fclose($log);
}
