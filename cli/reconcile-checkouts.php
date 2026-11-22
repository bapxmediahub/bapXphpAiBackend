<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$limit=20;
foreach(array_slice($argv,1) as $arg){
    if(!preg_match('/^--limit=([0-9]{1,2})$/D',$arg,$matches))throw new InvalidArgumentException('Use --limit=1..20.');
    $limit=(int)$matches[1];
}
$result=(new \App\Services\RemoteCheckoutService())->reconcilePending($limit);
echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
