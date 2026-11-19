<?php
declare(strict_types=1);

use App\Services\{DatabaseService, ProductService, SettingsService};

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 1) {
    fwrite(STDERR, "Usage: ./bapXphp mcp:stdio\n");
    exit(2);
}
require dirname(__DIR__) . '/app/bootstrap.php';
session_abort();

// Protocol contracts: https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/stdio
const MCP_REVISION = '2026-07-28';
const MCP_FRAME_LIMIT = 65536;
const MCP_INFO = ['name' => 'bapx-public-shop', 'version' => '1.0.0'];
const SHOP_TOOL = 'search_public_products';

function toolDefinition(): array
{
    $product = [
        'name' => ['type' => 'string'],
        'path' => ['type' => 'string'],
        'priceInr' => ['type' => 'number'],
        'available' => ['type' => 'boolean'],
    ];
    return [
        'name' => SHOP_TOOL,
        'title' => 'Search public products',
        'description' => 'Search published shop products by keyword. Returns current prices in INR and store-relative product links in catalogue order.',
        'inputSchema' => [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => [
                'query' => ['type' => 'string', 'maxLength' => 120],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
            ],
        ],
        'outputSchema' => [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => [
                'products' => ['type' => 'array', 'maxItems' => 20, 'items' => [
                    'type' => 'object', 'properties' => $product,
                    'required' => array_keys($product), 'additionalProperties' => false,
                ]],
                'total' => ['type' => 'integer', 'minimum' => 0],
                'nextOffset' => ['type' => ['integer', 'null']],
            ],
            'required' => ['products', 'total', 'nextOffset'],
        ],
        'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
    ];
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
    $service = new ProductService();
    $matches = $service->discover($service->visible(), $query, '');
    $products = [];
    foreach (array_slice($matches, $offset, $limit) as $item) {
        $products[] = [
            'name' => (string)($item['name'] ?? ''),
            'path' => '/product/' . rawurlencode((string)($item['slug'] ?? '')),
            'priceInr' => $service->priceOf($item),
            'available' => in_array($item['stock_status'] ?? 'in_stock', ['in_stock', 'active'], true),
        ];
    }
    $next = $offset + count($products);
    $output = ['products' => $products, 'total' => count($matches), 'nextOffset' => $next < count($matches) ? $next : null];
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
    if ($method === 'tools/call' && (($params->name ?? null) !== SHOP_TOOL
        || (property_exists($params, 'arguments') && !($params->arguments instanceof stdClass)))) {
        return rpcError(-32602, 'Unknown tool or invalid tool parameters');
    }

    // Web requests get fresh statics automatically. Stdio requests share one process.
    DatabaseService::clearReadCache();
    SettingsService::flushCache();
    $shopEnabled = (new SettingsService())->modules(requireReadable: true)['shop'];
    if ($method === 'tools/list') {
        return ['result' => $base + ['tools' => $shopEnabled ? [toolDefinition()] : [], 'ttlMs' => 0, 'cacheScope' => 'public']];
    }
    if (!$shopEnabled) return rpcError(-32602, 'Tool unavailable');
    return ['result' => $base + searchProducts($params->arguments ?? (object)[])];
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
        return $envelope + rpcError(-32603, 'Catalogue unavailable');
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
