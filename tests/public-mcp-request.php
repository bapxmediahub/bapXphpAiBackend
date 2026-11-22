<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
putenv('APP_URL=https://example.test');
require dirname(__DIR__).'/app/bootstrap.php';
$body=base64_decode($argv[1]??'',true);
$headers=json_decode(base64_decode($argv[2]??'',true),true,32,JSON_THROW_ON_ERROR);
if (!is_string($body) || !is_array($headers)) throw new RuntimeException('Invalid SDK fixture.');
echo json_encode((new App\Services\PublicMcpService())->handle($body,$headers),JSON_THROW_ON_ERROR);
