<?php
declare(strict_types=1);
namespace App\Services;

/** Public knowledge only. This adapter never loads an actor, grant, transcript or private tool registry. */
final class PublicMcpService
{
    // https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http
    public const PROTOCOL = '2026-07-28';
    public const LEGACY_PROTOCOL = '2025-11-25';
    public const SUPPORTED_PROTOCOLS = [self::PROTOCOL,self::LEGACY_PROTOCOL];
    public const MAX_REQUEST_BYTES = 65536;
    public const UI_URI = 'ui://bapx/public-knowledge.html';
    public const UI_MIME = 'text/html;profile=mcp-app';
    public const SURFACE_META = 'in.bapx/publicSurface';
    private const INFO = ['name'=>'bapx-public-knowledge','version'=>'1.0.0'];
    private const INSTRUCTIONS = 'Read only published products, articles, contact details and enquiry options. '
        . 'Search the requested product or topic; an empty result is not permission to substitute unrelated items. Follow nextOffset for search pages. '
        . 'For product detail or comparison, reuse up to three exact raw slugs decoded once from returned /product/ paths in slugs, with an empty query, offset 0 and a limit covering the selection. Missing or unpublished selections are omitted. '
        . 'Use returned INR prices, availability, descriptions, optional images, highlights and specifications without inventing claims. Resolve relative paths against this website origin. '
        . 'A compatible MCP Apps host can render the linked public cards; all tools also return readable text and structured data. '
        . 'Open the product page to purchase or the contact form to send an enquiry. Consultation booking is retired; enquiry options are not bookable appointments. '
        . 'These tools never expose owner reports, customer records, private instructions or conversations, and never submit a form, purchase or booking. Connection guidance is at /ai-assistants; /llms.txt is an advisory guide, not an authorization or automatic installation mechanism.';
    private string $origin;

    public function __construct(
        private ?PublicKnowledgeService $knowledge = null,
        private ?PublicA2uiService $presentation = null,
        ?string $publicUrl = null
    ) {
        $url = parse_url($publicUrl ?? (string)getenv('APP_URL'));
        if (!is_array($url) || ($url['scheme']??'')!=='https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || !in_array($url['path']??'', ['', '/'], true)) {
            throw new \RuntimeException('The public HTTPS site origin is unavailable.');
        }
        $this->origin = 'https://'.strtolower($url['host'])
            .(isset($url['port']) && $url['port']!==443 ? ':'.$url['port'] : '');
    }

    public function origin(): string { return $this->origin; }

    /** Headers are lower-case HTTP field names. The caller owns HTTP I/O and Origin validation. */
    public function handle(string $body, array $headers): array
    {
        if (strlen($body)>self::MAX_REQUEST_BYTES) return self::error(413,null,-32600,'Request too large.');
        try { $request=json_decode($body,false,32,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return self::error(400,null,-32700,'Parse error.'); }
        if (!$request instanceof \stdClass || ($request->jsonrpc??null)!=='2.0'
            || !is_string($request->method??null) || $request->method==='') {
            return self::error(400,null,-32600,'Invalid request.');
        }
        $params=$request->params??null;
        // A legacy profile is explicit: its version header, or an initialize request without modern framing.
        // Never downgrade a malformed modern request into the more permissive handshake-based profile.
        $modernFraming=isset($headers['mcp-method']) || isset($headers['mcp-name'])
            || ($params instanceof \stdClass && ($params->_meta??null) instanceof \stdClass
                && (property_exists($params->_meta,'io.modelcontextprotocol/protocolVersion')
                    || property_exists($params->_meta,'io.modelcontextprotocol/clientCapabilities')));
        $versionHeader=$headers['mcp-protocol-version']??null;
        $legacy=!$modernFraming && ($versionHeader===self::LEGACY_PROTOCOL
            || ($request->method==='initialize' && $versionHeader===null));
        if (!property_exists($request,'id')) {
            // Stateless legacy initialization has no persistent effect. No JSON-RPC notification gets a reply.
            $accepted=$legacy && $request->method==='notifications/initialized'
                && (!property_exists($request,'params') || $params instanceof \stdClass);
            return ['status'=>$accepted ? 202 : 400,'body'=>null];
        }
        $id=$request->id;
        if (!is_int($id) && !is_string($id)) return self::error(400,null,-32600,'Invalid request ID.');
        if ($legacy) return $this->handleLegacy($request,$headers);
        foreach (['mcp-protocol-version','mcp-method'] as $required) {
            if (!isset($headers[$required]) || !is_string($headers[$required]) || $headers[$required]==='') {
                return self::error(400,$id,-32020,'Missing required MCP headers. This endpoint supports '.self::PROTOCOL.'.');
            }
            if (!preg_match('/^[\x21-\x7E]+$/D',$headers[$required])) {
                return self::error(400,$id,-32020,'Malformed MCP header.');
            }
        }
        if (!$params instanceof \stdClass || !($params->_meta??null) instanceof \stdClass) {
            return self::error(400,$id,-32602,'Required request metadata is missing.');
        }
        $meta=$params->_meta;
        $version=$meta->{'io.modelcontextprotocol/protocolVersion'}??null;
        $capabilities=$meta->{'io.modelcontextprotocol/clientCapabilities'}??null;
        $info=$meta->{'io.modelcontextprotocol/clientInfo'}??null;
        if (!is_string($version) || !$capabilities instanceof \stdClass
            || ($info!==null && (!$info instanceof \stdClass || !is_string($info->name??null) || !is_string($info->version??null)))) {
            return self::error(400,$id,-32602,'Invalid request metadata.');
        }
        if ($headers['mcp-protocol-version']!==$version || $headers['mcp-method']!==$request->method) {
            return self::error(400,$id,-32020,'MCP headers do not match the request.');
        }
        if (in_array($request->method,['tools/call','resources/read','prompts/get'],true)) {
            $name=$request->method==='resources/read' ? ($params->uri??null) : ($params->name??null);
            if (!is_string($name) || self::headerName($headers['mcp-name']??null)!==$name) {
                return self::error(400,$id,-32020,'Mcp-Name is missing or does not match the request.');
            }
        }
        if ($version!==self::PROTOCOL) {
            return self::error(400,$id,-32022,'Unsupported protocol version for modern framing.', ['requested'=>$version,'supported'=>self::SUPPORTED_PROTOCOLS]);
        }
        try {
            $result=match($request->method) {
                'server/discover'=>$this->complete([
                    'supportedVersions'=>self::SUPPORTED_PROTOCOLS,
                    'capabilities'=>self::capabilities(),
                    'instructions'=>self::INSTRUCTIONS,
                ],true),
                default=>$this->dispatch($request->method,$params),
            };
            if ($result===null) return self::error(404,$id,-32601,'Method not found. This endpoint supports '.self::PROTOCOL.'.');
            return ['status'=>200,'body'=>['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]];
        } catch (\InvalidArgumentException) {
            return self::error(400,$id,-32602,'Invalid method parameters or unavailable public capability.');
        } catch (\Throwable $error) {
            // Exception text can contain database or provider details. Neither log nor return it here.
            error_log('[public_mcp] unavailable class='.$error::class);
            return self::error(503,$id,-32603,'Public information is temporarily unavailable.');
        } finally {
            DatabaseService::releaseIdleConnection();
        }
    }

    /** A narrow 2025-11-25 Streamable HTTP profile, with no MCP/PHP session or stored client state. */
    private function handleLegacy(\stdClass $request,array $headers): array
    {
        $id=$request->id;
        $params=property_exists($request,'params') ? $request->params : (object)[];
        if (!$params instanceof \stdClass || (property_exists($params,'_meta') && !$params->_meta instanceof \stdClass)) {
            return self::error(400,$id,-32602,'Invalid legacy method parameters.');
        }
        try {
            if ($request->method==='initialize') {
                if (!is_string($params->protocolVersion??null) || $params->protocolVersion===''
                    || !($params->capabilities??null) instanceof \stdClass
                    || !($params->clientInfo??null) instanceof \stdClass
                    || !is_string($params->clientInfo->name??null) || !is_string($params->clientInfo->version??null)
                    || (isset($headers['mcp-protocol-version']) && $headers['mcp-protocol-version']!==$params->protocolVersion)) {
                    throw new \InvalidArgumentException();
                }
                // Legacy negotiation may offer the supported revision when the requested revision differs.
                $result=['protocolVersion'=>self::LEGACY_PROTOCOL,'capabilities'=>self::capabilities(),
                    'serverInfo'=>self::INFO,'instructions'=>self::INSTRUCTIONS];
            } elseif ($request->method==='ping') {
                $result=(object)[];
            } else {
                $result=$this->dispatch($request->method,$params);
                if ($result===null) return self::error(404,$id,-32601,'Method not found for MCP '.self::LEGACY_PROTOCOL.'.');
                // Shared public projections are identical; only the modern wire envelope differs.
                unset($result['resultType'],$result['ttlMs'],$result['cacheScope'],$result['_meta']['io.modelcontextprotocol/serverInfo']);
                if (empty($result['_meta'])) unset($result['_meta']);
            }
            return ['status'=>200,'body'=>['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]];
        } catch (\InvalidArgumentException) {
            return self::error(400,$id,-32602,'Invalid method parameters or unavailable public capability.');
        } catch (\Throwable $error) {
            error_log('[public_mcp] unavailable class='.$error::class);
            return self::error(503,$id,-32603,'Public information is temporarily unavailable.');
        } finally {
            DatabaseService::releaseIdleConnection();
        }
    }

    private function dispatch(string $method,\stdClass $params): ?array
    {
        return match($method) {
            'tools/list'=>$this->listTools($params),
            'tools/call'=>$this->callTool($params),
            'resources/list'=>$this->listResources($params),
            'resources/templates/list'=>$this->emptyTemplates($params),
            'resources/read'=>$this->readResource($params),
            default=>null,
        };
    }

    private static function capabilities(): array
    {
        return ['tools'=>(object)[],'resources'=>(object)[],
            'extensions'=>['io.modelcontextprotocol/ui'=>(object)[]]];
    }

    private static function headerName(mixed $value): ?string
    {
        if (!is_string($value) || $value==='' || trim($value)!==$value || preg_match('/[^\x20-\x7E]/',$value)) return null;
        if (str_starts_with($value,'=?base64?') && str_ends_with($value,'?=')) {
            $decoded=base64_decode(substr($value,9,-2),true);
            return is_string($decoded) && mb_check_encoding($decoded,'UTF-8') ? $decoded : null;
        }
        return $value;
    }

    private function complete(array $result, bool $cacheable=false): array
    {
        $result=['resultType'=>'complete']+$result;
        $result['_meta']=($result['_meta']??[])+['io.modelcontextprotocol/serverInfo'=>self::INFO];
        if ($cacheable) $result+=['ttlMs'=>0,'cacheScope'=>'public'];
        return $result;
    }

    public static function error(int $status, mixed $id, int $code, string $message, ?array $data=null): array
    {
        $body=['jsonrpc'=>'2.0'];
        if (is_int($id) || is_string($id)) $body['id']=$id;
        $body['error']=['code'=>$code,'message'=>$message];
        if ($data!==null) $body['error']['data']=$data;
        return ['status'=>$status,'body'=>$body];
    }

    private function tools(): array
    {
        $modules=(new SettingsService())->modules(requireReadable:true);
        $definitions=[];
        if ($modules['shop']) $definitions[]=['search_public_products','Search published products',
            'Search the published catalogue by query, following nextOffset. Returns current INR prices, availability, descriptions and safe image paths. For exact detail or comparison use up to three raw slugs from returned product paths, with query empty, offset 0 and limit covering the selection; only those published products return, with bounded galleries, highlights and specifications.',
            PublicKnowledgeService::productInputSchema(),PublicKnowledgeService::productOutputSchema()];
        if ($modules['blog']) $definitions[]=['search_public_articles','Search published articles and guides',
            'Find published blog articles and customer guides. Excludes drafts, scheduled and private help.',
            PublicKnowledgeService::articleInputSchema(),PublicKnowledgeService::articleOutputSchema()];
        $definitions[]=['get_public_contact','Read public contact details',
            'Read the business name, public phone numbers, email, hours and contact-page link. Does not send a message.',
            PublicKnowledgeService::contactInputSchema(),PublicKnowledgeService::contactOutputSchema()];
        $definitions[]=['list_public_services','Find public enquiry services',
            'Find the published product, temple, order-help and general enquiry options and their contact-form links. Does not book or submit an enquiry.',
            PublicKnowledgeService::serviceInputSchema(),PublicKnowledgeService::serviceOutputSchema()];
        return array_map(static function(array $definition): array {
            [$name,$title,$description,$input,$output]=$definition;
            $tool=['name'=>$name,'title'=>$title,'description'=>$description,'inputSchema'=>$input,'outputSchema'=>$output,
                'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false]];
            // Metadata is safe public data. Hosts without Apps ignore it and use the meaningful text result.
            // This also supports reference hosts that do not advertise their UI capability at discovery.
            $tool['_meta']=['ui'=>['resourceUri'=>self::UI_URI,'visibility'=>['model','app']]];
            return $tool;
        },$definitions);
    }

    private static function noCursor(\stdClass $params): void
    {
        if (property_exists($params,'cursor')) throw new \InvalidArgumentException();
    }

    private function listTools(\stdClass $params): array
    {
        self::noCursor($params);
        return $this->complete(['tools'=>$this->tools()],true);
    }

    private function callTool(\stdClass $params): array
    {
        $name=$params->name??null;
        if (!is_string($name) || !in_array($name,['search_public_products','search_public_articles','get_public_contact','list_public_services'],true)
            || (property_exists($params,'arguments') && !$params->arguments instanceof \stdClass)) {
            throw new \InvalidArgumentException();
        }
        if (in_array($name,['search_public_products','search_public_articles'],true)) {
            $module=$name==='search_public_products' ? 'shop' : 'blog';
            if (!(new SettingsService())->modules(requireReadable:true)[$module]) throw new \InvalidArgumentException();
        }
        $arguments=get_object_vars($params->arguments??(object)[]);
        $contact=$name==='get_public_contact';$products=$name==='search_public_products';
        $query=$arguments['query']??''; $offset=$arguments['offset']??0; $limit=$arguments['limit']??5;
        $slugs=$arguments['slugs']??[];
        $allowed=$contact ? [] : ($products?['query','offset','limit','slugs']:['query','offset','limit']);
        if (array_diff(array_keys($arguments),$allowed) || !is_string($query) || !mb_check_encoding($query,'UTF-8') || mb_strlen($query)>120
            || !is_int($offset) || $offset<0 || $offset>2147483647 || !is_int($limit) || $limit<1 || $limit>20
            || !is_array($slugs)
            || array_filter($arguments,static fn(mixed $value):bool=>$value===null)) {
            return $this->complete(['isError'=>true,'content'=>[['type'=>'text','text'=>$contact
                ? 'Contact details take no arguments.' : 'Use query up to 120 characters, offset 0..2147483647 and limit 1..20.']]]);
        }
        $source=$this->knowledge??=new PublicKnowledgeService();
        $view=match($name) {
            'search_public_products'=>'products', 'search_public_articles'=>'articles',
            'get_public_contact'=>'contact', 'list_public_services'=>'services',
        };
        try {
            $data=$contact ? $source->contact() : ($products
                ? $source->products($query,$offset,$limit,$slugs) : $source->{$view}($query,$offset,$limit));
        } catch (\InvalidArgumentException) {
            return $this->complete(['isError'=>true,'content'=>[['type'=>'text',
                'text'=>'Use valid search arguments, or up to three unique exact product slugs with an empty query, offset 0 and a limit covering the selection.']]]);
        }
        $result=['structuredContent'=>$data, 'content'=>[['type'=>'text','text'=>$this->textAnswer($view,$data)]]];
        try {
            $layout=$products&&count($slugs)>1?'compare':($products&&count($slugs)===1?'detail':'auto');
            $surface=($this->presentation??=new PublicA2uiService($source))->fromResult($view,$data,4,$layout);
            $result['_meta']=[self::SURFACE_META=>$surface];
        } catch (\Throwable $error) {
            // Presentation is progressive enhancement; retain the verified public data and text.
            error_log('[public_mcp] presentation unavailable class='.$error::class);
        }
        if (strlen(json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))>65536) {
            return $this->complete(['isError'=>true,'content'=>[['type'=>'text','text'=>'Results are too large. Reduce limit and try again.']]]);
        }
        return $this->complete($result);
    }

    private function textAnswer(string $view, array $data): string
    {
        // Include an absolute source origin so plain MCP clients can use the relative canonical paths.
        return 'Published '.$view.' from '.$this->origin.'. Relative paths refer to this website.'."\n"
            .json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    private function resourceDefinition(): array
    {
        return ['uri'=>self::UI_URI,'name'=>'public-knowledge','title'=>'Public products and information',
            'description'=>'Read-only cards for published products, articles, contact details and services.', 'mimeType'=>self::UI_MIME];
    }

    private function listResources(\stdClass $params): array
    {
        self::noCursor($params);
        return $this->complete(['resources'=>[$this->resourceDefinition()]],true);
    }

    private function emptyTemplates(\stdClass $params): array
    {
        self::noCursor($params);
        return $this->complete(['resourceTemplates'=>[]],true);
    }

    private function readResource(\stdClass $params): array
    {
        if (($params->uri??null)!==self::UI_URI) throw new \InvalidArgumentException();
        $publicOrigin=$this->origin;
        $renderer=file_get_contents(app_path('assets/a2ui-public-renderer.js'));
        $styles=file_get_contents(app_path('assets/css/a2ui-public.css'));
        $bridge=file_get_contents(app_path('assets/public-mcp-app.js'));
        if ($renderer===false || $styles===false || $bridge===false) throw new \RuntimeException();
        ob_start();
        try { require app_path('views/public/mcp-app.php'); $html=(string)ob_get_contents(); }
        finally { ob_end_clean(); }
        return $this->complete(['contents'=>[['uri'=>self::UI_URI,'mimeType'=>self::UI_MIME,'text'=>$html,
            '_meta'=>['ui'=>['prefersBorder'=>true,'csp'=>['connectDomains'=>[], 'resourceDomains'=>[$publicOrigin], 'frameDomains'=>[]]]]]]],true);
    }
}
