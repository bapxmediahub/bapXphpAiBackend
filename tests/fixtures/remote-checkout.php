<?php
declare(strict_types=1);
use App\Services\{RemoteCheckoutService,RemoteCheckoutStateService,VerifiedOrderService};
require_once __DIR__.'/remote-cart.php';

/** Real checkout/cart transitions and verified-order save with a deterministic, in-process provider. */
final class RemoteCheckoutFixture
{
    public RemoteCartFixture $cartFixture;
    public array $attempts=[];
    public array $links=[];
    public array $payments=[];
    public array $orders=[];
    public array $gatewayCalls=[];
    public array $operations=[];
    public array $coupons=[];
    public bool $gatewayOffline=false;
    public bool $timeoutAfterCreate=false;
    public bool $failPersist=false;
    public bool $saveThenFailOnce=false;
    public int $persistCalls=0;
    public array $settings=['gst_state'=>'Tamil Nadu','default_gst_rate'=>5.0,'default_hsn_code'=>'8306',
        'gst_legal_name'=>'Fixture Store','gst_trade_name'=>'Fixture Store','gstin'=>'33AAAAA0000A1Z5',
        'gst_address'=>'Synthetic supplier address','gst_state_code'=>'33'];
    public array $secrets=['razorpay_mode'=>'test','razorpay_test_key_id'=>'rzp_test_fixture',
        'razorpay_test_key_secret'=>'fixture-key-never-a-real-provider-key','razorpay_test_webhook_secret'=>'fixture-webhook-never-a-real-provider-secret'];
    private RemoteCheckoutService $service;

    public function __construct(?array $state=null)
    {
        $this->cartFixture=new RemoteCartFixture($state['catalogue']??null);
        if($state!==null) {
            if(($state['fixtureVersion']??null)!==1)throw new InvalidArgumentException('Unknown checkout fixture state.');
            $this->cartFixture->now=$state['now'];$this->cartFixture->carts=$state['carts'];
            foreach(['attempts','links','payments','orders','gatewayCalls','operations','coupons','settings','secrets',
                'gatewayOffline','timeoutAfterCreate','failPersist','saveThenFailOnce','persistCalls'] as $field) {
                if(array_key_exists($field,$state))$this->$field=$state[$field];
            }
        }
        $this->seed();
        $this->service=new RemoteCheckoutService(store:$this->cartFixture->store(),products:$this->cartFixture->products(),
            knowledge:$this->cartFixture->knowledge(),operation:fn(string $op,array $args):array=>$this->operate($op,$args),
            gateway:fn(string $op,array $attempt,array $args):array=>$this->gateway($op,$attempt,$args),
            settings:fn():array=>$this->settings,secrets:fn():array=>$this->secrets,
            persist:fn(array $pending,string $paymentId,array $fields):string=>$this->saveOrder($pending,$paymentId,$fields),
            publicUrl:'https://cart.example.test');
    }

    public function service():RemoteCheckoutService { return $this->service; }
    public function gatewayPort():Closure { return fn(string $op,array $attempt,array $args):array=>$this->gateway($op,$attempt,$args); }
    public static function delivery():array {
        return ['name'=>'Synthetic Shopper','email'=>'synthetic-shopper@example.test','phone'=>'9876543210',
            'address'=>'12 Synthetic Test Street','city'=>'Chennai','pincode'=>'600001','state'=>'Tamil Nadu','gstin'=>''];
    }
    public function advance(int $seconds):void { $this->cartFixture->now+=$seconds; }
    public function close():void { $this->cartFixture->close(); }

    public function exportState():array
    {
        $state=['fixtureVersion'=>1,'now'=>$this->cartFixture->now,'carts'=>$this->cartFixture->carts,'catalogue'=>$this->cartFixture->records()];
        foreach(['attempts','links','payments','orders','gatewayCalls','operations','coupons','settings','secrets',
            'gatewayOffline','timeoutAfterCreate','failPersist','saveThenFailOnce','persistCalls'] as $field)$state[$field]=$this->$field;
        return $state;
    }

    public function seed():void
    {
        $this->cartFixture->seedCheckoutRows('coupons',$this->coupons);
        $this->cartFixture->seedCheckoutRows('orders',array_values($this->orders));
    }

    /** MySQL JSON object keys and whole-number floats may return in normalized form. */
    private static function stored(array $data):array
    {
        $sort=static function(array $value)use(&$sort):array {
            if(!array_is_list($value))ksort($value);
            foreach($value as $key=>$child)if(is_array($child))$value[$key]=$sort($child);
            return $value;
        };
        return json_decode(json_encode($sort($data),JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
    }

    private function operate(string $operation,array $args):array
    {
        $this->seed();$this->operations[]=$operation;$now=$this->cartFixture->now;
        if($operation==='list_pending') {
            $attempts=array_values(array_filter($this->attempts,static fn(array $a):bool=>
                !in_array($a['state'],['completed','expired','cancelled'],true)&&strtotime($a['updated_at'].' UTC')<=$now-30));
            usort($attempts,static fn(array $a,array $b):int=>$a['updated_at']<=>$b['updated_at']);
            return ['attempts'=>array_slice($attempts,0,$args['limit']),'now'=>$now];
        }
        if(in_array($operation,['read_cart','save_review','begin_payment'],true)) {
            $cart=$this->cartFixture->carts[$args['id']]??null;
            $id=$cart['checkout_receipt']['attempt_id']??null;$attempt=$id!==null?($this->attempts[$id]??null):null;
            $result=RemoteCheckoutStateService::cart($cart,$attempt,$operation,$args,$now);
            $this->cartFixture->carts[$args['id']]=self::stored($result['record']);
            if(($result['created']??false)===true)$this->attempts[$result['attempt']['id']]=self::stored($result['attempt']);
            return $result;
        }
        $result=RemoteCheckoutStateService::attempt($this->attempts[$args['id']]??null,$operation,$args,$now);
        $this->attempts[$args['id']]=self::stored($result['attempt']);return $result;
    }

    private function gateway(string $operation,array $attempt,array $args):array
    {
        $this->gatewayCalls[]=['operation'=>$operation,'attemptId'=>$attempt['id'],'payload'=>$args];
        if($this->gatewayOffline)throw new RuntimeException('Synthetic provider unavailable',503);
        $linkId='plink_'.substr($attempt['id'],0,20);
        if($operation==='create_link') {
            if(isset($this->links[$linkId]))throw new RuntimeException('Fixture detected duplicate provider creation.');
            $this->links[$linkId]=$args+['id'=>$linkId,'short_url'=>'https://rzp.io/rzp/fixture'.substr($attempt['id'],0,12),
                'status'=>'created','amount_paid'=>0,'order_id'=>'order_'.substr($attempt['id'],0,20),'payments'=>[]];
            if($this->timeoutAfterCreate)throw new RuntimeException('Synthetic lost provider response',503);
            return $this->links[$linkId];
        }
        if($operation==='find_link')return ['payment_links'=>array_values(array_filter($this->links,
            static fn(array $link):bool=>$link['reference_id']===$attempt['reference_id']))];
        if($operation==='fetch_link')return $this->links[$attempt['provider_link_id']]??throw new RuntimeException('Synthetic missing link',404);
        if($operation==='fetch_payment')return $this->payments[$args['payment_id']]??throw new RuntimeException('Synthetic missing payment',404);
        if($operation==='order_payments'){
            $items=array_values(array_filter($this->payments,
                static fn(array $payment):bool=>$payment['order_id']===$args['order_id']));
            return ['entity'=>'collection','count'=>count($items),'items'=>$items];
        }
        throw new LogicException('Unexpected fixture provider operation.');
    }

    public function markPaid(string $attemptId,array $overrides=[]):void
    {
        $attempt=$this->attempts[$attemptId];$linkId='plink_'.substr($attemptId,0,20);$paymentId='pay_'.substr($attemptId,0,20);
        $this->links[$linkId]['status']='paid';$this->links[$linkId]['amount_paid']=$attempt['amount_paise'];
        $this->links[$linkId]['payments']=[['payment_id'=>$paymentId,'amount'=>$attempt['amount_paise']]];
        $this->payments[$paymentId]=array_replace(['id'=>$paymentId,'order_id'=>$this->links[$linkId]['order_id'],
            'amount'=>$attempt['amount_paise'],'currency'=>'INR','status'=>'captured','captured'=>true,
            'email'=>'synthetic-provider-private@example.test','contact'=>'+919999999999','vpa'=>'synthetic-private@provider'], $overrides);
    }

    private function saveOrder(array $pending,string $paymentId,array $fields):string
    {
        $this->persistCalls++;
        if($this->failPersist)throw new RuntimeException('Synthetic persistence outage.');
        $id=(new VerifiedOrderService())->persist($pending,$paymentId,$fields,[
            'readOrders'=>fn():array=>array_values($this->orders),
            'products'=>fn():array=>$this->cartFixture->products()->bySlug(),
            'settings'=>fn():array=>$this->settings,
            'insertOrder'=>function(array $order):array {
                if(isset($this->orders[$order['id']]))return ['inserted'=>false,'record'=>$this->orders[$order['id']]];
                $this->orders[$order['id']]=$order;$this->seed();return ['inserted'=>true,'record'=>$order];
            },
        ]);
        if($this->saveThenFailOnce){$this->saveThenFailOnce=false;throw new RuntimeException('Synthetic lost save response.');}
        return $id;
    }
}
