<?php
$env = parse_ini_file(app_path('.env')) ?: [];
foreach (['BAPX_MYSQL_HOST','BAPX_MYSQL_PORT','BAPX_MYSQL_DB','BAPX_MYSQL_USER','BAPX_MYSQL_PASS'] as $k) {
    $env[$k] = $env[$k] ?? $_SERVER[$k] ?? $_ENV[$k] ?? '';
}
return [
    'host' => $env['BAPX_MYSQL_HOST'] ?: 'localhost',
    'port' => $env['BAPX_MYSQL_PORT'] ?: '3306',
    'dbname' => $env['BAPX_MYSQL_DB'] ?: 'u907253411_db_name_sps',
    'user' => $env['BAPX_MYSQL_USER'] ?: 'u907253411_db_user_sps',
    'pass' => $env['BAPX_MYSQL_PASS'] ?: '',
    'remote_fallback_url' => 'https://sripanchamispiritual.com/remotedb',
    'remote_fallback_token' => '7199a9435b916416c51bf9291e4e66852101117230e45cc7e90ae2b1b1c48161',
];
