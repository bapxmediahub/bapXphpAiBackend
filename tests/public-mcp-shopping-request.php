<?php
declare(strict_types=1);
// CLI-only protocol fixture. Synthetic state is carried by the test process, never a live store.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
putenv('APP_URL=https://cart.example.test');
require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/fixtures/remote-checkout.php';
set_exception_handler(static function(Throwable $error):void {
    fwrite(STDERR,'Shopping fixture failed: '.$error::class."\n"); exit(1);
});
$input=json_decode(file_get_contents('php://stdin'),true,64,JSON_THROW_ON_ERROR);
if (!is_array($input) || !is_string($input['body']??null) || !is_array($input['headers']??null)) throw new RuntimeException();
$fixture=new RemoteCheckoutFixture(($input['state']['fixtureVersion']??null)===1?$input['state']:null);
try {
    if(($input['state']['fixtureVersion']??null)!==1)$fixture->cartFixture->now=time();
    $before=count($fixture->operations);
    foreach($input['controls']??[] as $control) {
        if(($control['action']??null)==='markPaid')$fixture->markPaid($control['attemptId'],$control['overrides']??[]);
        else throw new InvalidArgumentException('Unsupported synthetic fixture control.');
    }
    $records=$fixture->cartFixture->records();
    foreach($records as &$record) {
        if($record['id']==='ring') {
            $record['image_url']='/assets/images/products/varahi-amman-ring-front.webp';
            $record['image_urls']=['/assets/images/products/varahi-amman-ring-side-1.webp'];
            $record['description']='Published ring with official catalogue photos.';
            $record['highlights']=['Published fixture selection'];
        }
        if($record['id']==='pendant') $record['image_url']='/assets/images/products/varahi-amman-dollar-front.webp';
        if(in_array($record['slug'],$input['state']['hidden']??[],true)) $record['status']='hidden';
    }
    unset($record);
    $fixture->cartFixture->setProducts($records);
    $service=new App\Services\PublicMcpService(knowledge:$fixture->cartFixture->knowledge(),publicUrl:'https://cart.example.test',
        shopping:$fixture->cartFixture->service(),checkout:$fixture->service());
    $reply=$service->handle($input['body'],$input['headers']);
    echo json_encode(['reply'=>$reply,'state'=>$fixture->exportState()+['hidden'=>$input['state']['hidden']??[]],
        'operations'=>array_merge($fixture->cartFixture->operations,array_slice($fixture->operations,$before))],JSON_THROW_ON_ERROR);
} finally { $fixture->close(); }
