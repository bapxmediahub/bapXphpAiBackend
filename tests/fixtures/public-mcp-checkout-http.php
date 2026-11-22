<?php
declare(strict_types=1);
// Dedicated native PHP loopback router and CLI controls. Never a production route.
if(!in_array(PHP_SAPI,['cli','cli-server'],true)){http_response_code(404);exit;}
if(PHP_SAPI==='cli-server'&&(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)
    ||parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)!=='/api/mcp')){http_response_code(404);exit;}
$directory=realpath((string)getenv('BAPX_MCP_CHECKOUT_FIXTURE_DIR'));
if($directory===false||dirname($directory)!==realpath(sys_get_temp_dir())
    ||!preg_match('/^bapx-mcp-checkout-[A-Za-z0-9]+$/D',basename($directory)))throw new RuntimeException('Refuse a nonfixture state directory.');
putenv('BAPX_TEST_MODE=1');putenv('APP_URL=https://cart.example.test');
$_SERVER['APP_URL']='https://cart.example.test';
// The guarded loopback /api/mcp branch uses the production stateless bootstrap path.
if(PHP_SAPI==='cli-server')define('BAPX_PUBLIC_MCP_REQUEST',true);
require dirname(__DIR__,2).'/app/bootstrap.php';
require __DIR__.'/remote-checkout.php';
$file=fopen($directory.'/state.json','c+');
if($file===false||!flock($file,LOCK_EX))throw new RuntimeException('Fixture state unavailable.');
chmod($directory.'/state.json',0600);
$raw=stream_get_contents($file);$state=$raw===''?null:json_decode($raw,true,64,JSON_THROW_ON_ERROR);
$fixture=new RemoteCheckoutFixture($state);
$shopEnabled=$state['shopEnabled']??true;
$fixture->cartFixture->shopEnabled($shopEnabled);
$output='';
try{
    if(PHP_SAPI==='cli-server'){
        if(session_status()===PHP_SESSION_ACTIVE)throw new RuntimeException('Public MCP fixture opened a PHP session.');
        $service=new App\Services\PublicMcpService(knowledge:$fixture->cartFixture->knowledge(),shopping:$fixture->cartFixture->service(),
            checkout:$fixture->service(),publicUrl:'https://cart.example.test');
        // Commit the synthetic provider/store state before sending the HTTP result,
        // matching the production database's durable-before-response boundary.
        ob_start();
        try{(new App\Controllers\PublicMcpController($service))->handle();$output=(string)ob_get_contents();}
        finally{ob_end_clean();}
    }else{
        $input=json_decode(file_get_contents('php://stdin')?:'{}',true,32,JSON_THROW_ON_ERROR);
        switch($argv[1]??''){
            case 'initialize':
                if($state!==null)throw new RuntimeException('Fixture already initialized.');
                $fixture->cartFixture->now=time();
                $output=json_encode(['ready'=>true],JSON_THROW_ON_ERROR);break;
            case 'mark_paid':
                $fixture->markPaid((string)($input['attemptId']??''));$fixture->advance(31);
                $output=json_encode(['marked'=>true],JSON_THROW_ON_ERROR);break;
            case 'configure':
                foreach($input as$key=>$value){
                    if(!is_bool($value)||!in_array($key,['gatewayOffline','timeoutAfterCreate','failPersist','shopEnabled'],true))throw new RuntimeException('Invalid fixture flag.');
                    if($key==='shopEnabled')$shopEnabled=$value;else$fixture->$key=$value;
                }
                $fixture->advance(31);$output=json_encode(['configured'=>true],JSON_THROW_ON_ERROR);break;
            case 'inspect':
                $output=json_encode(['orders'=>count($fixture->orders),'attempts'=>count($fixture->attempts),
                    'providerCreates'=>count(array_filter($fixture->gatewayCalls,static fn(array$row):bool=>$row['operation']==='create_link'))],JSON_THROW_ON_ERROR);break;
            default:throw new RuntimeException('Unknown fixture control.');
        }
    }
    $encoded=json_encode($fixture->exportState()+['shopEnabled'=>$shopEnabled],JSON_THROW_ON_ERROR);
    rewind($file);ftruncate($file,0);if(fwrite($file,$encoded)!==strlen($encoded))throw new RuntimeException('Fixture state incomplete.');
    fflush($file);
}finally{$fixture->close();flock($file,LOCK_UN);fclose($file);}
echo$output;
