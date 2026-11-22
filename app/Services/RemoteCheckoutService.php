<?php
declare(strict_types=1);
namespace App\Services;

use App\Integrations\Razorpay\RazorpayClient;

/** Anonymous checkout, shared by HTTP agents and the fixed in-chat form. No browser/account state. */
final class RemoteCheckoutService
{
    public const QUOTE_TTL=600;
    public const PAYMENT_TTL=1800;
    public const STATUS_TTL=604800;
    // Razorpay Standard Payment Links require at least INR1 (100paise).
    public const MIN_AMOUNT_PAISE=100;
    public const MAX_AMOUNT_PAISE=198000000000000;
    public const TAX_SETTINGS=['gst_state','default_gst_rate','default_hsn_code','gst_legal_name','gst_trade_name','gstin','gst_address','gst_state_code'];
    private ProductService $products;
    private RemoteCartService $carts;
    private int $now=0;

    /** Closures are in-process test ports, never request-selected URLs, keys or data providers. */
    public function __construct(
        private DatabaseService $store=new DatabaseService(),
        ?ProductService $products=null,
        ?PublicKnowledgeService $knowledge=null,
        private ?\Closure $operation=null,
        private ?\Closure $gateway=null,
        private ?\Closure $settings=null,
        private ?\Closure $secrets=null,
        private ?\Closure $persist=null,
        private ?string $publicUrl=null,
    ) {
        $this->products=$products??new ProductService($store);
        $this->carts=new RemoteCartService($store,$this->products,$knowledge,publicUrl:$publicUrl);
    }

    public static function deliverySchema():array
    {
        return ['type'=>'object','additionalProperties'=>false,
            'required'=>['name','email','phone','address','city','pincode','state'], 'properties'=>[
                'name'=>['type'=>'string','minLength'=>1,'maxLength'=>120],
                'email'=>['type'=>'string','format'=>'email','maxLength'=>254],
                'phone'=>['type'=>'string','minLength'=>1,'maxLength'=>30],
                'address'=>['type'=>'string','minLength'=>1,'maxLength'=>500],
                'city'=>['type'=>'string','minLength'=>1,'maxLength'=>100],
                'pincode'=>['type'=>'string','pattern'=>'^[0-9]{6}$'],
                'state'=>['type'=>'string','enum'=>TaxService::states()],
                'gstin'=>['type'=>'string','pattern'=>'^(?:[A-Z0-9]{15})?$'],
            ]];
    }

    public static function reviewInputSchema():array
    {
        $schema=RemoteCartService::checkoutInputSchema();
        $schema['properties']['delivery']=self::deliverySchema();
        $schema['properties']['couponCode']=['type'=>'string','maxLength'=>40,'description'=>'Optional coupon. An empty string clears the existing coupon.'];
        return $schema;
    }

    public static function recoverySchema():array
    {
        return ['type'=>'object','additionalProperties'=>false,'required'=>['attemptId','paymentToken','statusExpiresAt'],
            'properties'=>['attemptId'=>['type'=>'string','pattern'=>'^[a-f0-9]{32}$'],
                'paymentToken'=>self::statusInputSchema()['properties']['paymentToken'],
                'statusExpiresAt'=>['type'=>'string','format'=>'date-time']]];
    }

    public static function reviewOutputSchema():array
    {
        $money=['type'=>'integer','minimum'=>0,'maximum'=>self::MAX_AMOUNT_PAISE];
        $tax=['type'=>['object','null'],'additionalProperties'=>false,'required'=>['gstPaise','cgstPaise','sgstPaise','igstPaise'],
            'properties'=>array_fill_keys(['gstPaise','cgstPaise','sgstPaise','igstPaise'],$money)];
        return ['type'=>'object','additionalProperties'=>false,
            'required'=>['stage','cartRevision','cart','deliveryComplete','missingFields','deliveryRegion','couponApplied','totals','quote','payment','notice'],
            'properties'=>[
                'stage'=>['type'=>'string','enum'=>['details_required','review_ready','payment_preparing','payment_ready','payment_uncertain','completed']],
                'cartRevision'=>RemoteCartService::cartInputSchema()['properties']['expectedRevision'],
                'cart'=>RemoteCartService::cartOutputSchema(),
                'deliveryComplete'=>['type'=>'boolean'],
                'missingFields'=>['type'=>'array','maxItems'=>7,'items'=>['type'=>'string','enum'=>['name','email','phone','address','city','pincode','state']]],
                'deliveryRegion'=>['type'=>['string','null'],'maxLength'=>60], 'couponApplied'=>['type'=>'boolean'],
                'totals'=>['type'=>'object','additionalProperties'=>false,
                    'required'=>['currency','itemsPaise','discountPaise','shippingPaise','totalPaise','taxIncluded','tax'],
                    'properties'=>['currency'=>['type'=>'string','const'=>'INR'],'itemsPaise'=>$money,'discountPaise'=>$money,
                        'shippingPaise'=>['type'=>'integer','const'=>0],'totalPaise'=>$money,'taxIncluded'=>['type'=>'boolean','const'=>true],'tax'=>$tax]],
                'quote'=>['type'=>['object','null'],'additionalProperties'=>false,'required'=>['id','expiresAt'],
                    'properties'=>['id'=>['type'=>'string','pattern'=>'^quote_[a-f0-9]{32}$'],'expiresAt'=>['type'=>'string','format'=>'date-time']]],
                'payment'=>['type'=>'object','additionalProperties'=>false,'required'=>['available','provider','mode','presentation'],
                    'properties'=>['available'=>['type'=>'boolean'],'provider'=>['type'=>'string','const'=>'razorpay'],
                        'mode'=>['type'=>'string','enum'=>['test','live','unavailable']],
                        'presentation'=>['type'=>'string','enum'=>['external_provider','unavailable']]]],
                'notice'=>['type'=>'string','maxLength'=>500],
            ]];
    }

    public static function preparePaymentInputSchema():array
    {
        $schema=RemoteCartService::checkoutInputSchema();
        $schema['required']=array_merge($schema['required'],['quoteId','confirm']);
        $schema['properties']['quoteId']=['type'=>'string','pattern'=>'^quote_[a-f0-9]{32}$'];
        $schema['properties']['confirm']=['type'=>'boolean','const'=>true,'description'=>'True only after the shopper explicitly approves this exact quote, delivery details and total.'];
        return $schema;
    }

    public static function preparePaymentOutputSchema():array
    {
        return ['type'=>'object','additionalProperties'=>false,
            'required'=>['stage','quoteId','attemptId','paymentToken','statusExpiresAt','provider','mode','amountPaise','currency','notice'],
            'properties'=>[
                'stage'=>['type'=>'string','enum'=>['payment_preparing','payment_ready','payment_uncertain','completed']],
                'quoteId'=>['type'=>'string','pattern'=>'^quote_[a-f0-9]{32}$'],
                'attemptId'=>['type'=>'string','pattern'=>'^[a-f0-9]{32}$'],
                'paymentToken'=>self::statusInputSchema()['properties']['paymentToken'],
                'statusExpiresAt'=>['type'=>'string','format'=>'date-time'],
                'provider'=>['type'=>'string','const'=>'razorpay'],'mode'=>['type'=>'string','enum'=>['test','live']],
                'amountPaise'=>['type'=>'integer','minimum'=>self::MIN_AMOUNT_PAISE,'maximum'=>self::MAX_AMOUNT_PAISE],
                'currency'=>['type'=>'string','const'=>'INR'],
                'paymentUrl'=>['type'=>'string','format'=>'uri','maxLength'=>2560],
                'paymentExpiresAt'=>['type'=>'string','format'=>'date-time'],'notice'=>['type'=>'string','maxLength'=>500],
            ]];
    }

    public static function statusInputSchema():array
    {
        return ['type'=>'object','additionalProperties'=>false,'required'=>['paymentToken'],'properties'=>[
            'paymentToken'=>['type'=>'string','pattern'=>'^payment_[a-f0-9]{32}\\.[a-f0-9]{64}$',
                'description'=>'Private status-only capability returned for one confirmed checkout; valid for seven days. It cannot change delivery, pay or access an account.'],
        ]];
    }

    public static function statusOutputSchema():array
    {
        return ['type'=>'object','additionalProperties'=>false,
            'required'=>['paymentStatus','orderStatus','amountPaise','currency','statusExpiresAt','checkedAt','notice'],
            'properties'=>[
                'paymentStatus'=>['type'=>'string','enum'=>['pending','authorized','captured','failed','expired','cancelled','unknown']],
                'orderStatus'=>['type'=>'string','enum'=>['not_created','saved','save_pending']],
                'amountPaise'=>['type'=>'integer','minimum'=>self::MIN_AMOUNT_PAISE,'maximum'=>self::MAX_AMOUNT_PAISE],
                'currency'=>['type'=>'string','const'=>'INR'], 'statusExpiresAt'=>['type'=>'string','format'=>'date-time'],
                'checkedAt'=>['type'=>'string','format'=>'date-time'],
                'orderReference'=>['type'=>'string','maxLength'=>128], 'notice'=>['type'=>'string','maxLength'=>500],
            ]];
    }

    private function operate(string $operation,array $payload):array
    {
        $result=$this->operation!==null?($this->operation)($operation,$payload):$this->store->remoteCheckoutOperation($operation,$payload);
        if(is_int($result['now']??null))$this->now=$result['now'];
        return $result;
    }

    private static function exactKeys(array $args,array $required,array $optional=[]):void
    {
        if(array_diff(array_keys($args),array_merge($required,$optional))||array_diff($required,array_keys($args))) {
            throw new RemoteCartException('invalid_arguments','Use only the documented fields for this checkout operation.');
        }
    }

    public static function credential(mixed $token,string $prefix):array
    {
        if(!is_string($token)||!in_array($prefix,['cart','payment','pay'],true)
            ||!preg_match('/^'.$prefix.'_([a-f0-9]{32})\.([a-f0-9]{64})$/D',$token,$matches)) {
            throw new RemoteCartException('invalid_capability','Use the complete private capability returned for this operation.');
        }
        return ['id'=>$matches[1],'secret'=>$matches[2]];
    }

    private static function revision(mixed $value):int
    {
        if(!is_int($value)||$value<0||$value>RemoteCartService::MAX_REVISION)throw new RemoteCartException('invalid_revision','Use the current integer cart revision.');
        return $value;
    }

    private function requireShop():void
    {
        if(!(new SettingsService($this->store))->modules(requireReadable:true)['shop'])throw new RemoteCartException('shop_unavailable','Shopping is currently unavailable.',503);
    }

    private function taxSettings():array
    {
        $all=$this->settings!==null?($this->settings)():(new SettingsService($this->store))->public();
        // Freeze the same actual defaults used by TaxService, with numeric rates.
        $result=['gst_state'=>(string)($all['gst_state']??'Tamil Nadu'),
            'default_gst_rate'=>TaxService::rateFor([],$all),'default_hsn_code'=>TaxService::hsnFor([],$all)];
        foreach(['gst_legal_name','gst_trade_name','gstin','gst_address','gst_state_code'] as $key)$result[$key]=(string)($all[$key]??'');
        return $result;
    }

    private function secretValues():array { return $this->secrets!==null?($this->secrets)():(new SecretService($this->store))->all(); }

    private function paymentAvailability():array
    {
        $secrets=$this->secretValues();$mode=$secrets['razorpay_mode']??'test';
        if(!in_array($mode,['test','live'],true))$mode='test';
        // SecretService normalizes legacy keys into their selected mode. Availability
        // and provider execution must require the same exact normalized pair.
        $available=trim((string)($secrets['razorpay_'.$mode.'_key_id']??''))!==''
            &&trim((string)($secrets['razorpay_'.$mode.'_key_secret']??''))!==''
            &&trim((string)($secrets['razorpay_'.$mode.'_webhook_secret']??''))!=='';
        return ['available'=>$available,'provider'=>'razorpay','mode'=>$available?$mode:'unavailable',
            'presentation'=>$available?'external_provider':'unavailable'];
    }

    private static function delivery(mixed $input):array
    {
        if(!is_array($input))throw new RemoteCartException('invalid_delivery','Submit complete delivery details before reviewing checkout.');
        $required=['name','email','phone','address','city','pincode','state'];self::exactKeys($input,$required,['gstin']);
        $bounds=['name'=>120,'email'=>254,'phone'=>30,'address'=>500,'city'=>100,'pincode'=>6,'state'=>60,'gstin'=>15];$result=[];
        foreach($bounds as $field=>$max) {
            $value=$input[$field]??'';
            if(!is_string($value)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)
                ||!preg_match('//u',$value)||mb_strlen(trim($value))>$max||($field!=='gstin'&&trim($value)==='')) {
                throw new RemoteCartException('invalid_delivery','Check the delivery '.$field.' field.');
            }
            $result[$field]=trim($value);
        }
        if(!filter_var($result['email'],FILTER_VALIDATE_EMAIL)||!AuthService::validPhone($result['phone'])
            ||!preg_match('/^[0-9]{6}$/D',$result['pincode']))throw new RemoteCartException('invalid_delivery','Enter a valid email, phone and six-digit delivery PIN code.');
        $result['state']=TaxService::normaliseState($result['state'])??throw new RemoteCartException('invalid_delivery','Choose a supported Indian delivery state.');
        $result['email']=strtolower($result['email']);$result['gstin']=strtoupper($result['gstin']);
        if($result['gstin']!==''&&!preg_match('/^[A-Z0-9]{15}$/D',$result['gstin']))throw new RemoteCartException('invalid_delivery','Enter a 15-character GSTIN or leave it empty.');
        return $result;
    }

    private static function coupon(mixed $code):string
    {
        if(!is_string($code)||strlen($code)>40||!preg_match('/^[A-Za-z0-9_ -]*$/D',$code))throw new RemoteCartException('invalid_coupon','Enter a coupon code of at most 40 letters, numbers, spaces, hyphens or underscores.');
        return strtoupper(trim($code));
    }

    /** Canonical, key-order-independent digest: MySQL JSON may reorder object keys. */
    public static function digest(mixed $value):string
    {
        $normalize=static function(mixed $data)use(&$normalize):mixed {
            if(!is_array($data))return $data;
            if(!array_is_list($data))ksort($data,SORT_STRING);
            foreach($data as $key=>$item)$data[$key]=$normalize($item);
            return $data;
        };
        return hash('sha256',json_encode($normalize($value),JSON_THROW_ON_ERROR));
    }

    private function draft(array $record,array $delivery,string $coupon,int $revision,int $now):array
    {
        $cart=$this->carts->checkoutProjection(['record'=>$record,'now'=>$now,'replayed'=>false]);
        if($cart['items']===[]||$cart['unavailableSlugs']!==[]||!is_numeric($cart['estimatedTotalInr'])||$cart['estimatedTotalInr']<=0) {
            throw new RemoteCartException('checkout_unavailable','Review requires a nonempty cart of currently available products.',409);
        }
        $resolved=(new CartService($this->products))->resolve($record['lines']);
        if(count($resolved)!==count($record['lines']))throw new RemoteCartException('quote_changed','A selected product changed. Review the current cart.',409);
        $settings=$this->taxSettings();$items=[];
        foreach($resolved as $line) {
            $items[]=['slug'=>$line['slug'],'name'=>$line['name'],'qty'=>$line['qty'],
                'unit_price'=>$line['unit_price'],'line_total'=>$line['line_total'],
                'gst_rate'=>TaxService::rateFor($line['product'],$settings),'hsn_code'=>TaxService::hsnFor($line['product'],$settings)];
        }
        $itemsPaise=(int)round(CartService::total($resolved)*100);$discount=0.0;
        if($coupon!=='') {
            try{$discount=(new CouponService($this->store))->apply($coupon,$itemsPaise/100,$delivery['email']??'',new \DateTimeImmutable('@'.$now))['discount'];}
            catch(\InvalidArgumentException $error){throw new RemoteCartException('coupon_unavailable',$error->getMessage(),409);}
        }
        $discountPaise=(int)round($discount*100);$total=$itemsPaise-$discountPaise;
        if($total<self::MIN_AMOUNT_PAISE||$total>self::MAX_AMOUNT_PAISE)throw new RemoteCartException('checkout_amount','This payment checkout requires a payable total of at least ₹1.',409);
        $tax=null;$snapshot=null;
        if($delivery!==[]) {
            try{$snapshot=(new TaxService())->snapshot($items,$discountPaise/100,$delivery['state'],$settings);}
            catch(\InvalidArgumentException){throw new RemoteCartException('checkout_configuration','The merchant must finish its tax configuration before payment.',503);}
            $tax=['gstPaise'=>(int)round($snapshot['tax_total']*100),'cgstPaise'=>(int)round($snapshot['cgst_total']*100),
                'sgstPaise'=>(int)round($snapshot['sgst_total']*100),'igstPaise'=>(int)round($snapshot['igst_total']*100)];
        }
        $totals=['currency'=>'INR','itemsPaise'=>$itemsPaise,'discountPaise'=>$discountPaise,'shippingPaise'=>0,
            'totalPaise'=>$total,'taxIncluded'=>true,'tax'=>$tax];
        $pending=['customer_name'=>$delivery['name']??'','customer_email'=>$delivery['email']??'','customer_phone'=>$delivery['phone']??'',
            'shipping_address'=>$delivery['address']??'','shipping_city'=>$delivery['city']??'','shipping_pincode'=>$delivery['pincode']??'',
            'shipping_state'=>$delivery['state']??'','customer_gstin'=>$delivery['gstin']??'',
            'items'=>$items,'discount'=>$discountPaise/100,'coupon_code'=>$coupon,'total'=>$total/100,'tax_settings'=>$settings];
        $digest=self::digest(['revision'=>$revision,'pending'=>$pending,'totals'=>$totals]);
        return ['delivery'=>$delivery,'coupon_code'=>$coupon,'totals'=>$totals,'digest'=>$digest,'pending_order'=>$pending];
    }

    public function reviewCheckout(array $args):array
    {
        self::exactKeys($args,['cartToken','expectedRevision'],['delivery','couponCode']);$this->requireShop();
        $credential=self::credential($args['cartToken'],'cart');$expected=self::revision($args['expectedRevision']);
        $identity=['id'=>$credential['id'],'capabilityHash'=>hash('sha256',$credential['secret'])];
        $saved=$this->operate('read_cart',$identity);$record=$saved['record'];
        if($record['revision']!==$expected)throw new RemoteCartException('cart_conflict','The cart changed. Get its current revision before reviewing checkout.',409);
        if(isset($record['checkout_receipt'])) {
            if(isset($args['delivery'])||array_key_exists('couponCode',$args))throw new RemoteCartException('checkout_locked','This checkout was confirmed. Check its payment status before starting a different cart.',409);
            return $this->reviewProjection($saved,$credential['secret']);
        }
        if($record['status']!=='open')throw new RemoteCartException('checkout_locked','This cart has already been handed to a checkout.',409);
        $delivery=array_key_exists('delivery',$args)?self::delivery($args['delivery']):($record['checkout']['delivery']??[]);
        $coupon=array_key_exists('couponCode',$args)?self::coupon($args['couponCode']):($record['checkout']['coupon_code']??'');
        $changed=self::digest([$delivery,$coupon])!==self::digest([$record['checkout']['delivery']??[],$record['checkout']['coupon_code']??'']);
        $revision=$expected+($changed?1:0);
        if($revision>RemoteCartService::MAX_REVISION)throw new RemoteCartException('cart_limit','Create a new cart to continue.',409);
        $draft=$this->draft($record,$delivery,$coupon,$revision,$saved['now']);
        $draft['quote']=$delivery===[]?null:['id'=>'quote_'.bin2hex(random_bytes(16)),'revision'=>$revision,
            'expires_at'=>min(strtotime($record['expires_at'].' UTC'),$saved['now']+self::QUOTE_TTL),'digest'=>$draft['digest']];
        $saved=$this->operate('save_review',$identity+['expectedRevision'=>$expected,'draft'=>$draft]);
        return $this->reviewProjection($saved,$credential['secret']);
    }

    private function reviewProjection(array $saved,string $cartSecret):array
    {
        $record=$saved['record'];$checkout=$record['checkout']??[];$quote=$checkout['quote']??null;
        $cart=$this->carts->checkoutProjection($saved);$recovery=self::cartRecovery($record,$cartSecret);
        if($recovery!==null)$cart['checkout']=$recovery;
        $attempt=$saved['attempt']??null;$delivery=$checkout['delivery']??[];
        return ['stage'=>$attempt!==null?self::stage($attempt):($quote!==null?'review_ready':'details_required'),
            'cartRevision'=>$record['revision'],'cart'=>$cart,'deliveryComplete'=>$delivery!==[],
            'missingFields'=>$delivery===[]?['name','email','phone','address','city','pincode','state']:[],
            'deliveryRegion'=>$delivery['state']??null,'couponApplied'=>($checkout['coupon_code']??'')!=='',
            'totals'=>$checkout['totals'],
            'quote'=>$quote===null?null:['id'=>$quote['id'],'expiresAt'=>gmdate('Y-m-d\TH:i:s\Z',$quote['expires_at'])],
            'payment'=>$this->paymentAvailability(),
            'notice'=>$attempt!==null?'This checkout was already confirmed. Use its status handle; do not create another payment attempt.':
                ($quote!==null?'Review this exact INR total and delivery details before confirming. Product GST is included; current shipping charge is zero. Payment takes place only with the provider after confirmation.':'Enter delivery details to review tax and the final total. No order or payment has been created.')];
    }

    public static function statusSecret(string $cartSecret,string $id,int $expires):string
    { return hash_hmac('sha256','checkout-status:'.$id.':'.$expires,$cartSecret); }
    private static function handoffSecret(string $cartSecret,string $id,int $expires):string
    { return hash_hmac('sha256','payment-handoff:'.$id.':'.$expires,$cartSecret); }

    /** Recovery contains no payment claims, personal data or gateway identifiers. */
    public static function cartRecovery(array $record,string $cartSecret):?array
    {
        $receipt=$record['checkout_receipt']??null;
        if(!is_array($receipt))return null;
        $secret=self::statusSecret($cartSecret,$receipt['attempt_id'],$receipt['status_expires_at']);
        if(!hash_equals($receipt['status_hash'],hash('sha256',$secret)))throw new RemoteCartException('checkout_unavailable','Checkout recovery is temporarily unavailable.',503);
        return ['attemptId'=>$receipt['attempt_id'],'paymentToken'=>'payment_'.$receipt['attempt_id'].'.'.$secret,
            'statusExpiresAt'=>gmdate('Y-m-d\TH:i:s\Z',$receipt['status_expires_at'])];
    }

    public function preparePayment(array $args):array
    {
        self::exactKeys($args,['cartToken','expectedRevision','quoteId','confirm']);$this->requireShop();
        $credential=self::credential($args['cartToken'],'cart');$expected=self::revision($args['expectedRevision']);
        if(($args['confirm']??null)!==true||!is_string($args['quoteId'])||!preg_match('/^quote_[a-f0-9]{32}$/D',$args['quoteId'])) {
            throw new RemoteCartException('confirmation_required','Explicitly confirm the exact current quote before preparing payment.');
        }
        $identity=['id'=>$credential['id'],'capabilityHash'=>hash('sha256',$credential['secret'])];
        $saved=$this->operate('read_cart',$identity);$record=$saved['record'];
        if(isset($saved['attempt'])) {
            $attempt=$saved['attempt'];
            if($attempt['quote_id']!==$args['quoteId']||$attempt['cart_revision']!==$expected)throw new RemoteCartException('checkout_locked','A different quote was already confirmed for this cart.',409);
            $attempt=$this->reconcile($attempt);
            return $this->prepareProjection($attempt,$credential['secret']);
        }
        $quote=$record['checkout']['quote']??null;
        if($record['status']!=='open'||$record['revision']!==$expected||!is_array($quote)||$quote['id']!==$args['quoteId']
            ||$quote['revision']!==$expected||$quote['expires_at']<=$saved['now']) {
            throw new RemoteCartException('quote_expired','Review the current cart and confirm its fresh quote before payment.',409);
        }
        $draft=$this->draft($record,$record['checkout']['delivery'],$record['checkout']['coupon_code'],$expected,$saved['now']);
        if(!hash_equals($quote['digest'],$draft['digest']))throw new RemoteCartException('quote_changed','Prices, product availability, tax or coupon eligibility changed. Review and confirm again.',409);
        $availability=$this->paymentAvailability();
        if(!$availability['available'])throw new RemoteCartException('payment_unavailable','The merchant has not configured payment credentials and signed checkout completion. Payment is unavailable.',503);
        $id=bin2hex(random_bytes(16));$statusExpires=$saved['now']+self::STATUS_TTL;$paymentExpires=$saved['now']+self::PAYMENT_TTL;
        $attempt=['id'=>$id,'cart_id'=>$record['id'],'cart_revision'=>$expected,'quote_id'=>$quote['id'],'quote_digest'=>$quote['digest'],
            'reference_id'=>'ac_'.$id,'mode'=>$availability['mode'],'amount_paise'=>$draft['totals']['totalPaise'],'currency'=>'INR',
            'status_hash'=>hash('sha256',self::statusSecret($credential['secret'],$id,$statusExpires)),
            'status_expires_at'=>$statusExpires,'handoff_hash'=>hash('sha256',self::handoffSecret($credential['secret'],$id,$paymentExpires)),
            'payment_expires_at'=>$paymentExpires,'pending_order'=>$draft['pending_order'],'state'=>'preparing',
            'payment_status'=>'pending','order_status'=>'not_created','created_at'=>gmdate('Y-m-d H:i:s',$saved['now']),
            'updated_at'=>gmdate('Y-m-d H:i:s',$saved['now']),'checked_at'=>$saved['now']];
        $begun=$this->operate('begin_payment',$identity+['expectedRevision'=>$expected,'quoteId'=>$quote['id'],'quoteDigest'=>$quote['digest'],'attempt'=>$attempt]);
        $attempt=$begun['attempt'];
        if($begun['created']) {
            try {
                $link=$this->provider('create_link',$attempt, ['amount'=>$attempt['amount_paise'],'currency'=>'INR','accept_partial'=>false,
                    'reference_id'=>$attempt['reference_id'],'expire_by'=>$attempt['payment_expires_at'],
                    'description'=>'Sri Panchami shop checkout','notify'=>['sms'=>false,'email'=>false],'reminder_enable'=>false]);
                $attempt=$this->acceptLink($attempt,$link);
            } catch(\Throwable $error) {
                // A timeout can follow a successful provider write. Preserve the attempt
                // and reconcile its unique reference; never repeat create_link blindly.
                $failure=in_array($error->getCode(),[400,401,403],true)?'provider_configuration':'provider_unavailable';
                $attempt=$this->operate('update_attempt',['id'=>$attempt['id'],'change'=>'uncertain','failure'=>$failure])['attempt'];
            }
        } else $attempt=$this->reconcile($attempt);
        return $this->prepareProjection($attempt,$credential['secret']);
    }

    private static function stage(array $attempt):string
    {
        if($attempt['order_status']==='saved')return 'completed';
        return match($attempt['state']){'ready'=>'payment_ready','preparing'=>'payment_preparing',default=>'payment_uncertain'};
    }

    private function prepareProjection(array $attempt,string $cartSecret):array
    {
        $secret=self::statusSecret($cartSecret,$attempt['id'],$attempt['status_expires_at']);
        if(!hash_equals($attempt['status_hash'],hash('sha256',$secret)))throw new RemoteCartException('checkout_unavailable','Checkout recovery is temporarily unavailable.',503);
        $result=['stage'=>self::stage($attempt),'quoteId'=>$attempt['quote_id'],'attemptId'=>$attempt['id'],
            'paymentToken'=>'payment_'.$attempt['id'].'.'.$secret,'statusExpiresAt'=>gmdate('Y-m-d\TH:i:s\Z',$attempt['status_expires_at']),
            'provider'=>'razorpay','mode'=>$attempt['mode'],'amountPaise'=>$attempt['amount_paise'],'currency'=>'INR',
            'notice'=>self::attemptNotice($attempt)];
        if($attempt['state']==='ready'&&$attempt['payment_expires_at']>$this->now&&isset($attempt['provider_url'])) {
            $ticket=self::handoffSecret($cartSecret,$attempt['id'],$attempt['payment_expires_at']);
            $result['paymentUrl']=PublicSiteUrlService::origin($this->publicUrl).'/checkout/payment?ticket=pay_'.$attempt['id'].'.'.$ticket;
            $result['paymentExpiresAt']=gmdate('Y-m-d\TH:i:s\Z',$attempt['payment_expires_at']);
        }
        return $result;
    }

    private static function attemptNotice(array $attempt):string
    {
        if($attempt['order_status']==='saved')return 'Payment was verified as captured and the order was saved. Fulfillment follows the merchant’s normal review.';
        if($attempt['payment_status']==='captured')return 'Payment was verified as captured. Order recording is pending recovery; do not pay again.';
        if(in_array($attempt['payment_status'],['expired','cancelled','failed'],true))return 'This payment attempt is '.$attempt['payment_status'].'. No paid order has been recorded.';
        if(($attempt['failure_code']??'')==='provider_configuration')return 'The payment provider rejected checkout setup. The merchant must check its payment-link credentials or account eligibility. Do not start another payment until status is resolved.';
        if($attempt['state']==='ready')return 'Delivery and totals are confirmed. Payment is not complete. Open the merchant payment page only if you choose to continue to the provider; return here to check verified status.';
        return 'Payment preparation is unresolved. Check this attempt’s status; do not start another payment or repeat an unverified charge.';
    }

    public function checkoutStatus(array $args):array
    {
        self::exactKeys($args,['paymentToken']);$credential=self::credential($args['paymentToken'],'payment');
        $saved=$this->operate('read_attempt',['id'=>$credential['id'],'statusHash'=>hash('sha256',$credential['secret'])]);
        return self::statusProjection($this->reconcile($saved['attempt']));
    }

    private static function statusProjection(array $attempt):array
    {
        $result=['paymentStatus'=>$attempt['payment_status'],'orderStatus'=>$attempt['order_status'],
            'amountPaise'=>$attempt['amount_paise'],'currency'=>'INR','statusExpiresAt'=>gmdate('Y-m-d\TH:i:s\Z',$attempt['status_expires_at']),
            'checkedAt'=>gmdate('Y-m-d\TH:i:s\Z',$attempt['checked_at']),'notice'=>self::attemptNotice($attempt)];
        if($attempt['order_status']==='saved')$result['orderReference']=$attempt['order_id'];
        return $result;
    }

    private function provider(string $operation,array $attempt,array $payload=[]):array
    {
        if($this->gateway!==null)return ($this->gateway)($operation,$attempt,$payload);
        $secrets=$this->secretValues();$mode=$attempt['mode'];
        $id=trim((string)($secrets['razorpay_'.$mode.'_key_id']??''));$secret=trim((string)($secrets['razorpay_'.$mode.'_key_secret']??''));
        if($id===''||$secret==='')throw new \RuntimeException('Payment credentials unavailable',401);
        $client=new RazorpayClient($id,$secret);
        return match($operation){
            'create_link'=>$client->createPaymentLink($payload),
            'find_link'=>$client->findPaymentLinks($attempt['reference_id']),
            'fetch_link'=>$client->fetchPaymentLink($attempt['provider_link_id']),
            'fetch_payment'=>$client->fetchPayment($payload['payment_id']),
            'order_payments'=>$client->fetchOrderPayments($payload['order_id']),
            default=>throw new \LogicException('Unknown fixed payment operation.'),
        };
    }

    public static function validProviderUrl(mixed $url):bool
    {
        return is_string($url)&&strlen($url)<=512&&preg_match('~^https://rzp\.io/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+$~D',$url)===1;
    }

    private function acceptLink(array $attempt,array $link):array
    {
        if(!is_string($link['id']??null)||!preg_match('/^plink_[A-Za-z0-9]{1,64}$/D',$link['id'])
            ||($link['reference_id']??null)!==$attempt['reference_id']||($link['amount']??null)!==$attempt['amount_paise']
            ||($link['currency']??null)!=='INR'||($link['accept_partial']??null)!==false
            ||!is_int($link['expire_by']??null)||$link['expire_by']>$attempt['payment_expires_at']
            ||$link['expire_by']<=strtotime($attempt['created_at'].' UTC')
            ||!self::validProviderUrl($link['short_url']??null))throw new \RuntimeException('Provider checkout identity mismatch.');
        if(isset($attempt['provider_link_id'])&&$attempt['provider_link_id']!==$link['id'])throw new \RuntimeException('Provider checkout identity changed.');
        $state=$link['status']??null;
        if(!in_array($state,['created','partially_paid','paid','expired','cancelled'],true))throw new \RuntimeException('Unknown provider checkout state.');
        $attempt=$this->operate('update_attempt',['id'=>$attempt['id'],'change'=>'link','linkId'=>$link['id'],
            'providerUrl'=>$link['short_url'],'paymentStatus'=>match($state){'expired'=>'expired','cancelled'=>'cancelled',default=>'pending'}])['attempt'];
        if($state!=='paid')return $attempt;
        if(($link['amount_paid']??null)!==$attempt['amount_paise']||!is_string($link['order_id']??null)
            ||!preg_match('/^order_[A-Za-z0-9]{1,64}$/D',$link['order_id']))throw new \RuntimeException('Paid checkout amount or order mismatch.');
        $payments=$link['payments']??[];$ids=[];
        if(is_array($payments))foreach(array_slice($payments,0,20) as $payment) {
            $id=$payment['payment_id']??null;
            if(is_string($id)&&preg_match('/^pay_[A-Za-z0-9]{1,64}$/D',$id))$ids[$id]=true;
        }
        if($ids===[]) {
            $list=$this->provider('order_payments',$attempt,['order_id'=>$link['order_id']]);
            foreach(array_slice($list['items']??[],0,20) as $payment) {
                $id=$payment['id']??null;
                if(is_string($id)&&preg_match('/^pay_[A-Za-z0-9]{1,64}$/D',$id))$ids[$id]=true;
            }
        }
        $verified=[];
        foreach(array_keys($ids) as $id) {
            $payment=$this->provider('fetch_payment',$attempt,['payment_id'=>$id]);
            if(PaymentService::isCapturedRazorpayPayment($payment,$id,$link['order_id'],$attempt['amount_paise']))$verified[]=$id;
        }
        if(count($verified)!==1)throw new \RuntimeException('Captured payment could not be uniquely verified.');
        $attempt=$this->operate('update_attempt',['id'=>$attempt['id'],'change'=>'verified','paymentId'=>$verified[0],'providerOrderId'=>$link['order_id']])['attempt'];
        return $this->saveOrder($attempt);
    }

    private function saveOrder(array $attempt):array
    {
        if($attempt['order_status']==='saved')return $attempt;
        if($attempt['payment_status']!=='captured'||empty($attempt['verified_payment_id']))return $attempt;
        try {
            $fields=['razorpay_order_id'=>$attempt['provider_order_id'],'razorpay_payment_link_id'=>$attempt['provider_link_id']];
            $id=$this->persist!==null?($this->persist)($attempt['pending_order'],$attempt['verified_payment_id'],$fields)
                :(new VerifiedOrderService($this->store))->persist($attempt['pending_order'],$attempt['verified_payment_id'],$fields);
            return $this->operate('update_attempt',['id'=>$attempt['id'],'change'=>'completed','orderId'=>$id])['attempt'];
        } catch(\Throwable) {
            // The shared verified-payment save is idempotent. A later signed event,
            // bounded reconciler or status read safely recovers a partial write.
            return $this->operate('update_attempt',['id'=>$attempt['id'],'change'=>'save_pending'])['attempt'];
        }
    }

    private function reconcile(array $attempt):array
    {
        if($attempt['order_status']==='saved')return $attempt;
        $claim=$this->operate('claim_reconcile',['id'=>$attempt['id']]);$attempt=$claim['attempt'];
        if(!$claim['claimed'])return $attempt;
        if($attempt['payment_status']==='captured')return $this->saveOrder($attempt);
        try {
            if(isset($attempt['provider_link_id']))$link=$this->provider('fetch_link',$attempt);
            else {
                $response=$this->provider('find_link',$attempt);$matches=[];
                $links=$response['payment_links']??$response['items']??null;
                if(!is_array($links)||!array_is_list($links)||count($links)>20)throw new \RuntimeException('Invalid provider lookup result.');
                foreach($links as $candidate) {
                    if(is_array($candidate)&&($candidate['reference_id']??null)===$attempt['reference_id'])$matches[]=$candidate;
                }
                if(count($matches)>1)throw new \RuntimeException('Ambiguous provider reference.');
                if($matches===[]) {
                    return $this->operate('update_attempt',['id'=>$attempt['id'],'change'=>'not_found'])['attempt'];
                }
                $link=$matches[0];
            }
            return $this->acceptLink($attempt,$link);
        } catch(\Throwable) {
            return $this->operate('update_attempt',['id'=>$attempt['id'],'change'=>'uncertain','failure'=>'provider_unavailable'])['attempt'];
        }
    }

    /** Merchant-owned page only; this read neither creates a payment nor changes a browser cart. */
    public function paymentHandoff(string $ticket):array
    {
        $credential=self::credential($ticket,'pay');
        $saved=$this->operate('read_handoff',['id'=>$credential['id'],'handoffHash'=>hash('sha256',$credential['secret'])]);
        $attempt=$saved['attempt'];
        return ['amountPaise'=>$attempt['amount_paise'],'currency'=>'INR','mode'=>$attempt['mode'],
            'expiresAt'=>gmdate('Y-m-d\TH:i:s\Z',$attempt['payment_expires_at']),
            'providerUrl'=>$attempt['state']==='ready'&&self::validProviderUrl($attempt['provider_url']??null)?$attempt['provider_url']:null,
            'notice'=>self::attemptNotice($attempt),'paymentStatus'=>$attempt['payment_status'],'orderStatus'=>$attempt['order_status']];
    }

    /** HMAC is checked before JSON parsing. Payload assertions never establish paid state. */
    public function receiveWebhook(string $body,string $signature):void
    {
        if(strlen($body)>65536||!preg_match('/^[a-f0-9]{64}$/D',$signature))throw new RemoteCartException('invalid_signature','Invalid payment notification.',401);
        $secrets=$this->secretValues();$modes=[];
        foreach(['test','live'] as $mode) {
            $secret=(string)($secrets['razorpay_'.$mode.'_webhook_secret']??'');
            if($secret!==''&&hash_equals(hash_hmac('sha256',$body,$secret),$signature))$modes[]=$mode;
        }
        if($modes===[])throw new RemoteCartException('invalid_signature','Invalid payment notification.',401);
        try{$event=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new RemoteCartException('invalid_notification','Invalid payment notification.');}
        if(!is_array($event)||!in_array($event['event']??null,['payment_link.paid','payment_link.partially_paid','payment_link.expired','payment_link.cancelled'],true))return;
        $reference=$event['payload']['payment_link']['entity']['reference_id']??null;
        if(!is_string($reference)||!preg_match('/^ac_([a-f0-9]{32})$/D',$reference,$matches))return;
        try{$saved=$this->operate('read_internal',['id'=>$matches[1]]);}
        catch(RemoteCartException $error){if($error->errorCode==='checkout_not_found')return;throw $error;}
        $attempt=$saved['attempt'];
        if(!in_array($attempt['mode'],$modes,true))throw new RemoteCartException('invalid_signature','Invalid payment notification.',401);
        $attempt=$this->reconcile($attempt);
        if($attempt['order_status']==='save_pending'||$attempt['state']==='uncertain'||$attempt['state']==='preparing'
            ||(($event['event']??null)==='payment_link.paid'&&$attempt['payment_status']!=='captured')) {
            throw new RemoteCartException('notification_pending','Payment verification will be retried.',503,30);
        }
    }

    /** CLI/cron only: bounded provider reconciliation, no payment creation or personal output. */
    public function reconcilePending(int $limit=20):array
    {
        if($limit<1||$limit>20)throw new \InvalidArgumentException('Reconciliation limit must be 1–20.');
        $records=$this->operate('list_pending',['limit'=>$limit]);$checked=$saved=0;
        foreach($records['attempts'] as $attempt){$result=$this->reconcile($attempt);$checked++;if($result['order_status']==='saved')$saved++;}
        return ['checked'=>$checked,'ordersSaved'=>$saved];
    }
}
