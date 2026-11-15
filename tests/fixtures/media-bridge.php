<?php
declare(strict_types=1);
header('Content-Type: application/json');
$payload=json_decode((string)file_get_contents('php://input'),true)?:[];
if (($payload['password']??'')!=='sps_fixture_only_password') {
    http_response_code(401);echo json_encode(['success'=>false,'error'=>'invalid fixture credentials']);exit;
}
$path=(string)getenv('BAPX_MEDIA_FIXTURE_STATE');
$data=json_decode((string)file_get_contents($path),true)?:[];
if (isset($payload['query'])) {
    if (!preg_match('/^SELECT \* FROM (media_files|products|temples|categories|settings)$/D',(string)$payload['query'],$m)) {
        http_response_code(400);echo json_encode(['success'=>false,'error'=>'fixture query not allowed']);exit;
    }
    $rows=array_map(static fn(array $r):array=>['id'=>$r['id']??'', '_data'=>json_encode($r,JSON_THROW_ON_ERROR)],
        $data['collections'][$m[1]]??[]);
    echo json_encode(['success'=>true,'data'=>$rows],JSON_THROW_ON_ERROR);exit;
}
if (($payload['action']??'')==='delete' && ($payload['collection']??'')==='media_files') {
    $id=(string)($payload['id']??'');
    if (in_array($id,$data['reject_delete_ids']??[],true)) {
        http_response_code(503);echo json_encode(['success'=>false,'error'=>'fixture metadata removal unavailable']);exit;
    }
    $data['collections']['media_files']=array_values(array_filter($data['collections']['media_files']??[],
        static fn(array $r):bool=>($r['id']??'')!==$id));
    file_put_contents($path,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX);
    echo json_encode(['success'=>true],JSON_THROW_ON_ERROR);exit;
}
http_response_code(400);echo json_encode(['success'=>false,'error'=>'unsupported fixture operation']);
