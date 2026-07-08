<?php
return [
    'host' => getenv('BAPX_MYSQL_HOST') ?: getenv('MYSQL_HOST') ?: 'srv1877.hstgr.io',
    'port' => getenv('BAPX_MYSQL_PORT') ?: getenv('MYSQL_PORT') ?: '3306',
    'dbname' => getenv('BAPX_MYSQL_DB') ?: getenv('MYSQL_DB') ?: 'u907253411_db_name_sps',
    'user' => getenv('BAPX_MYSQL_USER') ?: getenv('MYSQL_USER') ?: 'u907253411_db_user_sps',
    'pass' => getenv('BAPX_MYSQL_PASS') ?: getenv('MYSQL_PASS') ?: 'SPsprituals2026#',
];
