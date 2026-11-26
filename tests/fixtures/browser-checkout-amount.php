<?php
declare(strict_types=1);
namespace {
    // Execute the native controller in an isolated CLI process with only synthetic
    // records and a recording gateway. No bootstrap, credentials or network.
    if (PHP_SAPI !== 'cli' || getenv('BAPX_CHECKOUT_AMOUNT_FIXTURE') !== '1') exit(1);
    $fixture = json_decode($argv[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
    $GLOBALS['amountFixture'] = $fixture;
    $GLOBALS['gatewayCalls'] = [];
}
namespace App\Services {
    final class DatabaseService {
        public function read(string $collection): array {
            $case = $GLOBALS['amountFixture'];
            return match ($collection) {
                'products' => [array_replace(['slug'=>'fixture-product','name'=>'Fixture product','price'=>$case['price'],'stock_status'=>'in_stock',
                    'gst_rate'=>5,'hsn_code'=>'8306','gst_uqc'=>'KGS','gst_qty_per_unit'=>'0.125'], $case['product']??[])],
                'coupons' => isset($case['coupon']) ? [$case['coupon']] : [],
                'orders' => [],
                default => throw new \RuntimeException('Unexpected fixture collection'),
            };
        }
    }
    final class SecretService {
        public function all(): array {
            return ['razorpay_key_id'=>'fixture-key','razorpay_key_secret'=>'fixture-secret','stripe_secret_key'=>'fixture-stripe'];
        }
        public function razorpayReadyForCurrentHost(array $secrets): bool {return true;}
    }
    final class SettingsService {
        public function public(bool $requireReadable=false): array {
            if (!empty($GLOBALS['amountFixture']['settingsUnavailable'])) {
                if ($requireReadable) throw new \RuntimeException('Fixture settings unavailable');
                return [];
            }
            return array_replace(['gst_state'=>'Tamil Nadu','default_gst_rate'=>12,'default_hsn_code'=>'9999',
                'gst_legal_name'=>'Fixture Supplier','gst_address'=>'Fixture address','private_marker'=>'never-frozen'], $GLOBALS['amountFixture']['settings']??[]);
        }
    }
    final class SiteTelemetryService {
        public function record(string $event, array $data=[]): void {}
    }
}
namespace App\Integrations\Razorpay {
    final class RazorpayClient {
        public function __construct(string $key,string $secret) {
            if ($key!=='fixture-key'||$secret!=='fixture-secret') throw new \RuntimeException('Non-fixture credential');
        }
        public function createOrder(int $amount,string $receipt): array {
            $GLOBALS['gatewayCalls'][]=$amount;
            return ['id'=>'order_fixture','amount'=>$amount,'currency'=>'INR'];
        }
    }
}
namespace App\Integrations\Stripe {
    final class StripeClient {
        public function __construct(string $key) {
            if ($key!=='fixture-stripe') throw new \RuntimeException('Non-fixture credential');
        }
        public function createCheckoutSession(array $lineItems,string $successUrl,string $cancelUrl): array {
            if (count($lineItems)!==1 || $lineItems[0]['quantity']!==1 || !is_int($lineItems[0]['amount']))
                throw new \RuntimeException('Unexpected fixture gateway request');
            $GLOBALS['gatewayCalls'][]=$lineItems[0]['amount'];
            return ['id'=>'cs_fixture','url'=>'https://stripe.example.invalid/checkout'];
        }
    }
}
namespace {
    spl_autoload_register(static function(string $class): void {
        if(!str_starts_with($class,'App\\'))return;
        $file=dirname(__DIR__,2).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
        if(is_file($file)) require $file;
    });
    $_SERVER['REQUEST_URI']='/checkout/create-order';
    $_SESSION=['csrf_token'=>'fixture-csrf','cart'=>[['slug'=>'fixture-product','qty'=>1]],'pending_order'=>['marker'=>'existing recovery']];
    $_POST=['_csrf'=>'fixture-csrf','phone'=>'9876543210','state'=>'Tamil Nadu','payment_method'=>$fixture['method']??'razorpay','email'=>'fixture@example.invalid','coupon_code'=>$fixture['couponCode']??(isset($fixture['coupon'])?'FIXTURE':'')];
    if(array_key_exists('posted',$fixture))$_POST['amount']=$fixture['posted'];
    ob_start();
    register_shutdown_function(static function():void {
        $body=ob_get_clean();
        echo json_encode(['status'=>http_response_code(),'body'=>json_decode($body,true),'gateway_calls'=>$GLOBALS['gatewayCalls'],'session'=>$_SESSION],JSON_THROW_ON_ERROR);
    });
    (new \App\Controllers\CommerceController())->createOrder();
}
