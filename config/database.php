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
    'remote_fallback_token' => '3d7aae804963bb7a74df410fb50a4fa79d2db10a410b88e5a440e69581d1e4e5',
];
