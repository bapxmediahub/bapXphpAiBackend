<?php
declare(strict_types=1);

use App\Services\{DatabaseService, McpClientService, ProductService, PublicKnowledgeService, SettingsService, SupportToolRegistry};

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$command = $argv[1] ?? 'mcp:stdio';
if (!(($command === 'mcp:stdio' && $argc <= 2) || ($command === 'mcp:tools' && $argc === 2)
    || ($command === 'mcp:policy' && $argc === 3))) {
    fwrite(STDERR, "Usage: ./bapXphp mcp:stdio | mcp:tools | mcp:policy <key>\n");
    exit(2);
}
require dirname(__DIR__) . '/app/bootstrap.php';
session_abort();

// Protocol contracts: https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/stdio
const MCP_REVISION = '2026-07-28';
const MCP_FRAME_LIMIT = 65536;
const MCP_INFO = ['name' => 'bapx-public-shop', 'version' => '1.0.0'];
const SHOP_TOOL = 'search_public_products';
const POLICY_TOOL = 'public_policy';
const ARTICLE_TOOL = 'search_public_articles';

function shopToolDefinition(): array
{
    return [
        'name' => SHOP_TOOL,
        'title' => 'Search public products',
        'description' => 'Search published shop products by keyword. Returns current INR prices, public descriptions, safe local images and store-relative product links in catalogue order.',
        'inputSchema' => PublicKnowledgeService::productInputSchema(),
        'outputSchema' => PublicKnowledgeService::productOutputSchema(),
        'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
    ];
}

function policyToolDefinition(): array
{
    return [
        'name' => POLICY_TOOL,
        'title' => 'Read a store policy',
        'description' => 'Read published guidance about tracking, returns, shipping cost or payment methods.',
        'inputSchema' => SupportToolRegistry::policyInputSchema() + ['additionalProperties' => false],
        'outputSchema' => [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => ['value' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2048]],
            'required' => ['value'],
        ],
        'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
    ];
}

function articleToolDefinition(): array
{
    return [
        'name'=>ARTICLE_TOOL,
        'title'=>'Search published articles and customer guides',
        'description'=>'Search public Markdown articles and customer help, excluding drafts, future or private help. Results are display-safe titles and links only.',
        'inputSchema'=>PublicKnowledgeService::articleInputSchema(),
        'outputSchema'=>PublicKnowledgeService::articleOutputSchema(),
        'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false],
    ];
}

function publicArticles(stdClass $arguments): array
{
    $data=get_object_vars($arguments);
    if (array_diff(array_keys($data), ['query','offset','limit'])
        || !is_string($data['query'] ?? '')
        || !is_int($data['offset'] ?? 0) || !is_int($data['limit'] ?? 5)) {
        return toolFailure('Invalid public article search arguments.');
    }
    try {
        $result=(new PublicKnowledgeService())->articles($data['query'] ?? '', $data['offset'] ?? 0, $data['limit'] ?? 5);
    } catch (\InvalidArgumentException) {
        return toolFailure('Use a query up to 120 characters, offset 0..2147483647 and limit 1..20.');
    }
    $text=json_encode($result, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    if(strlen($text)>24576)return toolFailure('Article results too large; reduce limit.');
    return ['structuredContent'=>$result,'content'=>[['type'=>'text','text'=>$text]]];
}

function availableTools(): array
{
    $modules = (new SettingsService())->modules(requireReadable: true);
    $tools = $modules['shop'] ? [shopToolDefinition(), policyToolDefinition()] : [policyToolDefinition()];
    if ($modules['blog']) $tools[] = articleToolDefinition();
    return $tools;
}

function publicPolicy(stdClass $arguments): array
{
    // A guest actor and a fixed operation keep customer and Enterprise capabilities out of this interface.
    $answer = (new SupportToolRegistry(null))->run(POLICY_TOOL, get_object_vars($arguments));
    if (isset($answer['error'])) return toolFailure('Choose one supported policy key from the input schema.');
    $value = $answer['value'] ?? null;
    if (!is_string($value) || $value === '' || mb_strlen($value) > 2048) return toolFailure('The policy is unavailable.');
    return ['structuredContent' => ['value' => $value], 'content' => [['type' => 'text', 'text' => $value]]];
}

function toolFailure(string $message): array
{
    return ['isError' => true, 'content' => [['type' => 'text', 'text' => $message]]];
}

function searchProducts(stdClass $arguments): array
{
    $values = get_object_vars($arguments);
    $options = $values + ['query' => '', 'offset' => 0, 'limit' => 20];
    ['query' => $query, 'offset' => $offset, 'limit' => $limit] = $options;
    if (array_diff(array_keys($values), ['query', 'offset', 'limit'])
        || !is_string($query) || mb_strlen($query) > 120
        || !is_int($offset) || $offset < 0 || $offset > 2147483647
        || !is_int($limit) || $limit < 1 || $limit > 20) {
        return toolFailure('Use a query of up to 120 characters, a nonnegative offset up to 2147483647 and a limit from 1 to 20.');
    }
    // One published catalogue projection for native MCP and other PHP callers.
    try {
        $output=(new PublicKnowledgeService())->products($query,$offset,$limit);
    } catch (\InvalidArgumentException) {
        return toolFailure('Invalid public catalogue query.');
    }
    $text = json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (strlen($text) > 24576) return toolFailure('This page exceeds the result size limit. Reduce the limit or advance the offset.');
    return ['structuredContent' => $output, 'content' => [['type' => 'text', 'text' => $text]]];
}

function rpcError(int $code, string $message, ?array $data = null): array
{
    $error = ['code' => $code, 'message' => $message];
    if ($data !== null) $error['data'] = $data;
    return ['error' => $error];
}

function dispatchRequest(string $method, mixed $params): array
{
    $meta = $params->_meta ?? null;
    $version = $meta->{'io.modelcontextprotocol/protocolVersion'} ?? null;
    $capabilities = $meta->{'io.modelcontextprotocol/clientCapabilities'} ?? null;
    $info = $meta->{'io.modelcontextprotocol/clientInfo'} ?? null;
    if (!is_string($version) || !($capabilities instanceof stdClass)
        || ($info !== null && (!is_string($info->name ?? null) || !is_string($info->version ?? null)))) {
        return rpcError(-32602, 'Invalid request metadata');
    }
    if ($version !== MCP_REVISION) {
        return rpcError(-32022, 'Unsupported protocol version', ['requested' => $version, 'supported' => [MCP_REVISION]]);
    }
    $base = ['resultType' => 'complete', '_meta' => ['io.modelcontextprotocol/serverInfo' => MCP_INFO]];
    if ($method === 'server/discover') {
        return ['result' => $base + ['supportedVersions' => [MCP_REVISION], 'capabilities' => ['tools' => (object)[]]]];
    }
    if ($method !== 'tools/list' && $method !== 'tools/call') return rpcError(-32601, 'Method not found');
    if ($method === 'tools/list' && property_exists($params, 'cursor')) return rpcError(-32602, 'Invalid cursor');
    if ($method === 'tools/call' && (!in_array($params->name ?? null, [SHOP_TOOL, POLICY_TOOL, ARTICLE_TOOL, McpClientService::TOOL], true)
        || (property_exists($params, 'arguments') && !($params->arguments instanceof stdClass)))) {
        return rpcError(-32602, 'Unknown tool or invalid tool parameters');
    }

    // Web requests get fresh statics automatically. Stdio requests share one process.
    DatabaseService::clearReadCache();
    SettingsService::flushCache();
    if ($method === 'tools/list') {
        $tools = availableTools();
        $private = (new McpClientService())->definition((string)getenv('BAPX_MCP_CLIENT_TOKEN'));
        if ($private !== null) $tools[] = $private;
        return ['result' => $base + ['tools' => $tools, 'ttlMs' => 0, 'cacheScope' => $private === null ? 'public' : 'private']];
    }
    $arguments = $params->arguments ?? (object)[];
    if ($params->name === McpClientService::TOOL) {
        try {
            $summary = (new McpClientService())->sales((string)getenv('BAPX_MCP_CLIENT_TOKEN'), get_object_vars($arguments));
        } catch (DomainException $error) {
            return ['result' => $base + toolFailure($error->getMessage())];
        }
        if ($summary === null) return rpcError(-32602, 'Unknown tool or invalid tool parameters');
        return ['result' => $base + ['structuredContent'=>$summary,
            'content'=>[['type'=>'text', 'text'=>json_encode($summary, JSON_THROW_ON_ERROR)]]]];
    }
    if ($params->name === POLICY_TOOL) return ['result' => $base + publicPolicy($arguments)];
    if ($params->name === ARTICLE_TOOL) {
        if (!(new SettingsService())->modules(requireReadable:true)['blog']) return rpcError(-32602, 'Tool unavailable');
        return ['result'=>$base + publicArticles($arguments)];
    }
    if (!(new SettingsService())->modules(requireReadable: true)['shop']) return rpcError(-32602, 'Tool unavailable');
    return ['result' => $base + searchProducts($arguments)];
}

function handleMessage(mixed $request): ?array
{
    $envelope = ['jsonrpc' => '2.0', 'id' => null];
    if (!($request instanceof stdClass) || ($request->jsonrpc ?? null) !== '2.0' || !is_string($request->method ?? null)) {
        return $envelope + rpcError(-32600, 'Invalid request');
    }
    if (!property_exists($request, 'id')) return null; // Notifications have no reply.
    if (!is_string($request->id) && !is_int($request->id)) return $envelope + rpcError(-32600, 'Invalid request ID');
    $envelope['id'] = $request->id;
    try {
        return $envelope + dispatchRequest($request->method, $request->params ?? null);
    } catch (Throwable) {
        return $envelope + rpcError(-32603, 'Requested capability unavailable');
    } finally {
        DatabaseService::releaseIdleConnection();
    }
}

if ($command !== 'mcp:stdio') {
    try {
        if ($command === 'mcp:tools') {
            $output = ['tools' => availableTools()];
        } else {
            $answer = publicPolicy((object)['key' => $argv[2]]);
            if (!empty($answer['isError'])) {
                fwrite(STDERR, $answer['content'][0]['text'] . "\n");
                exit(2);
            }
            $output = $answer['structuredContent'];
        }
        echo json_encode($output, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    } catch (Throwable) {
        fwrite(STDERR, "Public capabilities are unavailable.\n");
        exit(1);
    }
}

while (($frame = fgets(STDIN, MCP_FRAME_LIMIT + 2)) !== false) {
    if (strlen($frame) > MCP_FRAME_LIMIT && !str_ends_with($frame, "\n")) {
        while (($rest = fgets(STDIN, MCP_FRAME_LIMIT + 2)) !== false && !str_ends_with($rest, "\n")) {}
        $reply = ['jsonrpc' => '2.0', 'id' => null] + rpcError(-32600, 'Request too large');
    } else {
        try {
            $reply = handleMessage(json_decode($frame, false, 32, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            $reply = ['jsonrpc' => '2.0', 'id' => null] + rpcError(-32700, 'Parse error');
        }
    }
    if ($reply !== null) {
        echo json_encode($reply, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES) . "\n";
        fflush(STDOUT);
    }
}
