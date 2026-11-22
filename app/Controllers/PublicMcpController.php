<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\PublicMcpService;

/** Streamable HTTP framing for public information and explicit anonymous commerce capabilities. */
final class PublicMcpController
{
    /** In-process dependency injection for controller fixtures; never selected by an HTTP parameter. */
    public function __construct(private ?PublicMcpService $service=null) {}

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Vary: Origin');
        try {
            $service=$this->service??new PublicMcpService();
            $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
            // Server-side agents normally send no Origin. Browser callers must be the configured site itself.
            if ($origin!=='' && $origin!==$service->origin()) {
                $this->send(PublicMcpService::error(403,null,-32600,'Origin is not allowed.'));
                return;
            }
            if ($origin!=='') header('Access-Control-Allow-Origin: '.$service->origin());
            $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??''));
            if ($method==='OPTIONS') {
                header('Access-Control-Allow-Methods: POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Accept, MCP-Protocol-Version, Mcp-Method, Mcp-Name');
                header('Allow: POST, OPTIONS');
                http_response_code(204);
                return;
            }
            if ($method!=='POST') {
                header('Allow: POST, OPTIONS');
                $this->send(PublicMcpService::error(405,null,-32600,'Use POST for MCP requests.'));
                return;
            }
            $type=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]));
            if ($type!=='application/json') {
                $this->send(PublicMcpService::error(415,null,-32600,'Use application/json.'));
                return;
            }
            $accepted=[];
            foreach(explode(',',strtolower((string)($_SERVER['HTTP_ACCEPT']??''))) as $part) {
                $pieces=explode(';',trim($part)); $mime=trim(array_shift($pieces)); $quality=1.0;
                foreach($pieces as $option) if (preg_match('/^\s*q=([0-9.]+)\s*$/D',$option,$match)) $quality=(float)$match[1];
                if ($quality>0) $accepted[]=$mime;
            }
            if (!in_array('application/json',$accepted,true) || !in_array('text/event-stream',$accepted,true)) {
                $this->send(PublicMcpService::error(406,null,-32600,'Accept application/json and text/event-stream.'));
                return;
            }
            if ((int)($_SERVER['CONTENT_LENGTH']??0)>PublicMcpService::MAX_REQUEST_BYTES) {
                $this->send(PublicMcpService::error(413,null,-32600,'Request too large.'));
                return;
            }
            $input=fopen('php://input','rb');
            if ($input===false) throw new \RuntimeException();
            try { $body=stream_get_contents($input,PublicMcpService::MAX_REQUEST_BYTES+1); }
            finally { fclose($input); }
            if ($body===false) throw new \RuntimeException();
            $headers=[];
            foreach($_SERVER as $key=>$value) {
                if (str_starts_with($key,'HTTP_') && is_string($value)) $headers[strtolower(str_replace('_','-',substr($key,5)))]=$value;
            }
            $this->send($service->handle($body,$headers));
        } catch (\Throwable $error) {
            error_log('[public_mcp] transport unavailable class='.$error::class);
            $this->send(PublicMcpService::error(503,null,-32603,'Public information is temporarily unavailable.'));
        }
    }

    private function send(array $response): void
    {
        http_response_code($response['status']);
        if ($response['body']!==null && ($_SERVER['REQUEST_METHOD']??'')!=='HEAD') {
            echo json_encode($response['body'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
    }
}
