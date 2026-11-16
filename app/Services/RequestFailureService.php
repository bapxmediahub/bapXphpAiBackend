<?php
declare(strict_types=1);
namespace App\Services;

/** Dynamic-request response boundary. Never loads database, session records or provider data. */
final class RequestFailureService
{
    private static bool $registered=false;
    private static bool $failed=false;
    private static string $reference='';
    private static string $root='';
    private static string $failureBody='';

    public static function register(string $root): void
    {
        if(self::$registered)return;
        self::$registered=true;
        self::$root=rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        self::$reference=bin2hex(random_bytes(16));
        // Never let a warning/exception leak through a partly-rendered owner page.
        ini_set('display_errors','0');
        ini_set('display_startup_errors','0');
        // Native buffers may nest. This boundary cannot be flushed/removed by a
        // template's buffer cleanup and only publishes the selected response at end.
        // https://www.php.net/manual/en/function.ob-start.php
        ob_start([self::class,'output'],0,PHP_OUTPUT_HANDLER_CLEANABLE);
        if(!headers_sent())header('X-Request-Id: '.self::$reference);
        set_exception_handler(static function(\Throwable $error): void {
            self::fail($error::class,$error->getFile(),$error->getLine());
        });
        register_shutdown_function(static function(): void {
            if(self::$failed)return;
            $error=error_get_last();
            if(is_array($error)&&in_array($error['type']??0,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)) {
                self::fail('PHPFatalError',(string)($error['file']??''),(int)($error['line']??0));
            }
        });
    }

    /** Output handlers must not invoke any output-buffer manipulation function. */
    public static function output(string $buffer,int $phase): string
    {
        if(!self::$failed)return $buffer;
        return ($phase&PHP_OUTPUT_HANDLER_FINAL)!==0 ? self::$failureBody : '';
    }

    private static function wantsJson(): bool
    {
        $uri=(string)($_SERVER['REQUEST_URI']??'/');
        $path=(string)(parse_url($uri,PHP_URL_PATH)??'/');
        if($path==='/api'||str_starts_with($path,'/api/')||$path==='/remotedb'
            ||in_array($path,['/support/ask','/support/feedback','/admin/agent/ask'],true))return true;
        $types=strtolower((string)($_SERVER['HTTP_ACCEPT']??'').' '.(string)($_SERVER['CONTENT_TYPE']??''));
        foreach(headers_list() as $header)if(str_starts_with(strtolower($header),'content-type:'))$types.=' '.strtolower($header);
        return str_contains($types,'application/json')||str_contains($types,'+json');
    }

    private static function fail(string $class,string $file,int $line): void
    {
        if(self::$failed)return;
        self::$failed=true;
        $json=self::wantsJson();
        $location=str_starts_with($file,self::$root)?substr($file,strlen(self::$root)):basename($file);
        $location=preg_replace('~[^a-zA-Z0-9_./-]~','?',$location)??'unknown';
        $class=preg_replace('~[^a-zA-Z0-9_\\\\]~','?',$class)??'Throwable';
        $agentTrace='';
        foreach(headers_list() as $header)if(preg_match('/^X-Agent-Trace:\s*([a-f0-9]{16})$/iD',$header,$match))$agentTrace=' agent_trace='.$match[1];
        // Do not log exception messages, SQL, request bodies, tokens or session data.
        error_log('[request_failure] reference='.self::$reference.' class='.$class.' source='.$location.':'.max(0,$line).$agentTrace);
        if(!headers_sent()){
            foreach(['Location','Content-Length','Content-Disposition','Content-Encoding','ETag','Last-Modified','Expires','Retry-After'] as $header)header_remove($header);
            http_response_code(500);
            header('Content-Type: '.($json?'application/json':'text/html').'; charset=utf-8');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-Request-Id: '.self::$reference);
        }else{
            // Already-flushed upstream responses cannot have status repaired in PHP.
            error_log('[request_failure] reference='.self::$reference.' boundary=already_sent');
        }
        if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='HEAD'){self::$failureBody='';return;}
        if($json){
            self::$failureBody=(string)json_encode([
                'error'=>'The request could not be completed.',
                'request_id'=>self::$reference,
                'guidance'=>'Check the current status before repeating a payment or other change.',
            ],JSON_UNESCAPED_SLASHES);
            return;
        }
        // A static, dependency-free error page works even when templates or MySQL do not.
        self::$failureBody='<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta name="robots" content="noindex,nofollow"><title>Request could not be completed</title>'
            .'<style>body{margin:0;background:#fdfbf7;color:#2b1712;font:16px/1.65 system-ui,sans-serif;display:grid;min-height:100vh;place-items:center}'
            .'.panel{box-sizing:border-box;width:min(600px,calc(100% - 32px));padding:clamp(24px,6vw,40px);border:1px solid #d7bd72;border-radius:20px;background:#fff}'
            .'h1{font:600 clamp(1.5rem,5vw,2rem)/1.25 Georgia,serif;color:#3a0003}code{overflow-wrap:anywhere;font-size:.85rem}'
            .'nav{display:flex;gap:12px;flex-wrap:wrap}a{display:inline-flex;align-items:center;min-height:44px;color:#3a0003;font-weight:600}a:focus-visible{outline:3px solid #b8860b;outline-offset:3px}</style>'
            .'</head><body><main class="panel"><p>Something interrupted this request</p><h1>We could not finish loading this page.</h1>'
            .'<p>We cannot confirm from this error alone whether an earlier action completed. Please check the current status before repeating a payment, order or other change.</p>'
            .'<p>Contact support with this request reference:</p><p><code>'.self::$reference.'</code></p>'
            .'<nav aria-label="Recovery options"><a href="/">Return to the website</a><a href="/contact">Contact support</a></nav></main></body></html>';
    }
}
