<?php
namespace App\Services;

final class AgentContextService {
    public function __construct(private DatabaseService $store = new DatabaseService(), private SchemaService $schema = new SchemaService()) {}

    public function forUserEmail(string $email, string $message = ''): array {
        $email = strtolower(trim($email));
        return [
            'orders' => $email !== '' ? $this->supportOrders($email, $message) : [],
            'settings' => $this->publicSettings(),
            'site' => $this->siteContext($message),
        ];
    }

    public function forSupportTickets(string $email, ?string $reference, ?string $status, ?array $period = null): array {
        $tickets = (new SupportTicketService($this->store))->forUser($email);
        return self::supportTicketFacts($tickets, $reference, $status, $period);
    }

    /** Only system-generated ticket metadata may be sent to the provider. */
    public static function supportTicketFacts(array $tickets, ?string $reference, ?string $status, ?array $period = null): array {
        usort($tickets, static fn(array $a, array $b): int =>
            (strtotime((string)($b['created_at'] ?? '')) ?: 0) <=> (strtotime((string)($a['created_at'] ?? '')) ?: 0));
        $matches = [];
        $counts = ['open' => 0, 'answered' => 0, 'closed' => 0, 'unknown' => 0];
        foreach ($tickets as $ticket) {
            $id = strtolower((string)($ticket['id'] ?? ''));
            if (!preg_match('/^[a-f0-9]{16}$/D', $id)) continue;
            if ($reference !== null && $id !== $reference) continue;
            $state = strtolower((string)($ticket['status'] ?? ''));
            if (!isset($counts[$state])) $state = 'unknown';
            if ($status !== null && $status !== $state) continue;
            $created = self::recordDate($ticket['created_at'] ?? null);
            if ($period !== null && ($created === null || $created < $period[0] || $created > $period[1])) continue;
            $counts[$state]++;
            $matches[] = [
                'reference' => $id,
                'status' => $state,
                'created_date' => $created,
                'updated_date' => self::recordDate($ticket['updated_at'] ?? null),
            ];
        }
        return ['count' => count($matches), 'counts_by_status' => $counts, 'recent' => array_slice($matches, 0, 5)];
    }

    private static function recordDate(mixed $value): ?string {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:$|[T ])/', $value)) return null;
        $date = substr($value, 0, 10);
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year) ? $date : null;
    }

    public const OWNER_COUNT_ENTITIES = ['users', 'products', 'appointments'];

    public function ownerCount(mixed $entity): array {
        if (!in_array($entity, self::OWNER_COUNT_ENTITIES, true)) return ['error' => 'Choose users, products or appointments.'];
        return ['entity' => $entity, 'count' => count($this->store->read($entity))];
    }

    /** Owner analytics contain aggregate figures only; never include customer identifiers. */
    public function forOwnerAnalytics(): string {
        return self::ownerAnalyticsFromRecords($this->store->read('orders'), count($this->store->read('users')),
            count($this->store->read('products')), count($this->store->read('appointments')), count($this->store->read('support_tickets')));
    }

    public static function ownerAnalyticsFromRecords(array $orders, int $userCount, int $productCount, int $appointmentCount, int $ticketCount): string {
        $confirmed = array_filter($orders, fn($o) => ($o['status'] ?? '') === 'confirmed');
        $pending = array_filter($orders, fn($o) => ($o['status'] ?? '') === 'pending');
        $total = array_sum(array_column($orders, 'total'));
        $units = [];
        $revenue = [];
        foreach ($confirmed as $order) {
            foreach (($order['items'] ?? []) as $item) {
                $name = (string)($item['name'] ?? $item['slug'] ?? '');
                if ($name === '') continue;
                $units[$name] = ($units[$name] ?? 0) + (int)($item['qty'] ?? 1);
                $revenue[$name] = ($revenue[$name] ?? 0) + (float)($item['line_total'] ?? 0);
            }
        }
        arsort($units);
        $top = '';
        foreach (array_slice($units, 0, 5, true) as $name => $count) {
            $top .= "\n  - {$name}: {$count} sold, ₹" . number_format($revenue[$name] ?? 0, 2);
        }
        if ($top === '') $top = "\n  - no confirmed sales yet";
        $orderCount = count($orders);
        return "Site data (aggregate statistics):\n"
            . '- Total users: ' . $userCount . "\n"
            . '- Total orders: ' . $orderCount . ' (confirmed: ' . count($confirmed) . ', pending: ' . count($pending) . ")\n"
            . '- Products: ' . $productCount . "\n"
            . '- Appointments: ' . $appointmentCount . "\n"
            . '- Support tickets: ' . $ticketCount . "\n"
            . '- Total revenue: ₹' . number_format($total, 2) . "\n"
            . '- Confirmed revenue: ₹' . number_format(array_sum(array_column($confirmed, 'total')), 2) . "\n"
            . '- Pending revenue (unconfirmed): ₹' . number_format(array_sum(array_column($pending, 'total')), 2) . "\n"
            . '- Average order value: ₹' . number_format($orderCount ? $total / $orderCount : 0, 2) . "\n"
            . '- Top 5 products by units sold:' . $top;
    }

    /** Share the order state needed for support without delivery addresses or payment identifiers. */
    private function supportOrders(string $email, string $message): array {
        $orders = array_values(array_filter($this->store->read('orders'), static fn(array $order): bool =>
            strtolower(trim((string)($order['customer_email'] ?? ''))) === $email));
        if (preg_match('/\b(?:order\s*#?\s*)?([a-f0-9]{16})\b/i', $message, $match)) {
            $orders = array_values(array_filter($orders, static fn(array $order): bool =>
                strcasecmp((string)($order['id'] ?? ''), $match[1]) === 0));
        }
        usort($orders, static fn(array $a, array $b): int =>
            (strtotime((string)($b['created_at'] ?? '')) ?: 0) <=> (strtotime((string)($a['created_at'] ?? '')) ?: 0));
        $schemaFields = $this->schema->agentContextFields('orders');
        $safeFields = array_flip(array_intersect($schemaFields, ['id', 'status', 'total', 'shipped_at', 'invoice_number', 'invoice_date']));
        return array_map(static function (array $order) use ($safeFields, $schemaFields): array {
            $facts = array_intersect_key($order, $safeFields);
            if (in_array('items', $schemaFields, true)) {
                $facts['items'] = array_map(static fn(array $item): array => [
                    'name' => mb_substr(strip_tags((string)($item['name'] ?? '')), 0, 120),
                    'quantity' => (int)($item['qty'] ?? 0),
                ], array_slice(array_filter((array)($order['items'] ?? []), 'is_array'), 0, 10));
            }
            return $facts;
        }, array_slice($orders, 0, 5));
    }

    private function publicSettings(): array {
        $settings = (new SettingsService($this->store))->public();
        return array_intersect_key($settings, array_flip(['currency', 'timezone', 'shipping_mode', 'flat_rate']));
    }

    /** Match only exact public catalogue aliases, not loose words that can collide. */
    public static function matchesNamedProduct(string $message, array $product): bool
    {
        $normalise = static fn(string $value): string => trim((string)preg_replace('/[^a-z0-9]+/i', ' ', mb_strtolower($value)));
        $haystack = ' ' . $normalise($message) . ' ';
        $aliases = [
            $normalise((string)($product['name'] ?? '')),
            $normalise((string)($product['slug'] ?? '')),
        ];
        foreach (array_unique(array_filter($aliases)) as $alias) {
            // Requiring at least two words avoids treating a generic one-word slug such
            // as "ring" or "paste" as a named-product request.
            if (str_word_count($alias) >= 2 && str_contains($haystack, ' ' . $alias . ' ')) return true;
        }
        return false;
    }

    /**
     * Identify a specific published collection using its own distinctive words.
     * Generic phrases such as "festival products" must never select a random sale.
     */
    public static function requestedCollection(string $message, array $collections): ?array {
        $tokens = static function (string $text): array {
            $text = mb_strtolower($text);
            $text=str_replace(['அய்யப்பன்','ஐயப்பன்','அய்யப்பா','சுவாமி','சாமி','பண்டிகை','திருவிழா','பொருட்கள்','சஷ்டி'],['ayyappa','ayyappa','ayyappa','swamy','swamy','festive','festive','products','sassthi'],$text);
            $text = str_replace(['ayyappan', 'swami', 'festival', 'sashti', 'sasthi'],
                ['ayyappa', 'swamy', 'festive', 'sassthi', 'sassthi'], $text);
            $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            return array_values(array_unique($words));
        };
        $query = $tokens($message);
        $generic = ['sale', 'festive', 'collection', 'special', 'offers'];
        $best = null;
        $score = 0;$tied=false;
        foreach ($collections as $collection) {
            if (!is_array($collection)) continue;
            $name = (string)($collection['name'] ?? '');
            $slug = (string)($collection['slug'] ?? '');
            if ($name === '' || $slug === '') continue;
            $overlap = array_intersect($tokens($name), $query);
            $distinctive = array_diff($overlap, $generic);
            if (count($overlap) < 2 || $distinctive === []) continue;
            $count = count($overlap);
            if ($count > $score) {
                $best = $collection;
                $score = $count;$tied=false;
            } elseif ($count === $score) {$tied=true;}
        }
        return $tied ? null : $best;
    }

    /** Reproducible, bounded catalogue decisions. No match never means the first 20 products. */
    public static function catalogueSelection(array $visibleProducts, string $message, array $collections = []): array {
        $products=array_values(array_filter($visibleProducts,static fn($p):bool=>is_array($p)&&empty($p['is_hidden'])
            &&preg_match('/^[\p{L}\p{M}\p{N} _.-]{1,160}$/uD',(string)($p['slug']??''))));
        $collection=null;$chosen=[];$intent='unknown';$status='not_applicable';$reason='not_a_catalogue_request';
        $collectionQuery=(bool)preg_match('/\b(?:festival|festive|collection|sale)\b|பண்டிகை|திருவிழா|அய்யப்ப|ஐயப்ப|சஷ்டி/iu',$message);
        if(preg_match('/\b(?:my|latest|recent)\s+(?:support\s+)?tickets?\b/i',$message)){$intent='own_ticket';}
        elseif(preg_match('/\b(?:my|latest|recent)\s+(?:order|orders|payment|refund)|\border\s*#\s*[a-f0-9]{16}\b/i',$message)){$intent='own_order';}
        elseif(preg_match('/\b(?:shipping|delivery|dispatch|tracking|refund policy|return policy)\b|விநியோகம்/iu',$message)){$intent='public_policy';}
        elseif($collectionQuery){
            $intent='collection';$collection=self::requestedCollection($message,$collections);
            if($collection!==null){$chosen=(new CategoryService())->collectionProducts($collection,$products);$status=$chosen===[]?'no_match':'matched';$reason='collection_membership';}
            else{$status='clarification';$reason='collection_not_identified';}
        }else{
            $exact=array_values(array_filter($products,static fn(array $p):bool=>self::matchesNamedProduct($message,$p)));
            if(self::isProductComparison($message)){
                $intent='comparison';$chosen=$exact;
                foreach(['pendants?','malas?|malais?','rings?','bracelets?','statues?|idols?'] as $type){
                    $pattern='/\b(?:'.$type.')\b/i';if(!preg_match($pattern,$message))continue;
                    $examples=array_filter($products,static fn(array $p):bool=>(bool)preg_match($pattern,(string)($p['name']??'')));
                    $chosen=array_merge($chosen,array_slice(array_values($examples),0,3));
                }
                $chosen=array_values(array_column($chosen,null,'slug'));
                $status=count($chosen)>=2?'matched':'clarification';$reason='comparison_examples';
            }elseif($exact!==[]){$intent='catalogue_search';$chosen=$exact;$status='matched';$reason='exact_public_alias';}
            elseif(preg_match('/^(?:what (?:do you sell|products? (?:do you (?:sell|have)|are available))|(?:show|list|browse)(?: me)? (?:all |your |the )?(?:products?|catalogue|catalog|shop|items?))[?!. ]*$/iu',trim($message))){
                $intent='catalogue_browse';$chosen=$products;$status=$chosen===[]?'no_match':'matched';$reason='explicit_catalogue_browse';
            }elseif(preg_match('/\b(?:products?|items?|prices?|cost|sell|stock|available|buy|pendants?|rings?|malas?|malais?|bracelets?|statues?|idols?)\b|விலை|பொருட்|மாலை/iu',$message)){
                $intent='catalogue_search';
                $normalize=static function(string $v):array{
                    $v=mb_strtolower($v);$v=str_replace(['pendants','rings','bracelets','malas','malais','statues','idols','ayyappan','swami'],['pendant','ring','bracelet','mala','malai','statue','idol','ayyappa','swamy'],$v);
                    return preg_split('/[^\p{L}\p{N}]+/u',$v,-1,PREG_SPLIT_NO_EMPTY)?:[];
                };
                $stop=['do','does','you','your','we','have','has','a','an','the','can','could','please','show','me','tell','about','how','much','is','are','in','stock','available','price','prices','cost','sell','buy','product','products','item','items','what','of','for','current','and'];
                $terms=array_values(array_diff(array_unique($normalize($message)),$stop));
                if($terms!==[])$chosen=array_values(array_filter($products,static function(array $p)use($terms,$normalize):bool{
                    $words=$normalize((string)($p['name']??'').' '.(string)$p['slug']);return array_diff($terms,$words)===[];
                }));
                $status=$chosen===[]?'no_match':'matched';$reason=$chosen===[]?'no_public_alias_match':'all_query_terms_in_public_name';
            }
        }
        $chosen=array_slice($chosen,0,20);$sources=[];
        foreach($chosen as $product){$updated=$product['updated_at']??null;
            if(!is_string($updated)||!preg_match('/^\d{4}-\d{2}-\d{2}T/',$updated)||strtotime($updated)===false)$updated=null;
            $sources[]=['id'=>'product:'.rawurlencode($product['slug']),'last_updated'=>$updated];
        }
        return ['version'=>1,'intent'=>$intent,'status'=>$status,'reason'=>$reason,'products'=>$chosen,'collection'=>$collection,
            'candidate_count'=>count($products),'result_count'=>count($chosen),'sources'=>$sources,
            'result_sha256'=>hash('sha256',json_encode(array_map(static fn(array $p):array=>array_intersect_key($p,array_flip(['slug','name','price','offer_price','stock_status','updated_at'])),$chosen),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))];
    }

    public static function supportProducts(array $visibleProducts, string $message): array {
        return self::catalogueSelection($visibleProducts,$message)['products'];
    }

    public static function isProductComparison(string $message): bool {
        return (bool)preg_match('/\b(?:compare|comparison|difference between|differ from|versus|vs)\b/i', $message);
    }

    private function siteContext(string $message): array {
        $visibleProducts = (new ProductService($this->store))->visible();
        $collection = null;
        $collectionQuery=(bool)preg_match('/\b(?:festival|festive|collection|sale)\b|பண்டிகை|திருவிழா|அய்யப்ப|ஐயப்ப|சஷ்டி/iu',$message);
        $collections=$collectionQuery?(new CategoryService($this->store))->collections():[];
        $selection=self::catalogueSelection($visibleProducts,$message,$collections);
        $collection=$selection['collection'];$selected=$selection['products'];
        $products = array_map(fn($item) => [
            'name' => $item['name'] ?? '',
            'slug' => $item['slug'] ?? '',
            'url' => '/product/' . rawurlencode((string)($item['slug'] ?? '')),
            'price' => $item['offer_price'] ?? $item['price'] ?? null,
            'stock_status' => $item['stock_status'] ?? '',
            'description' => mb_substr(strip_tags((string)($item['description'] ?? '')), 0, 2000),
            'highlights' => $item['highlights'] ?? [],
            'description_points' => $item['description_points'] ?? [],
            'specifications' => $item['specifications'] ?? [],
        // Visible only, and priced through the offer window: the support bot must never
        // recommend a product the shopper cannot buy, or quote an offer that has ended.
        ], array_slice($selected, 0, 20));
        return [
            'pages' => [
                'shop' => '/shop',
                'cart' => '/cart',
                'checkout' => '/checkout',
                'contact' => '/contact',
                'orders' => '/account/dashboard/orders',
            ],
            'products' => $products,
            'retrieval' => array_diff_key($selection,array_flip(['products','collection'])),
            'collection_query'=>$collectionQuery,
            'available_collections'=>array_slice(array_values(array_filter(array_column($collections,'name'),'is_string')),0,12),
            'matched_collection' => $collection !== null
                ? ['name'=>(string)$collection['name'], 'slug'=>(string)$collection['slug']]
                : null,
            'support_scope' => 'Answer only from this JSON context and public site links. Do not access tools, files, admin data, or other users.',
        ];
    }
}
