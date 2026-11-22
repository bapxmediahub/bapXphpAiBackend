<?php
declare(strict_types=1);

use App\Services\{DatabaseService,ProductService,PublicKnowledgeService,RemoteCartService,SettingsService};

/** Synthetic catalogue plus the real pure cart state machine. No sockets, files or business writes. */
final class RemoteCartFixture
{
    public int $now;
    public array $carts=[];
    public array $operations=[];
    private array $catalogue;
    private DatabaseService $store;
    private ProductService $products;
    private PublicKnowledgeService $knowledge;
    private RemoteCartService $service;
    private ReflectionProperty $cache;
    private ReflectionProperty $settings;
    private array $previousCache;
    private mixed $previousSettings;
    private string|false $previousTestMode;
    private mixed $previousUrl;
    private string $cacheKey;
    private bool $closed=false;

    public function __construct(?array $catalogue=null)
    {
        $this->now=strtotime('2026-11-22 12:00:00 UTC');
        $this->catalogue=$catalogue??self::defaultProducts();
        $this->previousTestMode=getenv('BAPX_TEST_MODE');
        $this->previousUrl=$_SERVER['APP_URL']??null;
        $this->store=new DatabaseService();
        $url='http://127.0.0.1:1/remote-cart-fixture';
        (new ReflectionProperty(DatabaseService::class,'cfg'))->setValue($this->store,['remote_url'=>$url]);
        (new ReflectionProperty(DatabaseService::class,'remoteOnly'))->setValue($this->store,true);
        $this->cache=new ReflectionProperty(DatabaseService::class,'remoteQueryCache');
        $this->settings=new ReflectionProperty(SettingsService::class,'cache');
        $this->previousCache=$this->cache->getValue();$this->previousSettings=$this->settings->getValue();
        $this->cacheKey=hash('sha256',$url."\0SELECT * FROM products\0".serialize([]));
        $this->settings->setValue(null,[]);
        $_SERVER['APP_URL']='https://cart.example.test';
        putenv('BAPX_TEST_MODE=0');
        $this->setProducts($this->catalogue);
        $this->products=new ProductService($this->store,new DateTimeImmutable('2026-11-22 12:00:00 UTC'));
        $this->knowledge=new PublicKnowledgeService($this->products,publicUrl:'https://cart.example.test');
        $this->service=new RemoteCartService($this->store,$this->products,$this->knowledge,
            function(string $operation,array $payload):array {
                $this->operations[]=$operation;
                $result=RemoteCartService::transition($this->carts[$payload['id']]??null,$operation,$payload,$this->now);
                $this->carts[$payload['id']]=$result['record'];
                return $result;
            },'https://cart.example.test');
    }

    public function service():RemoteCartService { return $this->service; }
    public function store():DatabaseService { return $this->store; }
    public function knowledge():PublicKnowledgeService { return $this->knowledge; }
    public function products():ProductService { return $this->products; }
    public function records():array { return $this->catalogue; }
    public function seedCheckoutRows(string $collection,array $rows):void
    {
        if(!in_array($collection,['coupons','orders'],true))throw new InvalidArgumentException('Unsupported fixture collection.');
        $key=hash('sha256',"http://127.0.0.1:1/remote-cart-fixture\0SELECT * FROM ".$collection."\0".serialize([]));
        $cache=$this->cache->getValue();$cache[$key]=array_map(static fn(array $row):array=>[
            'id'=>$row['id'],'_data'=>json_encode($row,JSON_THROW_ON_ERROR)],$rows);$this->cache->setValue(null,$cache);
    }
    public function shopEnabled(bool $enabled):void { $this->settings->setValue(null,['module_shop'=>$enabled?'1':'0']); }

    public function setProducts(array $catalogue):void
    {
        $this->catalogue=$catalogue;
        $cache=$this->cache->getValue();
        $cache[$this->cacheKey]=array_map(static fn(array $row):array=>[
            'id'=>$row['id'],'_data'=>json_encode($row,JSON_THROW_ON_ERROR)],$catalogue);
        $this->cache->setValue(null,$cache);
    }

    public static function defaultProducts():array
    {
        $records=[
            ['id'=>'ring','slug'=>'sri-varahi-amman-ring','name'=>'Sri Varahi Amman Ring',
                'price'=>500.75,'stock_status'=>'in_stock','image_url'=>'/assets/images/ring.webp',
                'owner_note'=>'PRIVATE_CART_FIXTURE','customer_email'=>'private@example.test'],
            ['id'=>'pendant','slug'=>'varahi-amman-pendant','name'=>'Varahi Amman Pendant',
                'price'=>350.40,'offer_price'=>299.95,'offer_starts_at'=>'2026-11-01 00:00:00',
                'offer_ends_at'=>'2026-12-01 00:00:00','stock_status'=>'active',
                'image_url'=>'https://untrusted.invalid/tracker.png'],
            ['id'=>'tamil','slug'=>'தமிழ் pendant','name'=>'தமிழ் Pendant','price'=>125.50,
                'stock_status'=>'in_stock','image_url'=>'/assets/images/தமிழ்.webp'],
            ['id'=>'sold-out','slug'=>'sold-out-ring','name'=>'Sold Out Ring','price'=>300,'stock_status'=>'out_of_stock'],
            ['id'=>'hidden','slug'=>'private-ring','name'=>'Private Ring','price'=>999,'status'=>'hidden','stock_status'=>'in_stock'],
            ['id'=>'expired-offer','slug'=>'expired-offer-ring','name'=>'Expired Offer Ring','price'=>199.99,
                'offer_price'=>149.50,'offer_ends_at'=>'2026-11-01 00:00:00','stock_status'=>'in_stock'],
        ];
        for($i=1;$i<=21;$i++)$records[]=['id'=>'item-'.$i,'slug'=>'item-'.$i,'name'=>'Published Item '.$i,
            'price'=>10+$i,'stock_status'=>'in_stock'];
        return $records;
    }

    public function close():void
    {
        if($this->closed)return;$this->closed=true;
        $this->cache->setValue(null,$this->previousCache);$this->settings->setValue(null,$this->previousSettings);
        if($this->previousUrl===null)unset($_SERVER['APP_URL']);else $_SERVER['APP_URL']=$this->previousUrl;
        putenv($this->previousTestMode===false?'BAPX_TEST_MODE':'BAPX_TEST_MODE='.$this->previousTestMode);
    }

    public function __destruct() { $this->close(); }
}
