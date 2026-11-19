<?php
declare(strict_types=1);
namespace App\Services;

/** Owner-issued local integration credentials. Each grant has one immutable ID and capability. */
final class McpClientService
{
    public const PREFIX = 'mcp-client-';
    public const TOOL = 'paid_sales_summary';
    private const DAYS = [7, 30, 90];

    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function issue(string $label): string
    {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 60 || preg_match('/[[:cntrl:]]/u', $label)) {
            throw new \DomainException('Enter a client name of 1 to 60 characters.');
        }
        $id = self::PREFIX . bin2hex(random_bytes(12));
        $token = $id . '.' . bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable('now');
        $grant = [
            'id'=>$id, 'client_label'=>$label, 'scope'=>self::TOOL, 'digest'=>hash('sha256', $token),
            'issued_at'=>$now->format(DATE_ATOM), 'expires_at'=>$now->modify('+30 days')->format(DATE_ATOM),
            'revoked_at'=>null,
        ];
        if (!$this->store->insertIfAbsent('secrets', $grant)['inserted']) {
            throw new \RuntimeException('Unable to create a unique client grant.');
        }
        (new AuditLogService($this->store))->record('issue', 'mcp-client', $id);
        return $token;
    }

    public function revoke(string $id): void
    {
        if (!preg_match('/^mcp-client-[a-f0-9]{24}$/D', $id)) throw new \DomainException('Unknown client grant.');
        $grant = $this->store->find('secrets', $id);
        if (($grant['scope'] ?? null) !== self::TOOL) throw new \DomainException('Unknown client grant.');
        if (!empty($grant['revoked_at'])) return;
        $grant['digest'] = null;
        $grant['revoked_at'] = date(DATE_ATOM);
        $this->store->upsert('secrets', $grant);
        (new AuditLogService($this->store))->record('revoke', 'mcp-client', $id);
    }

    /** Return display metadata only. Access events never mutate or recreate a revoked credential. */
    public function clients(): array
    {
        $rows = $this->store->query("SELECT id, _data FROM secrets WHERE id LIKE 'mcp-client-%' ORDER BY _created_at DESC, id");
        if (!$rows) return [];
        $lastUsed = array_column($this->store->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(_data, '$.resource_id')) AS client_id,
            MAX(_created_at) AS last_used FROM audit_events
            WHERE JSON_UNQUOTE(JSON_EXTRACT(_data, '$.event')) = 'mcp-client.read' GROUP BY client_id"), 'last_used', 'client_id');
        $clients = [];
        foreach ($rows as $row) {
            $grant = json_decode($row['_data'], true, flags: JSON_THROW_ON_ERROR);
            if (($grant['scope'] ?? null) !== self::TOOL) continue;
            $clients[] = array_intersect_key($grant, array_flip(['client_label','issued_at','expires_at','revoked_at']))
                + ['id'=>$row['id'], 'last_used'=>$lastUsed[$row['id']] ?? null];
        }
        return $clients;
    }

    private function authorizedId(#[\SensitiveParameter] string $token): ?string
    {
        if (!preg_match('/^(mcp-client-[a-f0-9]{24})\.[a-f0-9]{64}$/D', $token, $matches)) return null;
        DatabaseService::clearReadCache();
        $grant = $this->store->find('secrets', $matches[1]);
        if (($grant['scope'] ?? null) !== self::TOOL || !empty($grant['revoked_at'])
            || strtotime((string)($grant['expires_at'] ?? '')) <= time()
            || !hash_equals((string)($grant['digest'] ?? ''), hash('sha256', $token))) return null;
        return $matches[1];
    }

    public function definition(#[\SensitiveParameter] string $token): ?array
    {
        if ($this->authorizedId($token) === null) return null;
        $fields = [
            'from'=>['type'=>'string', 'format'=>'date'], 'to'=>['type'=>'string', 'format'=>'date'],
            'paidOrders'=>['type'=>'integer', 'minimum'=>0], 'grossInr'=>['type'=>'number'],
            'refundedInr'=>['type'=>'number'], 'netInr'=>['type'=>'number'],
        ];
        return [
            'name'=>self::TOOL, 'title'=>'Paid sales summary',
            'description'=>'Read paid order count, gross sales, verified refunds and net sales in INR through today in India.',
            'inputSchema'=>['type'=>'object', 'additionalProperties'=>false, 'properties'=>[
                'days'=>['type'=>'integer', 'enum'=>self::DAYS, 'default'=>30],
            ]],
            'outputSchema'=>['type'=>'object', 'additionalProperties'=>false, 'properties'=>$fields, 'required'=>array_keys($fields)],
            'annotations'=>['readOnlyHint'=>true, 'destructiveHint'=>false, 'idempotentHint'=>true, 'openWorldHint'=>false],
        ];
    }

    public function sales(#[\SensitiveParameter] string $token, array $arguments): ?array
    {
        $id = $this->authorizedId($token);
        if ($id === null) return null;
        $days = ($arguments + ['days'=>30])['days'];
        if (array_diff(array_keys($arguments), ['days']) || !in_array($days, self::DAYS, true)) {
            throw new \DomainException('Choose a period of 7, 30 or 90 days.');
        }
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Kolkata'));
        $from = $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $to = $today->format('Y-m-d');
        $sales = (new OrderService($this->store))->salesInRange($from, $to);
        $audit = (new AuditLogService($this->store))->record('read', 'mcp-client', $id, ['days'=>$days], []);
        if ($audit['status'] !== 'recorded') throw new \RuntimeException('Could not record this access.');
        return [
            'from'=>$from, 'to'=>$to, 'paidOrders'=>array_sum(array_column($sales, 'orders')),
            'grossInr'=>round(array_sum(array_column($sales, 'gross')), 2),
            'refundedInr'=>round(array_sum(array_column($sales, 'refunded')), 2),
            'netInr'=>round(array_sum(array_column($sales, 'net')), 2),
        ];
    }
}
