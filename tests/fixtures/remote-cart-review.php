<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__,2).'/app/bootstrap.php';
require __DIR__.'/remote-cart.php';
$fixture=new RemoteCartFixture();
try{
    $service=$fixture->service();$cart=$service->shoppingCart(['operation'=>'create']);$token=$cart['cartToken'];
    foreach(['sri-varahi-amman-ring','varahi-amman-pendant','தமிழ் pendant']as$slug)$cart=$service->shoppingCart([
        'operation'=>'set_quantity','cartToken'=>$token,'expectedRevision'=>$cart['revision'],'slug'=>$slug,'quantity'=>2]);
    $checkout=$service->prepareCheckout(['cartToken'=>$token,'expectedRevision'=>$cart['revision']]);
    parse_str(parse_url($checkout['checkoutUrl'],PHP_URL_QUERY),$query);$ticket=$query['ticket'];
    $preview=$service->previewCheckout($ticket,hash('sha256','review-browser'));
    $render=static function(?array$preview,string$ticket,?string$error=null,bool$alreadyImported=false):string{
        $csrf='fixture-csrf-token';$browserItemCount=7;
        ob_start();require app_path('views/public/agent-checkout.php');return ob_get_clean();
    };
    $fixtures=['ready'=>$render($preview,$ticket),'changed'=>$render($preview,$ticket,'Product prices changed. Review the current selections before continuing.')];
    $unavailable=$preview;$unavailable['cart']['items'][0]['available']=false;
    $unavailable['cart']['unavailableSlugs']=['sri-varahi-amman-ring'];$unavailable['cart']['estimatedTotalInr']=null;
    $fixtures['unavailable']=$render($unavailable,$ticket);
    $imported=$preview;$imported['alreadyImported']=true;$imported['cart']['status']='handed_off';
    $fixtures['imported']=$render($imported,$ticket,null,true);
    $fixtures['recovery']=$render($imported,$ticket);
    $fixtures['expired']=$render(null,'','This checkout review link expired. Request a new link.');
    echo json_encode($fixtures,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
}finally{$fixture->close();}
