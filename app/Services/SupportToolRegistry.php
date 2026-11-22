<?php
namespace App\Services;

/**
 * Support-only tool surface. No arbitrary SQL, file paths, or cross-customer lookup.
 * The caller provides the authenticated actor; model-supplied identities are ignored.
 */
final class SupportToolRegistry {
    private const PUBLIC_POLICIES = [
        'tracking'=>'A tracking link is supplied when the order ships.',
        'returns_or_problems'=>'Use the contact form for return or product problems.',
        'shipping_cost'=>'Shipping is calculated at checkout.',
        'payment_methods'=>'Card and UPI.',
    ];

    public static function policyInputSchema(): array {
        return ['type'=>'object', 'properties'=>['key'=>['type'=>'string', 'enum'=>array_keys(self::PUBLIC_POLICIES)]], 'required'=>['key']];
    }

    public function __construct(private ?array $actor, private ?AgentContextService $context = null) {
        $this->context ??= new AgentContextService();
    }
    public function declarations(): array {
        return [
            ['name'=>'public_catalog', 'description'=>'Read published products using a customer question.',
             'parameters'=>['type'=>'object','properties'=>['question'=>['type'=>'string','minLength'=>1,'maxLength'=>256]],'required'=>['question']]],
            ['name'=>'public_policy', 'description'=>'Read the public shipping, returns, or payment policy.',
             'parameters'=>self::policyInputSchema()],
            ['name'=>'get_public_contact', 'description'=>'Read the published business phone numbers, email, opening hours and contact form link. No customer or owner records.',
             'parameters'=>PublicKnowledgeService::contactInputSchema()],
            ['name'=>'list_public_services', 'description'=>'List the existing public enquiry offerings and contact form destinations. These are enquiries, not booking or payment actions.',
             'parameters'=>PublicKnowledgeService::serviceInputSchema()],
            ['name'=>'own_ticket_status', 'description'=>'Read only the authenticated customer support-ticket metadata.',
             'parameters'=>['type'=>'object','properties'=>[
                'reference'=>['type'=>['string','null'],'pattern'=>'^[a-f0-9]{16}$'],
                'status'=>['type'=>['string','null'],'enum'=>['open','answered','closed',null]],
                'period'=>['type'=>['array','null'],'items'=>['type'=>'string','format'=>'date'],'minItems'=>2,'maxItems'=>2]
             ]]],
        ];
    }
    public function run(string $tool, array $args): array {
        if (!AgentCapabilityPolicy::allows(AgentCapabilityPolicy::SUPPORT, $tool, $this->actor)) {
            return ['error'=>'Not permitted'];
        }
        if ($tool === 'public_catalog') {
            if (array_keys($args) !== ['question'] || !is_string($args['question']) || trim($args['question']) === '' || mb_strlen($args['question']) > 256)
                return ['error'=>'Invalid arguments'];
            return ['products'=>$this->context->forUserEmail('', $args['question'])['site']['products'] ?? []];
        }
        if ($tool === 'public_policy') {
            if (array_keys($args) !== ['key'] || !is_string($args['key'])) return ['error'=>'Invalid arguments'];
            return isset(self::PUBLIC_POLICIES[$args['key']]) ? ['value'=>self::PUBLIC_POLICIES[$args['key']]] : ['error'=>'Invalid policy'];
        }
        if ($tool === 'get_public_contact') {
            return $args === [] ? (new PublicKnowledgeService())->contact() : ['error'=>'Invalid arguments'];
        }
        if ($tool === 'list_public_services') {
            if (array_diff(array_keys($args), ['query', 'offset', 'limit'])
                || (array_key_exists('query', $args) && !is_string($args['query']))
                || (array_key_exists('offset', $args) && !is_int($args['offset']))
                || (array_key_exists('limit', $args) && !is_int($args['limit']))) return ['error'=>'Invalid arguments'];
            try {
                return (new PublicKnowledgeService())->services($args['query'] ?? '', $args['offset'] ?? 0, $args['limit'] ?? 20);
            } catch (\InvalidArgumentException) {
                return ['error'=>'Invalid arguments'];
            }
        }
        if ($tool === 'own_ticket_status') {
            if (array_diff(array_keys($args), ['reference','status','period'])) return ['error'=>'Invalid arguments'];
            $reference = $args['reference'] ?? null;
            $status = $args['status'] ?? null;
            $period = $args['period'] ?? null;
            if ($reference !== null && (!is_string($reference) || !preg_match('/^[a-f0-9]{16}$/D',$reference))) return ['error'=>'Invalid reference'];
            if ($status !== null && !in_array($status,['open','answered','closed'],true)) return ['error'=>'Invalid status'];
            if ($period !== null && (!is_array($period) || count($period)!==2
                || !is_string($period[0]) || !is_string($period[1])
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$period[0])
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$period[1]))) return ['error'=>'Invalid period'];
            if ($period !== null) {
                foreach ($period as $date) {
                    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                    if ($parsed === false || $parsed->format('Y-m-d') !== $date) return ['error'=>'Invalid period'];
                }
                if ($period[0] > $period[1]) return ['error'=>'Invalid period'];
            }
            $email = trim((string)($this->actor['email'] ?? ''));
            return $this->context->forSupportTickets($email, $reference, $status, $period);
        }
        return ['error'=>'Tool unavailable'];
    }
}
