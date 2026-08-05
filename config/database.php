<?php
$fileEnv = parse_ini_file(app_path('.env')) ?: [];
$envValue = static function (string $key, string $default = '') use ($fileEnv): string {
    $runtime = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);
    if ($runtime !== false && $runtime !== null && $runtime !== '') return (string)$runtime;
    return (string)($fileEnv[$key] ?? $default);
};
$appUrl = rtrim($envValue('APP_URL', 'https://sripanchamispiritual.com'), '/');
return [
    'app_url' => $appUrl,
    'host' => $envValue('BAPX_MYSQL_HOST'),
    'port' => $envValue('BAPX_MYSQL_PORT', '3306'),
    'dbname' => $envValue('BAPX_MYSQL_DB'),
    'user' => $envValue('BAPX_MYSQL_USER'),
    'pass' => $envValue('BAPX_MYSQL_PASS'),
    // Lowercase '/remotedb'. The live host serves this path case-sensitively and 404s on
    // A wrong-case endpoint variant must fail loudly; transport and response failures are never converted
    // into an empty collection that could be mistaken for valid production data.
    'remote_url' => $envValue('BAPX_REMOTE_DB_URL', $appUrl . '/remotedb'),
    'remote_db_password' => $envValue('REMOTE_DB_PASSWORD'),
];
