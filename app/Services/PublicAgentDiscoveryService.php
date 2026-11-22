<?php
declare(strict_types=1);
namespace App\Services;

/** Site-specific HTTP discovery; this is not a standard .well-known manifest. */
final class PublicAgentDiscoveryService
{
    private const HTTP_READS = [
        'search_public_products'=>['path'=>'/api/knowledge/products',
            'parameters'=>['query'=>'q','offset'=>'offset','limit'=>'limit','slugs'=>'slugs[]']],
        'search_public_articles'=>['path'=>'/api/knowledge/articles',
            'parameters'=>['query'=>'q','offset'=>'offset','limit'=>'limit']],
        'get_public_contact'=>['path'=>'/api/knowledge/contact','parameters'=>[]],
        'list_public_services'=>['path'=>'/api/knowledge/services',
            'parameters'=>['query'=>'q','offset'=>'offset','limit'=>'limit']],
    ];

    public function __construct(private ?PublicMcpService $mcp = null) {}

    public function document(): array
    {
        $mcp=$this->mcp??=new PublicMcpService();
        $origin=$mcp->origin();
        $tools=$mcp->toolDefinitions();
        $scopes=[];$httpReads=[];
        foreach($tools as $tool) {
            // Scope is registry-owned metadata, not an inference from readOnlyHint:
            // a read of a private cart is still a capability-scoped operation.
            $access=$tool['_meta']['in.bapx/access']??[];
            $scope=is_string($access['scope']??null)?$access['scope']:'unspecified';
            $authorization=is_string($access['authorization']??null)?$access['authorization']:'follow_tool_contract';
            $key=$scope.'|'.$authorization;
            $scopes[$key]??=['scope'=>$scope,'authorization'=>$authorization,'tools'=>[]];
            $scopes[$key]['tools'][]=$tool['name'];
            if($scope==='public_information' && isset(self::HTTP_READS[$tool['name']])) {
                $read=self::HTTP_READS[$tool['name']];
                $httpReads[]=['tool'=>$tool['name'],'method'=>'GET','url'=>$origin.$read['path'],
                    'queryParameters'=>(object)$read['parameters']];
            }
        }
        $examples=[
            ['id'=>'discover','description'=>'Read this site-specific discovery document.',
                'method'=>'GET','url'=>$origin.'/api/agent-tools'],
            ['id'=>'list_tools','description'=>'Discover the actual MCP tool definitions without browser state.',
                'method'=>'POST','url'=>$origin.'/api/mcp',
                'headers'=>$this->headers('tools/list'),
                'body'=>['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/list',
                    'params'=>['_meta'=>$this->metadata()]]],
        ];
        if(in_array('get_public_contact',array_column($httpReads,'tool'),true)) {
            $examples[]=['id'=>'read_contact_http','description'=>'Read public contact details over ordinary HTTP.',
                'method'=>'GET','url'=>$origin.'/api/knowledge/contact'];
            $examples[]=['id'=>'read_contact_mcp','description'=>'Read public contact details through MCP.',
                'method'=>'POST','url'=>$origin.'/api/mcp',
                'headers'=>$this->headers('tools/call','get_public_contact'),
                'body'=>['jsonrpc'=>'2.0','id'=>2,'method'=>'tools/call','params'=>[
                    'name'=>'get_public_contact','arguments'=>new \stdClass(),'_meta'=>$this->metadata()]]];
        }
        if(in_array('search_public_products',array_column($httpReads,'tool'),true)) {
            $examples[]=['id'=>'search_products_http','description'=>'Search the published catalogue; an empty match stays empty.',
                'method'=>'GET','url'=>$origin.'/api/knowledge/products?q=ring&limit=3'];
        }
        foreach($examples as &$example)$example['curl']=self::curl($example);
        unset($example);
        return [
            'format'=>'bapx.agent-tools','version'=>1,
            'standardsStatus'=>'Site-specific discovery document; not a .well-known or MCP discovery standard.',
            'origin'=>$origin,'discoveryUrl'=>$origin.'/api/agent-tools',
            'guideUrl'=>$origin.'/ai-assistants','instructionsUrl'=>$origin.'/llms.txt',
            'transport'=>['type'=>'mcp','url'=>$origin.'/api/mcp','method'=>'POST',
                'supportedProtocols'=>PublicMcpService::SUPPORTED_PROTOCOLS,
                'contentType'=>'application/json','accept'=>['application/json','text/event-stream'],
                'exampleProtocol'=>PublicMcpService::PROTOCOL,
                'instructions'=>'The examples use the advertised protocol without initialization. Other supported profiles use their own handshake. Server-side HTTP agents do not need browser state; use the required protocol headers and request metadata.'],
            'capabilities'=>array_values($scopes),
            'authorization'=>'Discovery grants no permissions. Each tool enforces its own published scope and input contract. No credentials or cart capability values are included in this document.',
            'tools'=>$tools,'httpReads'=>$httpReads,'examples'=>$examples,
            'presentation'=>[
                'mcpApps'=>'A connected host with MCP Apps support can render the declared UI resource. A normal HTTP client receives structuredContent and readable text. Opening this guide does not install a connector in a chat session.',
                'productUrls'=>'Use productUrl, imageUrl and selected imageUrls from the official configured origin. Existing path, imagePath and imagePaths remain relative for in-site cards. Missing images must not be replaced with invented or search-engine images.',
            ],
        ];
    }

    private function metadata(): array
    {
        return ['io.modelcontextprotocol/protocolVersion'=>PublicMcpService::PROTOCOL,
            'io.modelcontextprotocol/clientCapabilities'=>new \stdClass()];
    }

    private function headers(string $method, ?string $name = null): array
    {
        $headers=['Content-Type'=>'application/json','Accept'=>'application/json, text/event-stream',
            'MCP-Protocol-Version'=>PublicMcpService::PROTOCOL,'Mcp-Method'=>$method];
        if($name!==null)$headers['Mcp-Name']=$name;
        return $headers;
    }

    private static function curl(array $example): string
    {
        $parts=['curl --fail-with-body --silent --show-error'];
        foreach($example['headers']??[] as $name=>$value)$parts[]='  -H '.self::shellQuote($name.': '.$value);
        if(isset($example['body']))$parts[]='  --data-raw '.self::shellQuote(json_encode(
            $example['body'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $parts[]='  '.self::shellQuote($example['url']);
        return implode(" \\\n",$parts);
    }

    private static function shellQuote(string $value): string
    {
        return "'".str_replace("'","'\\''",$value)."'";
    }
}
