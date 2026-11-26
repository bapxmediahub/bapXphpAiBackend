<?php
declare(strict_types=1);
namespace App\Services;

/** One read-only product projection for admin preflight, Google RSS and developer CLI. */
final class MerchantFeedService
{
    public const FEED_PATH='/feeds/google-products.xml';
    private string $origin;
    public function __construct(private ProductService $products=new ProductService(),?string $origin=null) {
        $origin??=(string)(require app_path('config/database.php'))['app_url'];
        $parts=parse_url($origin);
        if(!self::webUrl($origin)||!is_array($parts)||isset($parts['query'])||isset($parts['fragment']))throw new \InvalidArgumentException('Valid configured site origin required.');
        $this->origin=rtrim($origin,'/');
    }
    public function feedUrl(): string { return $this->origin.self::FEED_PATH; }
    public static function contract(): array {
        return ['schema_version'=>1,'operation'=>'merchant.catalogue.readiness','read_only'=>true,
            'input_schema'=>['type'=>'object','properties'=>new \stdClass(),'additionalProperties'=>false],
            'outputs'=>['json_readiness','google_rss_2_xml'],
            'external_effects'=>false,'authority'=>'published_product_records_not_google_approval'];
    }
    /** Explicit records are for deterministic tests; runtime callers always read the existing ProductService. */
    public function inspect(?array $records=null): array {
        $records??=$this->products->all();$items=[];$rows=[];$seen=[];$warningCount=0;
        foreach($records as $record){
            if(!is_array($record))continue;
            $id=trim(self::scalar($record['id']??''));$slug=trim(self::scalar($record['slug']??''));
            $title=self::text($record['name']??'',150);$description=self::text($record['description']??'',5000);
            $errors=[];$warnings=[];
            if(!preg_match('/^[A-Za-z0-9_.:-]{1,50}$/D',$id))$errors[]='stable_id_required';
            elseif(isset($seen[$id]))$errors[]='duplicate_id';
            $seen[$id]=true;
            if(!empty($record['is_hidden'])||in_array(strtolower(trim(self::scalar($record['status']??''))),['hidden','inactive','draft','disabled'],true))$errors[]='not_public';
            $stock=strtolower(trim(self::scalar($record['stock_status']??'')));
            if(!in_array($stock,['in_stock','active','out_of_stock'],true))$errors[]='not_sellable_catalogue_state';
            if($slug===''||mb_strlen($slug)>160||preg_match('/[\x00-\x1f\x7f\/\\\\]/u',$slug)||in_array($slug,['.','..'],true))$errors[]='product_url_required';
            if($title==='')$errors[]='title_required';if($description==='')$errors[]='description_required';
            $price=$this->products->priceOf($record);
            if(!is_finite($price)||$price<=0||$price>999999999.99)$errors[]='valid_price_required';
            $image=$this->image(self::scalar($record['image_url']??''));
            if($image===null)$errors[]='real_product_image_required';
            elseif($image['external'])$warnings[]='external_image_not_fetched';
            if($image!==null&&isset($image['width'])&&($image['width']<500||$image['height']<500))$warnings[]='image_below_500px_review_2027_requirement';
            $item=['id'=>$id,'title'=>$title,'description'=>$description,
                'link'=>$this->origin.'/product/'.rawurlencode($slug),'image_link'=>$image['url']??'',
                'price'=>number_format($price,2,'.','').' INR','availability'=>$stock==='out_of_stock'?'out_of_stock':'in_stock'];
            $metadata=self::metadata($record);
            $item+=$metadata['fields'];
            $warnings=array_merge($warnings,$metadata['warnings']);
            $gallery=is_array($record['image_urls']??null)?$record['image_urls']:[];$additional=[];
            foreach(array_slice($gallery,0,10) as $candidate){$extra=$this->image(self::scalar($candidate));if($extra!==null&&$extra['url']!==$item['image_link'])$additional[]=$extra['url'];}
            if($additional!==[])$item['additional_image_link']=array_values(array_unique($additional));
            $warnings=array_values(array_unique($warnings));$warningCount+=count($warnings)>0?1:0;
            $rows[]=['id'=>self::text($id,50),'title'=>$title,'link'=>$item['link'],'price'=>$item['price'],
                'availability'=>$item['availability'],'state'=>$errors===[]?'exported':'excluded','errors'=>array_values(array_unique($errors)),'warnings'=>$warnings];
            if($errors===[])$items[]=$item;
        }
        return ['schema_version'=>1,'feed_url'=>$this->feedUrl(),'record_count'=>count($rows),
            'exported_count'=>count($items),'excluded_count'=>count($rows)-count($items),'warning_count'=>$warningCount,
            'merchant_approval'=>'unverified','youtube_status'=>'eligibility_and_linking_required',
            'external_connection'=>'not_verified','items'=>$items,'rows'=>$rows];
    }
    public function xml(array $report): string {
        $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"><channel>';
        $xml.='<title>Product catalogue</title><link>'.self::escape($this->origin.'/shop').'</link><description>Current public product data</description>';
        $allowed=['id','title','description','link','image_link','price','availability','gtin','mpn','brand','condition','identifier_exists','additional_image_link'];
        foreach($report['items']??[] as $item){$xml.="\n<item>";
            foreach($allowed as $key){if(!isset($item[$key]))continue;
                foreach(is_array($item[$key])?$item[$key]:[$item[$key]] as $value)$xml.='<g:'.$key.'>'.self::escape(self::scalar($value)).'</g:'.$key.'>';}
            $xml.='</item>';
        }
        return $xml."\n</channel></rss>\n";
    }
    /** Validate explicitly submitted owner metadata; omission preserves existing values.
     * Empty strings clear fields. Never infer a manufacturer, barcode or exception. */
    public static function validateMetadata(array $existing,array $submitted): array {
        $updates=[];
        foreach (['brand','gtin','mpn','condition','identifier_exists'] as $key) {
            if (!array_key_exists($key,$submitted)) continue;
            $value=$submitted[$key];
            if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||preg_match('/[\x00-\x1f\x7f]/',$value)) {
                throw new \InvalidArgumentException('Enter valid product identity fields.');
            }
            $value=trim($value);
            if (in_array($key,['brand','mpn'],true) && mb_strlen($value)>70) {
                throw new \InvalidArgumentException('Brand and manufacturer part number must be at most 70 characters.');
            }
            if ($key==='gtin' && $value!=='' && !self::validGtin($value)) {
                throw new \InvalidArgumentException('Enter the actual 8, 12, 13 or 14-digit GTIN with a valid check digit, or leave it blank.');
            }
            if ($key==='condition' && !in_array($value,['','new','used','refurbished'],true)) {
                throw new \InvalidArgumentException('Select a valid product condition.');
            }
            if ($key==='identifier_exists' && !in_array($value,['','yes','no'],true)) {
                throw new \InvalidArgumentException('Select a valid identifier status.');
            }
            $updates[$key]=$value;
        }
        if ($updates===[]) return [];
        $combined=array_replace($existing,$updates);
        if (self::noIdentifiers($combined) && self::hasRecordedIdentifiers($combined)) {
            throw new \InvalidArgumentException('Only confirm no assigned identifiers when no brand, GTIN or manufacturer part number is recorded. Correct the identifier status or the actual fields.');
        }
        return $updates;
    }

    /** Shared validated public projection for the feed, product page and JSON-LD. */
    public static function metadata(array $record): array {
        $fields=[];$warnings=[];
        $gtin=trim(self::scalar($record['gtin']??''));
        $mpn=self::text($record['mpn']??'',70);$brand=self::text($record['brand']??'',70);
        if($gtin!==''&&self::validGtin($gtin))$fields['gtin']=$gtin;
        elseif($gtin!=='')$warnings[]='invalid_gtin_omitted';
        if($mpn!=='')$fields['mpn']=$mpn;
        if($brand!=='')$fields['brand']=$brand;
        if(self::noIdentifiers($record)&&!self::hasRecordedIdentifiers($record))$fields['identifier_exists']='no';
        else {
            if(self::noIdentifiers($record))$warnings[]='identifier_status_conflict';
            if(!isset($fields['gtin'])&&($mpn===''||$brand===''))$warnings[]='identifiers_need_review';
            if(($record['identifier_exists']??null)==='yes')$fields['identifier_exists']='yes';
        }
        if(in_array($record['condition']??null,['new','used','refurbished'],true))$fields['condition']=$record['condition'];
        else $warnings[]='condition_needs_review';
        return ['fields'=>$fields,'warnings'=>$warnings];
    }
    private static function noIdentifiers(array $record): bool {
        return ($record['identifier_exists']??null)===false||($record['identifier_exists']??null)==='no';
    }
    private static function hasRecordedIdentifiers(array $record): bool {
        foreach(['brand','gtin','mpn'] as $key) if(trim(self::scalar($record[$key]??''))!=='')return true;
        return false;
    }

    private static function scalar(mixed $value): string {return is_string($value)||is_int($value)||is_float($value)?(string)$value:'';}
    private static function text(mixed $value,int $limit): string {
        $text=html_entity_decode(strip_tags(self::scalar($value)),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $text=preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$text)??'';
        return mb_substr(trim(preg_replace('/\s+/u',' ',$text)??''),0,$limit);
    }
    private static function escape(string $text): string {return htmlspecialchars($text,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    private static function webUrl(string $url): bool {
        $parts=parse_url($url);
        return is_array($parts)&&in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)
            &&!empty($parts['host'])&&!isset($parts['user'])&&!isset($parts['pass'])
            &&!preg_match('/[\x00-\x20\x7f\\\\]/',$url)&&filter_var($url,FILTER_VALIDATE_URL)!==false;
    }
    /** Same-origin files are verified locally; external URLs are never fetched here. */
    private function image(string $url): ?array {
        $url=trim($url);if($url==='')return null;$parts=parse_url($url);if(!is_array($parts)||isset($parts['fragment']))return null;
        $host=(string)(parse_url($this->origin,PHP_URL_HOST)??'');
        if(isset($parts['host'])){
            if(!self::webUrl($url))return null;
            if(strcasecmp((string)$parts['host'],$host)!==0){
                if(preg_match('~(?:placeholder|dummyimage|placehold\.co)~i',$url))return null;
                return ['url'=>$url,'external'=>true];
            }
        }elseif(isset($parts['scheme'])||!str_starts_with($url,'/')||str_starts_with($url,'//'))return null;
        $path=rawurldecode((string)($parts['path']??''));
        if(!str_starts_with($path,'/assets/images/')||str_contains($path,'..')||preg_match('/[\x00-\x1f\\\\]/',$path)
            ||!preg_match('/\.(?:jpe?g|webp|png|gif|bmp|tiff?)$/iD',$path)||preg_match('~/(?:placeholder|logo|favicon|og-image|hero-temple-bg)[^/]*$~i',$path))return null;
        $path=webp_src($path);$file=MediaService::storedPathForUrl($path)??app_path(ltrim($path,'/'));
        if(!is_file($file)||is_link($file)||!is_readable($file))return null;$size=@getimagesize($file);if($size===false)return null;
        $encoded=implode('/',array_map('rawurlencode',explode('/',$path)));
        return ['url'=>$this->origin.$encoded,'external'=>false,'width'=>$size[0],'height'=>$size[1]];
    }
    private static function validGtin(string $value): bool {
        if(!preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/D',$value)||preg_match('/^0+$/D',$value))return false;
        $sum=0;$body=substr($value,0,-1);$factor=3;
        for($i=strlen($body)-1;$i>=0;$i--){$sum+=(int)$body[$i]*$factor;$factor=$factor===3?1:3;}
        return (10-$sum%10)%10===(int)substr($value,-1);
    }
}
