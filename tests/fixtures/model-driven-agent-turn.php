<?php
declare(strict_types=1);
namespace {
    if(PHP_SAPI!=='cli'||getenv('BAPX_MODEL_DRIVEN_FIXTURE')!=='1')exit(1);
}
namespace App\Services {
    function fixtureEndpoint(): string {
        $endpoint = (string)getenv('BAPX_FIXTURE_ENDPOINT');
        if (!preg_match('~^http://127\.0\.0\.1:[0-9]+/[a-z_]+$~D', $endpoint)) {
            throw new \RuntimeException('Only loopback fixtures permitted');
        }
        return $endpoint;
    }

    function fixtureCompatibleHost(): string {
        return in_array(getenv('BAPX_FIXTURE_PROTOCOL'), ['openai','openai_legacy'], true)
            ? 'api.openai.com' : 'provider.fixture.invalid';
    }

    // Local shims exercise AiClient's usual configured-endpoint route while
    // permitting connections only to this fixture's temporary loopback server.
    function dns_get_record(string $host, int $type): array {
        if ($host !== fixtureCompatibleHost()) throw new \RuntimeException('Unexpected DNS lookup');
        return [['ip'=>'93.184.216.34']];
    }
    function curl_init(?string $url = null): \CurlHandle|false {
        $endpoint = fixtureEndpoint();
        if ($url === $endpoint . '/fixture-model:generateContent') return \curl_init($url);
        if ($url === 'https://' . fixtureCompatibleHost() . '/v1/chat/completions') {
            return \curl_init($endpoint . '/chat/completions');
        }
        throw new \RuntimeException('Unexpected network destination');
    }

    final class SecretService {
        public const PRIMARY_MODEL='fixture-model', FALLBACK_MODEL='fixture-model';
        public const GOOGLE_MODELS=['fixture-model'];
        public static function validModelId(string $model): bool {return $model==='fixture-model';}
        public function getProviderPlan(): array {
            $endpoint = fixtureEndpoint();
            $google = ['provider'=>'google','endpoint'=>$endpoint,'apiKey'=>'fixture-only','models'=>['fixture-model'],'model'=>'fixture-model'];
            $protocol = getenv('BAPX_FIXTURE_PROTOCOL');
            if ($protocol === 'google') return [$google];
            $model = match ($protocol) {
                'openai' => 'gpt-5',
                'openai_legacy' => 'gpt-4o',
                default => 'fixture-model',
            };
            $compatible = ['provider'=>'openai_compatible','endpoint'=>'https://' . fixtureCompatibleHost() . '/v1',
                'apiKey'=>'fixture-only','models'=>[$model],'model'=>$model,'configured'=>true];
            return preg_match('~/catalogue_(?:fallback|locked)$~D',$endpoint) ? [$compatible,$google] : [$compatible];
        }
        public function getModelConfig(): array {return $this->getProviderPlan()[0];}
        public function all(): array {return [];}
    }
}
namespace {
    putenv('BAPX_TEST_MODE=1');
    date_default_timezone_set('Asia/Kolkata');
    function app_path(string $path=''): string{return dirname(__DIR__,2).'/'.$path;}
    function storage_path(string $path=''): string{return $GLOBALS['directory'].'/'.$path;}
    function module_on(string $key): bool{return (new \App\Services\SettingsService())->moduleEnabled($key);}
    function e(string $value): string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
    spl_autoload_register(static function(string $class):void{
        if(str_starts_with($class,'App\\')){ $path=app_path('app/'.str_replace('\\','/',substr($class,4)).'.php');if(is_file($path))require $path; }
    });
    $directory=sys_get_temp_dir().'/bapx-model-turn-'.bin2hex(random_bytes(8));
    mkdir($directory,0700);mkdir($directory.'/logs',0700);
    $scenario=$argv[1];$surface=$argv[2];$role=$argv[3];
    $_SERVER['REQUEST_METHOD']='POST';$_SERVER['REMOTE_ADDR']='127.0.0.1';
    $_SERVER['REQUEST_URI']=$surface==='admin'?'/admin/agent/ask':'/support/ask';
    $_SESSION=['csrf_token'=>'fixture','support_guest_id'=>'fixture-guest'];
    if($role!=='guest')$_SESSION['user']=['role'=>$role,'id'=>'fixture-actor','email'=>'fixture@example.invalid'];
    $_POST=['message'=>$argv[4],'_csrf'=>'fixture','request_id'=>bin2hex(random_bytes(16))];
    $followupReport = $scenario === 'owner_followup';
    $followup = $followupReport || str_starts_with($scenario, 'catalogue_');
    $foreign = in_array($scenario, ['foreign_support','foreign_owner'], true);
    $seedAnswer = null;
    if ($followup) {
        $conversations = new \App\Services\AgentConversationService();
        $actor = $conversations::actor($surface, $_SESSION['user'] ?? null);
        $seedAnswer = $followupReport
            ? 'The last delivered order report covered 3 to 7 January.'
            : 'There are currently 43 products published in the shop. One example was /product/fixture-first.';
        $report = $followupReport ? ['tool'=>'order_count', 'from'=>'2024-01-03', 'to'=>'2024-01-07'] : null;
        $knowledge = $followupReport ? [] : ['public_product_slugs'=>['fixture-first']];
        $question = $followupReport ? 'Show the first order report.' : 'How many products were published?';
        $previous = $conversations->record($surface, $actor, null, $question, $seedAnswer,
            bin2hex(random_bytes(8)), 'model', reportContext:$report, knowledge:$knowledge);
        $_POST['conversation_id'] = $previous['conversation_id'];
        $conversations->record($surface, $actor, $previous['conversation_id'], 'Can I ask a follow-up?',
            'Yes, you can ask about the same subject.', bin2hex(random_bytes(8)), 'model');
        $failedReport = $followupReport ? ['tool'=>'order_count', 'from'=>'2024-01-08', 'to'=>'2024-01-14'] : null;
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $conversations->record($surface, $actor, $previous['conversation_id'], 'Follow-up attempt.',
                'Unsuccessful provider attempt.', bin2hex(random_bytes(8)), 'error',
                reportContext:$failedReport, knowledge:$followupReport ? [] : ['public_product_slugs'=>['unaccepted']]);
        }
    } elseif ($foreign) {
        $conversations = new \App\Services\AgentConversationService();
        $identity = $conversations::actor($surface, $_SESSION['user'] ?? null);
        $identity['id'] = 'different-actor';
        $previous = $conversations->record($surface, $identity, null, 'This is a different actor’s question.',
            'Different actor’s private answer.', bin2hex(random_bytes(8)), 'model');
        $_POST['conversation_id'] = $previous['conversation_id'];
    }
    ob_start();
    register_shutdown_function(static function()use($directory,$seedAnswer):void{
        $response=json_decode(ob_get_clean(),true);
        $files=glob($directory.'/agent-conversations/*.json')?:[];
        $turns=$files?(json_decode(file_get_contents($files[0]),true)['turns']??[]):[];
        $result=['status'=>http_response_code(),'response'=>$response,'turn'=>$turns[array_key_last($turns)]??[]];
        if ($seedAnswer !== null) {
            $result['history_preserved'] = count($files) === 1 && count($turns) === 7
                && ($response['conversation_id'] ?? null) === $_POST['conversation_id']
                && ($turns[0]['answer'] ?? null) === $seedAnswer
                && array_column(array_slice($turns, 2, 4), 'outcome') === array_fill(0, 4, 'error');
        }
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST) as $entry){
            if($entry->isDir())rmdir($entry->getPathname());else unlink($entry->getPathname());
        }
        rmdir($directory);
    });
    if($surface==='admin')(new \App\Controllers\AdminController())->agentAsk();
    else (new \App\Controllers\SupportController())->ask();
}
