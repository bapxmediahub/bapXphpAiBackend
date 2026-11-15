<?php
namespace App\Services;

/**
 * Support-only tool surface. No arbitrary SQL, file paths, or cross-customer lookup.
 * The caller provides the authenticated actor; model-supplied identities are ignored.
 */
final class SupportToolRegistry {
    public function __construct(private ?array $actor, private ?AgentContextService $context = null) {
        $this->context ??= new AgentContextService();
    }
    public function declarations(): array {
        return [
            ['name'=>'public_catalog', 'description'=>'Read published products using a customer question.',
             'parameters'=>['type'=>'object','properties'=>['question'=>['type'=>'string','minLength'=>1,'maxLength'=>256]],'required'=>['question']]],
            ['name'=>'public_policy', 'description'=>'Read the public shipping, returns, or payment policy.',
             'parameters'=>['type'=>'object','properties'=>['key'=>['type'=>'string','enum'=>['tracking','returns_or_problems','shipping_cost','payment_methods']]],'required'=>['key']]],
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
            $policies = [
                'tracking'=>'A tracking link is supplied when the order ships.',
                'returns_or_problems'=>'Use the contact form for return or product problems.',
                'shipping_cost'=>'Shipping is calculated at checkout.',
                'payment_methods'=>'Card and UPI.',
            ];
            return isset($policies[$args['key']]) ? ['value'=>$policies[$args['key']]] : ['error'=>'Invalid policy'];
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
