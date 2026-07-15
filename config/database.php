<?php
$env = parse_ini_file(app_path('.env')) ?: [];
foreach (['BAPX_MYSQL_HOST','BAPX_MYSQL_PORT','BAPX_MYSQL_DB','BAPX_MYSQL_USER','BAPX_MYSQL_PASS','REMOTE_DB_PASSWORD'] as $k) {
    $env[$k] = $env[$k] ?? $_SERVER[$k] ?? $_ENV[$k] ?? '';
}
$appUrl = rtrim((string)($env['APP_URL'] ?? $_SERVER['APP_URL'] ?? $_ENV['APP_URL'] ?? 'https://sripanchamispiritual.com'), '/');
return [
    'host' => $env['BAPX_MYSQL_HOST'] ?: 'localhost',
    'port' => $env['BAPX_MYSQL_PORT'] ?: '3306',
    'dbname' => $env['BAPX_MYSQL_DB'] ?: 'u907253411_db_name_sps',
    'user' => $env['BAPX_MYSQL_USER'] ?: 'u907253411_db_user_sps',
    'pass' => $env['BAPX_MYSQL_PASS'] ?: '',
    'remote_url' => (string)($env['BAPX_REMOTE_DB_URL'] ?? $_SERVER['BAPX_REMOTE_DB_URL'] ?? $_ENV['BAPX_REMOTE_DB_URL'] ?? ($appUrl . '/remoteDB')),
    'remote_db_password' => (string)($env['REMOTE_DB_PASSWORD'] ?? ''),
];
