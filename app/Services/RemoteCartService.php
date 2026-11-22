<?php
declare(strict_types=1);
namespace App\Services;

/** Anonymous capability-scoped carts, independent of MCP/browser sessions and customer accounts. */
final class RemoteCartService
{
    public const CART_TTL = 86400;
    public const CHECKOUT_TTL = 900;
    public const MAX_LINES = 20;
    public const MAX_REVISION = 1000000;
    private const TOKEN = '/^cart_([a-f0-9]{32})\.([a-f0-9]{64})$/D';
    private const TICKET = '/^checkout_([a-f0-9]{32})\.([a-f0-9]{64})$/D';
    private ProductService $products;
    private PublicKnowledgeService $knowledge;

    public function __construct(
        private DatabaseService $store = new DatabaseService(),
        ?ProductService $products = null,
        ?PublicKnowledgeService $knowledge = null,
        private ?\Closure $operation = null,
        private ?string $publicUrl = null,
    ) {
        $this->products = $products ?? new ProductService($store);
        $this->knowledge = $knowledge ?? new PublicKnowledgeService($this->products,publicUrl:$publicUrl);
    }

    public static function cartInputSchema(): array
    {
        return ['type'=>'object','additionalProperties'=>false,'required'=>['operation'],'properties'=>[
            'operation'=>['type'=>'string','enum'=>['create','get','set_quantity']],
            'cartToken'=>['type'=>'string','pattern'=>'^cart_[a-f0-9]{32}\\.[a-f0-9]{64}$',
                'description'=>'Secret returned by create; grants only this anonymous cart and expires after 24 hours. Keep it private.'],
            'slug'=>['type'=>'string','minLength'=>1,'maxLength'=>2039,
                'description'=>'Exact raw slug decoded once from a published product path.'],
            'quantity'=>['type'=>'integer','minimum'=>0,'maximum'=>CartService::MAX_QUANTITY,
                'description'=>'Absolute quantity; zero removes the item. Never increment on retry.'],
            'expectedRevision'=>['type'=>'integer','minimum'=>0,'maximum'=>self::MAX_REVISION,
                'description'=>'Current cart revision, required for set_quantity. Reload after a conflict.'],
        ]];
    }

    public static function cartOutputSchema(): array
    {
        $item=['type'=>'object','additionalProperties'=>false,'required'=>[
            'slug','name','path','productUrl','quantity','unitPriceInr','lineTotalInr','available'],
            'properties'=>[
                'slug'=>['type'=>'string','maxLength'=>2039], 'name'=>['type'=>'string','maxLength'=>160],
                'path'=>['type'=>'string','maxLength'=>2048], 'productUrl'=>['type'=>'string','format'=>'uri','maxLength'=>4096],
                'quantity'=>['type'=>'integer','minimum'=>1,'maximum'=>CartService::MAX_QUANTITY],
                'unitPriceInr'=>['type'=>'number','minimum'=>0,'maximum'=>1000000000],
                'lineTotalInr'=>['type'=>'number','minimum'=>0,'maximum'=>99000000000],
                'available'=>['type'=>'boolean'], 'imagePath'=>['type'=>'string','maxLength'=>512],
                'imageUrl'=>['type'=>'string','format'=>'uri','maxLength'=>2560],
            ]];
        return ['type'=>'object','additionalProperties'=>false,'required'=>[
            'status','revision','expiresAt','currency','items','itemCount','estimatedTotalInr','unavailableSlugs','notice','replayed'],
            'properties'=>[
                'cartToken'=>self::cartInputSchema()['properties']['cartToken'],
                'status'=>['type'=>'string','enum'=>['open','handed_off','checkout_locked']],
                'revision'=>['type'=>'integer','minimum'=>0,'maximum'=>self::MAX_REVISION],
                'expiresAt'=>['type'=>'string','format'=>'date-time'], 'currency'=>['type'=>'string','const'=>'INR'],
                'items'=>['type'=>'array','maxItems'=>self::MAX_LINES,'items'=>$item],
                'itemCount'=>['type'=>'integer','minimum'=>0,'maximum'=>self::MAX_LINES*CartService::MAX_QUANTITY],
                'estimatedTotalInr'=>['type'=>['number','null'],'minimum'=>0,'maximum'=>1980000000000],
                'unavailableSlugs'=>['type'=>'array','maxItems'=>self::MAX_LINES,'items'=>['type'=>'string','maxLength'=>2039]],
                'notice'=>['type'=>'string','maxLength'=>500], 'replayed'=>['type'=>'boolean'],
                'checkout'=>RemoteCheckoutService::recoverySchema(),
            ]];
    }

    public static function checkoutInputSchema(): array
    {
        $fields=self::cartInputSchema()['properties'];
        return ['type'=>'object','additionalProperties'=>false,'required'=>['cartToken','expectedRevision'],
            'properties'=>['cartToken'=>$fields['cartToken'],'expectedRevision'=>$fields['expectedRevision']]];
    }

    public static function checkoutOutputSchema(): array
    {
        return ['type'=>'object','additionalProperties'=>false,
            'required'=>['checkoutUrl','expiresAt','cartRevision','cart','replayed','notice'], 'properties'=>[
                'checkoutUrl'=>['type'=>'string','format'=>'uri','maxLength'=>2560],
                'expiresAt'=>['type'=>'string','format'=>'date-time'],
                'cartRevision'=>['type'=>'integer','minimum'=>0,'maximum'=>self::MAX_REVISION],
                'cart'=>self::cartOutputSchema(), 'replayed'=>['type'=>'boolean'],
                'notice'=>['type'=>'string','maxLength'=>500],
            ]];
    }

    private function operate(string $operation, array $payload): array
    {
        return $this->operation !== null ? ($this->operation)($operation,$payload)
            : $this->store->remoteCartOperation($operation,$payload);
    }

    private static function exactKeys(array $args, array $required, array $optional=[]): void
    {
        if (array_diff(array_keys($args),array_merge($required,$optional)) || array_diff($required,array_keys($args))) {
            throw new RemoteCartException('invalid_arguments','Use only the required arguments for this cart operation.');
        }
    }

    private static function credential(mixed $value, bool $ticket=false): array
    {
        if (!is_string($value) || !preg_match($ticket?self::TICKET:self::TOKEN,$value,$matches)) {
            throw new RemoteCartException('invalid_capability','Use the complete secret capability returned for this cart.',400);
        }
        return ['id'=>$matches[1],'secret'=>$matches[2]];
    }

    private static function revision(mixed $value): int
    {
        if (!is_int($value) || $value<0 || $value>self::MAX_REVISION) {
            throw new RemoteCartException('invalid_revision','Use the current integer cart revision.');
        }
        return $value;
    }

    private function requireShop(): void
    {
        if (!(new SettingsService($this->store))->modules(requireReadable:true)['shop']) {
            throw new RemoteCartException('shop_unavailable','Shopping is currently unavailable.',503);
        }
    }

    public function shoppingCart(array $args): array
    {
        $operation=$args['operation']??null;
        if (!is_string($operation) || !in_array($operation,['create','get','set_quantity'],true)) {
            throw new RemoteCartException('invalid_operation','Choose create, get or set_quantity.');
        }
        $required=match($operation) {
            'create'=>['operation'], 'get'=>['operation','cartToken'],
            default=>['operation','cartToken','slug','quantity','expectedRevision'],
        };
        self::exactKeys($args,$required);
        $this->requireShop();
        if ($operation==='create') {
            $id=bin2hex(random_bytes(16));$secret=bin2hex(random_bytes(32));
            $saved=$this->operate('create',['id'=>$id,'capabilityHash'=>hash('sha256',$secret),
                // Only the server's peer address is used, never a client-selected forwarding header.
                'creationKey'=>hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'))]);
            return ['cartToken'=>'cart_'.$id.'.'.$secret]+$this->project($saved);
        }
        $credential=self::credential($args['cartToken']);
        $identity=['id'=>$credential['id'],'capabilityHash'=>hash('sha256',$credential['secret'])];
        if ($operation==='get') {
            $saved=$this->operate('get',$identity);$cart=$this->project($saved);
            $recovery=RemoteCheckoutService::cartRecovery($saved['record'],$credential['secret']);
            if($recovery!==null)$cart['checkout']=$recovery;
            return $cart;
        }
        $quantity=$args['quantity'];$slug=$args['slug'];
        if (!is_string($slug) || PublicKnowledgeService::productPath($slug)===null
            || !is_int($quantity) || $quantity<0 || $quantity>CartService::MAX_QUANTITY) {
            throw new RemoteCartException('invalid_quantity','Use a published raw product slug and an integer quantity from 0 to 99.');
        }
        $expected=self::revision($args['expectedRevision']);
        // Authenticate before catalogue work; a supplied cart ID never selects a browser cart.
        $this->operate('get',$identity);
        if ($quantity>0) {
            $published=$this->knowledge->products('',0,1,[$slug])['products'];
            if (count($published)!==1 || !$published[0]['available'] || $published[0]['priceInr']>1000000000) {
                throw new RemoteCartException('product_unavailable','This published product is unavailable for this cart.',409);
            }
        }
        return $this->project($this->operate('set_quantity',$identity+[
            'slug'=>$slug,'quantity'=>$quantity,'expectedRevision'=>$expected]));
    }

    public function prepareCheckout(array $args): array
    {
        self::exactKeys($args,['cartToken','expectedRevision']);$this->requireShop();
        $credential=self::credential($args['cartToken']);$expected=self::revision($args['expectedRevision']);
        $identity=['id'=>$credential['id'],'capabilityHash'=>hash('sha256',$credential['secret'])];
        $current=$this->operate('get',$identity);
        if ($current['record']['revision']!==$expected) self::conflict();
        $cart=$this->project($current);self::requireCheckoutCart($cart);
        $expiry=min(self::timestamp($current['record']['expires_at']),$current['now']+self::CHECKOUT_TTL);
        $ticketSecret=self::ticketSecret($credential['secret'],$credential['id'],$expected,$expiry);
        $saved=$this->operate('prepare_checkout',$identity+['expectedRevision'=>$expected,
            'ticketHash'=>hash('sha256',$ticketSecret),'ticketExpiresAt'=>$expiry]);
        $receipt=$saved['record']['handoff'];
        // Concurrent/repeated preparation reuses the stored receipt and its original expiry.
        $ticketSecret=self::ticketSecret($credential['secret'],$credential['id'],$expected,$receipt['expires_at']);
        if (!hash_equals($receipt['token_hash'],hash('sha256',$ticketSecret))) {
            throw new RemoteCartException('checkout_unavailable','A checkout link could not be prepared. Reload the cart.',503);
        }
        return ['checkoutUrl'=>$this->origin().'/cart/agent-checkout?ticket=checkout_'.$credential['id'].'.'.$ticketSecret,
            'expiresAt'=>gmdate('Y-m-d\TH:i:s\Z',$receipt['expires_at']), 'cartRevision'=>$expected,
            'cart'=>$cart, 'replayed'=>$saved['replayed'],
            'notice'=>'Open this separate 15-minute review link. It changes no browser cart until the shopper confirms replacement. No order, payment or stock reservation has been created.'];
    }

    private static function ticketSecret(string $secret,string $id,int $revision,int $expiry): string
    {
        return hash_hmac('sha256','checkout:'.$id.':'.$revision.':'.$expiry,$secret);
    }

    private function origin(): string
    {
        // #326 supplies the shared strict configured-origin helper before this slice is merged.
        return PublicSiteUrlService::origin($this->publicUrl);
    }

    private function project(array $saved): array
    {
        $record=$saved['record'];$lines=$record['lines'];$known=[];
        foreach(array_chunk(array_column($lines,'slug'),3) as $slugs) {
            foreach($this->knowledge->products('',0,count($slugs),$slugs)['products'] as $product) {
                $known[rawurldecode(substr($product['path'],9))]=$product;
            }
        }
        $items=[];$unavailable=[];$total=0.0;$count=0;
        foreach($lines as $line) {
            $slug=$line['slug'];$quantity=$line['qty'];$count+=$quantity;$product=$known[$slug]??null;
            if ($product===null || $product['priceInr']>1000000000) { $unavailable[]=$slug;continue; }
            $price=round((float)$product['priceInr'],2);$lineTotal=round($price*$quantity,2);$total+=$lineTotal;
            $item=['slug'=>$slug,'name'=>$product['name'],'path'=>$product['path'],
                'productUrl'=>$product['productUrl']??$this->origin().$product['path'],
                'quantity'=>$quantity,'unitPriceInr'=>$price,'lineTotalInr'=>$lineTotal,'available'=>$product['available']];
            if (isset($product['imagePath'])) {
                $item['imagePath']=$product['imagePath'];$item['imageUrl']=$product['imageUrl']??$this->origin().$product['imagePath'];
            }
            if (!$product['available']) $unavailable[]=$slug;
            $items[]=$item;
        }
        return ['status'=>$record['status'],'revision'=>$record['revision'],
            'expiresAt'=>gmdate('Y-m-d\TH:i:s\Z',self::timestamp($record['expires_at'])),'currency'=>'INR',
            'items'=>$items,'itemCount'=>$count,'estimatedTotalInr'=>$unavailable===[]?round($total,2):null,
            'unavailableSlugs'=>$unavailable,'replayed'=>$saved['replayed'],
            'notice'=>$record['status']==='checkout_locked'
                ?'Current catalogue estimate in INR. This cart has a confirmed checkout; use its status receipt for the confirmed amount, payment and saved-order facts. No stock reservation is implied.'
                :'Current product-price estimate in INR, including applicable product GST. Final delivery and discounts are reviewed at checkout. Cart operations do not reserve stock or confirm payment. Unavailable selections must be removed before checkout.'];
    }

    /** Shared projection for an already authenticated, database-locked checkout record. */
    public function checkoutProjection(array $saved):array { return $this->project($saved); }

    private static function requireCheckoutCart(array $cart): void
    {
        if ($cart['status']!=='open') throw new RemoteCartException('cart_handed_off','This cart has already been handed to the shopper.',409);
        if ($cart['items']===[] || $cart['unavailableSlugs']!==[] || !is_numeric($cart['estimatedTotalInr']) || $cart['estimatedTotalInr']<=0) {
            throw new RemoteCartException('checkout_unavailable','Checkout requires a nonempty cart of currently available, priced products.',409);
        }
    }

    /** Browser controller only: ticket and server session binding grant no account access. */
    public function previewCheckout(string $ticket,string $browserBinding): array
    {
        $this->requireShop();$credential=self::credential($ticket,true);
        $saved=$this->operate('preview_checkout',['id'=>$credential['id'],'ticketHash'=>hash('sha256',$credential['secret']),
            'browserBinding'=>$browserBinding]);
        $cart=$this->project($saved);
        return ['cart'=>$cart,'quote'=>self::quote($cart),'expiresAt'=>gmdate('Y-m-d\TH:i:s\Z',$saved['record']['handoff']['expires_at']),
            'alreadyImported'=>$saved['record']['status']==='handed_off'];
    }

    /** Claim first, then replace browser lines. A same-session retry recovers an interrupted session save. */
    public function claimCheckout(string $ticket,string $browserBinding,string $quote): array
    {
        $preview=$this->previewCheckout($ticket,$browserBinding);$cart=$preview['cart'];
        if (!preg_match('/^[a-f0-9]{64}$/D',$quote) || !hash_equals(self::quote($cart),$quote)) {
            throw new RemoteCartException('quote_changed','Product prices or availability changed. Review this cart again before continuing.',409);
        }
        if (!$preview['alreadyImported']) self::requireCheckoutCart($cart);
        $credential=self::credential($ticket,true);
        $saved=$this->operate('claim_checkout',['id'=>$credential['id'],'ticketHash'=>hash('sha256',$credential['secret']),
            'browserBinding'=>$browserBinding,'expectedRevision'=>$cart['revision']]);
        return ['lines'=>$saved['record']['lines'],'replayed'=>$saved['replayed']];
    }

    private static function quote(array $cart): string
    {
        return hash('sha256',json_encode(['revision'=>$cart['revision'],'items'=>$cart['items'],
            'unavailable'=>$cart['unavailableSlugs'],'total'=>$cart['estimatedTotalInr']],JSON_THROW_ON_ERROR));
    }

    private static function timestamp(string $value): int
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new \DateTimeZone('UTC'));
        if ($date===false || $date->format('Y-m-d H:i:s')!==$value) throw new \RuntimeException('Invalid stored cart time.');
        return $date->getTimestamp();
    }

    private static function conflict(): never
    {
        throw new RemoteCartException('cart_conflict','The cart changed. Get the current cart and review its revision before updating.',409);
    }

    /** Pure state machine; DatabaseService calls this while holding the cart row lock. */
    public static function transition(?array $record,string $operation,array $args,int $now): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',(string)($args['id']??''))) throw new RemoteCartException('invalid_capability','Invalid cart capability.');
        if ($operation==='create') {
            if ($record!==null) throw new RemoteCartException('cart_conflict','A new cart could not be created. Try again.',409);
            foreach(['capabilityHash','creationKey'] as $key) {
                if (!preg_match('/^[a-f0-9]{64}$/D',(string)($args[$key]??''))) throw new RemoteCartException('invalid_capability','Invalid cart creation request.');
            }
            $record=['id'=>$args['id'],'capability_hash'=>$args['capabilityHash'],'creation_key'=>$args['creationKey'],
                'status'=>'open','revision'=>0,'lines'=>[],'created_at'=>gmdate('Y-m-d H:i:s',$now),
                'updated_at'=>gmdate('Y-m-d H:i:s',$now),'expires_at'=>gmdate('Y-m-d H:i:s',$now+self::CART_TTL)];
            return ['record'=>$record,'replayed'=>false,'now'=>$now];
        }
        $ticket=in_array($operation,['preview_checkout','claim_checkout'],true);
        $stored=$ticket?($record['handoff']['token_hash']??''):($record['capability_hash']??'');
        $provided=$args[$ticket?'ticketHash':'capabilityHash']??null;
        if ($record===null || !is_string($provided) || !preg_match('/^[a-f0-9]{64}$/D',$provided)
            || !is_string($stored) || $stored==='' || !hash_equals($stored,$provided)) {
            throw new RemoteCartException('cart_not_found','This cart or checkout capability is unknown or no longer available.',404);
        }
        if (self::timestamp($record['expires_at'])<=$now) throw new RemoteCartException('cart_expired','This anonymous cart expired. Create a new cart.',410);
        $replayed=false;
        if ($ticket) {
            if (!is_string($args['browserBinding']??null) || !preg_match('/^[a-f0-9]{64}$/D',$args['browserBinding'])) {
                throw new RemoteCartException('invalid_browser','A browser session is required to review checkout.');
            }
            $handoff=$record['handoff'];
            if (!is_int($handoff['expires_at']??null) || $handoff['expires_at']<=$now) {
                throw new RemoteCartException('checkout_expired','This checkout review link expired. Request a new link.',410);
            }
            if (($handoff['revision']??null)!==$record['revision']) self::conflict();
            if (isset($handoff['claimed_session_hash'])) {
                if (!hash_equals($handoff['claimed_session_hash'],$args['browserBinding'])) {
                    throw new RemoteCartException('checkout_already_used','This checkout link was already used in another browser.',409);
                }
                $replayed=true;
            } elseif ($operation==='claim_checkout') {
                if (self::revision($args['expectedRevision']??null)!==$record['revision']) self::conflict();
                $record['handoff']['claimed_session_hash']=$args['browserBinding'];
                $record['handoff']['claimed_at']=$now;$record['status']='handed_off';
            }
        } elseif ($operation==='get') {
            // Read does not extend expiry or change an existing checkout receipt.
        } elseif ($record['status']==='checkout_locked') {
            throw new RemoteCartException('checkout_locked','This checkout was confirmed. Check its payment status before starting a different cart.',409);
        } elseif ($record['status']!=='open') {
            throw new RemoteCartException('cart_handed_off','This cart has already been handed to the shopper.',409);
        } elseif ($operation==='set_quantity') {
            $expected=self::revision($args['expectedRevision']??null);$slug=$args['slug']??null;$quantity=$args['quantity']??null;
            if (!is_string($slug) || PublicKnowledgeService::productPath($slug)===null || !is_int($quantity)
                || $quantity<0 || $quantity>CartService::MAX_QUANTITY) throw new RemoteCartException('invalid_quantity','Invalid cart quantity or product.');
            $fingerprint=hash('sha256',json_encode([$expected,$slug,$quantity],JSON_THROW_ON_ERROR));
            if ($expected!==$record['revision']) {
                if (($record['last_change']['fingerprint']??null)!==$fingerprint) self::conflict();
                $replayed=true;
            } else {
                // MySQL normalizes JSON object-key order. Compare the known line
                // fields, so a same-quantity write cannot invalidate a live quote.
                $currentLines=array_map(static fn(array $line):array=>['slug'=>$line['slug'],'qty'=>$line['qty']],$record['lines']);
                $lines=[];$found=false;
                foreach($currentLines as $line) {
                    if ($line['slug']===$slug) {$found=true;if($quantity>0)$lines[]=['slug'=>$slug,'qty'=>$quantity];}
                    else $lines[]=$line;
                }
                if (!$found && $quantity>0) $lines[]=['slug'=>$slug,'qty'=>$quantity];
                if (count($lines)>self::MAX_LINES) throw new RemoteCartException('cart_limit','A cart can contain at most 20 distinct products.',409);
                if ($lines!==$currentLines) {
                    if ($record['revision']>=self::MAX_REVISION) throw new RemoteCartException('cart_limit','Create a new cart to continue.',409);
                    $record['lines']=$lines;$record['revision']++;
                    $record['last_change']=['fingerprint'=>$fingerprint];unset($record['handoff']);
                    unset($record['checkout']['quote']);
                } else $replayed=true;
            }
        } elseif ($operation==='prepare_checkout') {
            if (self::revision($args['expectedRevision']??null)!==$record['revision']) self::conflict();
            if ($record['lines']===[]) throw new RemoteCartException('checkout_unavailable','Add an available product before preparing checkout.',409);
            if (isset($record['handoff']) && $record['handoff']['expires_at']>$now && $record['handoff']['revision']===$record['revision']) $replayed=true;
            else {
                $expiry=$args['ticketExpiresAt']??null;$hash=$args['ticketHash']??null;
                if (!is_int($expiry) || $expiry<=$now || $expiry>$now+self::CHECKOUT_TTL || $expiry>self::timestamp($record['expires_at'])
                    || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash)) {
                    throw new RemoteCartException('invalid_checkout','Invalid checkout review receipt.');
                }
                $record['handoff']=['token_hash'=>$hash,'expires_at'=>$expiry,'revision'=>$record['revision']];
            }
        } else throw new RemoteCartException('invalid_operation','Unknown cart operation.');
        if (!in_array($operation,['get','preview_checkout'],true) && !$replayed) $record['updated_at']=gmdate('Y-m-d H:i:s',$now);
        return ['record'=>$record,'replayed'=>$replayed,'now'=>$now];
    }
}
