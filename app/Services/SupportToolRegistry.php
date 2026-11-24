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

    public function __construct(private ?array $actor, private ?AgentContextService $context = null, private DatabaseService $store = new DatabaseService()) {
        $this->context ??= new AgentContextService($this->store);
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
            ['name'=>'public_catalogue_summary','description'=>'Count all currently published products and their configured public category memberships. Use for overall catalogue counts and category overview, not a named product or private order.',
             'parameters'=>['type'=>'object','properties'=>(object)[]]],
            ['name'=>'public_best_sellers','description'=>'Get up to four currently visible storefront best sellers by recorded paid, non-cancelled, non-refunded orders, all time only. No sales amounts, private orders or date filters.',
             'parameters'=>['type'=>'object','properties'=>(object)[]]],
            PublicKnowledgeService::agentArticleDeclaration(),
        ];
    }
    /** Project only distinct published identities and configured public categories. */
    public static function catalogueSummary(array $products,array $categories): array {
        $labels=[];$counts=[];$seen=[];
        foreach($categories as $category) {
            if(!is_array($category)||!empty($category['is_collection']))continue;
            $slug=$category['slug']??null;$name=$category['name']??null;
            if(!is_string($slug)||$slug===''||!is_string($name)||trim($name)==='')continue;
            $labels[$slug]=mb_substr(trim(strip_tags($name)),0,120);
        }
        foreach($products as $product) {
            if(!is_array($product)||!empty($product['is_hidden'])||!is_string($product['slug']??null)
                ||PublicKnowledgeService::productPath($product['slug'])===null||isset($seen[$product['slug']]))continue;
            $seen[$product['slug']]=true;
            $members=[];
            foreach(array_merge([$product['category']??null],(array)($product['categories']??[])) as $category) {
                if(is_string($category)&&isset($labels[$category])&&$labels[$category]!=='')$members[$category]=true;
            }
            if(!$members)$counts['Uncategorised']=($counts['Uncategorised']??0)+1;
            else {
                $names=[];foreach($members as $slug=>$_)$names[$labels[$slug]]=true;
                foreach($names as $name=>$_)$counts[$name]=($counts[$name]??0)+1;
            }
        }
        uksort($counts,'strnatcasecmp');$rows=[];
        foreach(array_slice($counts,0,30,true) as $name=>$count)$rows[]=['name'=>(string)$name,'count'=>$count];
        return ['total'=>count($seen),'categories'=>$rows,'category_count'=>count($counts),'more_categories'=>max(0,count($counts)-30),
            'membership_note'=>'A product can belong to more than one category.'];
    }

    /** Public ranking only: never send order identifiers, payment data or revenue. */
    public static function publicRankedProducts(array $products): array {
        $rows=[];$seen=[];
        foreach($products as $product) {
            if(!is_array($product)||!empty($product['is_hidden'])||!is_string($product['slug']??null)||isset($seen[$product['slug']]))continue;
            $path=PublicKnowledgeService::productPath($product['slug']);
            if($path===null||!is_string($product['name']??null)||trim($product['name'])==='')continue;
            $seen[$product['slug']]=true;
            $rows[]=['name'=>mb_substr(trim(strip_tags($product['name'])),0,160),'slug'=>$product['slug'],'url'=>$path];
            if(count($rows)>=4)break;
        }
        return $rows;
    }

    public function run(string $tool, array $args): array {
        if (!AgentCapabilityPolicy::allows(AgentCapabilityPolicy::SUPPORT, $tool, $this->actor)) {
            return ['error'=>'Not permitted'];
        }
        if ($tool === 'search_articles') return (new PublicKnowledgeService())->searchArticlesForAgent($args);
        if (in_array($tool,['public_catalogue_summary','public_best_sellers'],true)) {
            if($args!==[])return ['error'=>'Invalid arguments'];
            if(!(new SettingsService($this->store))->modules(requireReadable:true)['shop'])return ['error'=>'Public shop unavailable'];
            $products=new ProductService($this->store);
            $visible=$products->visible();
            if($tool==='public_catalogue_summary')return self::catalogueSummary($visible,(new CategoryService($this->store))->all());
            $ranked=self::publicRankedProducts($products->bestSellers($visible,4));
            return ['products'=>$ranked,'count'=>count($ranked),'period'=>'all_time','basis'=>'recorded paid, non-cancelled, non-refunded orders'];
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
