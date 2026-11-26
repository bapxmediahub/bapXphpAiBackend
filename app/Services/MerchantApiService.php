<?php
declare(strict_types=1);
namespace App\Services;

/** Native compatibility bridge for Google's documented Merchant API v1.
 * Pure preview and read-only diagnostics, not a WordPress.com partner identity. */
final class MerchantApiService
{
    private const HOST='https://merchantapi.googleapis.com';
    private const PAGE_SIZE=100;
    private const MAX_PAGES=5;

    public function __construct(private ?\Closure $transport=null) {}

    public static function validateLocale(string $language,string $label): void {
        if (!preg_match('/^[a-z]{2}$/D',$language)||!preg_match('/^[A-Z0-9_-]{1,20}$/D',$label)) {
            throw new \InvalidArgumentException('Use the actual two-letter content language and Merchant feed label.');
        }
    }

    /** Input is the same allowlisted projection used for the public RSS feed. */
    public function preview(array $report,string $language,string $label,array $custom=[]): array {
        self::validateLocale($language,$label);
        if($custom!==[]){
            // Revalidate the exact supported proposal contract even for internal callers.
            if(!array_is_list($custom)||count($custom)<4)throw new \InvalidArgumentException('Invalid YouTube partner preview attributes.');
            $channels=[];
            foreach(array_slice($custom,3) as $attribute){
                if(!is_array($attribute)||($attribute['name']??null)!=='youtube channel id')throw new \InvalidArgumentException('Invalid YouTube channel attribute.');
                $channels[]=$attribute['value']??null;
            }
            $expected=self::partnerPreviewAttributes(
                is_string($custom[0]['value']??null)?$custom[0]['value']:'',
                is_string($custom[1]['value']??null)?$custom[1]['value']:'',
                is_string($custom[2]['value']??null)?$custom[2]['value']:'',$channels);
            if($expected!==$custom)throw new \InvalidArgumentException('Invalid YouTube partner preview attributes.');
        }
        $items=[];$seen=[];
        foreach($report['items'] as $row){
            $id=$row['id']??null;
            if (!is_string($id)||!preg_match('/^[A-Za-z0-9_.:-]{1,50}$/D',$id)||isset($seen[$id])) {
                throw new \DomainException('Invalid or duplicate offer identity in catalogue projection.');
            }
            $seen[$id]=true;
            $price=$row['price']??'';
            if (!is_string($price)||!preg_match('/^(\d{1,9})\.(\d{2}) INR$/D',$price,$m)) {
                throw new \DomainException('Unsupported catalogue price.');
            }
            // The canonical RSS price already has exactly two decimal places.
            // Convert paise to micros with decimal strings, not binary floats.
            $micros=ltrim($m[1].$m[2].'0000','0');
            if($micros==='')throw new \DomainException('Non-payable catalogue offer.');
            $attrs=[
                'title'=>$row['title'],'description'=>$row['description'],
                'link'=>$row['link'],'imageLink'=>$row['image_link'],
                'availability'=>match($row['availability']??''){
                    'in_stock'=>'IN_STOCK','out_of_stock'=>'OUT_OF_STOCK',
                    default=>throw new \DomainException('Unsupported catalogue availability.'),
                },
                'price'=>['amountMicros'=>$micros,'currencyCode'=>'INR'],
            ];
            foreach(['brand'=>'brand','mpn'=>'mpn','condition'=>'condition','additional_image_link'=>'additionalImageLinks'] as $from=>$to) {
                if(isset($row[$from]))$attrs[$to]=$from==='condition'?strtoupper($row[$from]):$row[$from];
            }
            if(isset($row['gtin']))$attrs['gtins']=[$row['gtin']];
            if(isset($row['identifier_exists']))$attrs['identifierExists']=$row['identifier_exists']==='yes';
            $input=['offerId'=>$id,'contentLanguage'=>$language,'feedLabel'=>$label,'productAttributes'=>$attrs];
            if($custom!==[])$input['customAttributes']=$custom;
            $items[]=$input;
        }
        return ['schema_version'=>1,'operation'=>'merchant.product_inputs.preview','read_only'=>true,
            'external_effects'=>false,'partner_attributes_preview_only'=>$custom!==[],
            'merchant_approval'=>'unverified','youtube_channel_link'=>'unverified',
            'input_count'=>count($items),'excluded_count'=>(int)$report['excluded_count'],
            'productInputs'=>$items];
    }

    /** Explicit owner-supplied values for an offline proposal. No account is linked
     * and these attributes never enter the default RSS feed or trigger a write. */
    public static function partnerPreviewAttributes(string $storeUrl,string $storeName,string $group,array $channels): array {
        $parts=parse_url($storeUrl);
        if(!is_array($parts)||($parts['scheme']??'')!=='https'||empty($parts['host'])
            ||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])
            ||!filter_var($storeUrl,FILTER_VALIDATE_URL)||strlen($storeUrl)>2048){
            throw new \InvalidArgumentException('Use the actual HTTPS store URL.');
        }
        foreach([$storeName,$group] as $value) {
            if(trim($value)!==$value||$value===''||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>200||preg_match('/[\x00-\x1f\x7f]/',$value)){
                throw new \InvalidArgumentException('Use the actual store name and assigned group identifier.');
            }
        }
        if(!preg_match('/^[^:]+:[^:]+$/Du',$group)||trim(explode(':',$group)[0])===''||trim(explode(':',$group)[1])==='') {
            throw new \InvalidArgumentException('Use the assigned platform group identifier, not an invented store ID.');
        }
        if($channels===[]||!array_is_list($channels)||count($channels)>20){
            throw new \InvalidArgumentException('Provide one to twenty distinct actual YouTube channel IDs.');
        }
        $attributes=[['name'=>'youtube store url','value'=>$storeUrl],['name'=>'youtube store name','value'=>$storeName],['name'=>'youtube group id','value'=>$group]];
        $seen=[];
        foreach($channels as $channel){
            if(!is_string($channel)||!preg_match('/^UC[A-Za-z0-9_-]{22}$/D',$channel)||isset($seen[$channel]))throw new \InvalidArgumentException('Use distinct actual UC-prefixed YouTube channel IDs, not handles.');
            $seen[$channel]=true;
            $attributes[]=['name'=>'youtube channel id','value'=>$channel];
        }
        return $attributes;
    }

    /** Inspect a real authorized account/source and the matching local offers.
     * No uploads, destination changes, channel-link requests, or generic proxy. */
    public function diagnose(array $report,string $account,string $source,string $language,string $label,string $country,string $accessToken): array {
        self::validateLocale($language,$label);
        if(!preg_match('/^[1-9][0-9]{0,19}$/D',$account)||!preg_match('/^[1-9][0-9]{0,19}$/D',$source)||!preg_match('/^[A-Z]{2}$/D',$country)){
            throw new \InvalidArgumentException('Explicit valid Merchant account, data source and target country required.');
        }
        if($accessToken===''||strlen($accessToken)>8192||!preg_match('/^[A-Za-z0-9._~+\/=\-]+$/D',$accessToken)){
            throw new \InvalidArgumentException('Supply an authorized ephemeral Merchant API access token.');
        }
        $sourceName='accounts/'.$account.'/dataSources/'.$source;
        $resource=$this->get('/datasources/v1/'.$sourceName,$accessToken);
        $primary=$resource['primaryProductDataSource']??null;
        if(($resource['name']??null)!==$sourceName||!is_array($primary)||!empty($primary['legacyLocal'])){
            throw new \DomainException('Selected response is not the requested online primary product source.');
        }
        if((isset($primary['contentLanguage'])&&$primary['contentLanguage']!==''&&$primary['contentLanguage']!==$language)
            ||(isset($primary['feedLabel'])&&$primary['feedLabel']!==''&&$primary['feedLabel']!==$label)){
            throw new \DomainException('Selected source does not match the requested language/feed label.');
        }
        $sourceCountries=$primary['countries']??[];
        if(!is_array($sourceCountries)||!array_is_list($sourceCountries))throw new \DomainException('Invalid source countries.');
        foreach($sourceCountries as $code)if(!is_string($code)||!preg_match('/^[A-Z]{2}$/D',$code))throw new \DomainException('Invalid source country.');
        $countryState=$sourceCountries===[]?'not_observed':(in_array($country,$sourceCountries,true)?'listed':'not_listed');
        $destination='not_observed';
        $destinations=$primary['destinations']??[];
        if(!is_array($destinations)||!array_is_list($destinations))throw new \DomainException('Invalid data source destinations.');
        foreach($destinations as $d){
            if(!is_array($d))throw new \DomainException('Invalid data source destination.');
            if(($d['destination']??null)==='YOUTUBE_SHOPPING'){
                $state=match($d['state']??null){'ENABLED'=>'enabled','DISABLED'=>'disabled',default=>'unknown'};
                if($destination!=='not_observed'&&$destination!==$state)throw new \DomainException('Contradictory destination response.');
                $destination=$state;
            }
        }
        // Only include matching local identities, never unrelated merchant products.
        $local=[];
        foreach($report['items'] as $item)$local[$item['id']]=['offer_id'=>$item['id'],'state'=>'unobserved','issues'=>[]];
        $observed=[];$token='';$seenTokens=[];$pages=0;$complete=false;
        do {
            $query=['pageSize'=>self::PAGE_SIZE];
            if($token!=='')$query['pageToken']=$token;
            $page=$this->get('/products/v1/accounts/'.$account.'/products?'.http_build_query($query,'','&',PHP_QUERY_RFC3986),$accessToken);
            $products=$page['products']??[];
            if(!is_array($products)||!array_is_list($products)||count($products)>self::PAGE_SIZE)throw new \DomainException('Invalid processed product list.');
            foreach($products as $product){
                if(!is_array($product)||!is_string($product['name']??null)||!str_starts_with($product['name'],'accounts/'.$account.'/products/'))throw new \DomainException('Unexpected account product response.');
                $id=$product['offerId']??null;
                if(!is_string($id)||!isset($local[$id])||($product['dataSource']??null)!==$sourceName
                    ||($product['contentLanguage']??null)!==$language||($product['feedLabel']??null)!==$label||!empty($product['legacyLocal']))continue;
                if(isset($observed[$id]))throw new \DomainException('Duplicate processed offer in selected source.');
                $observed[$id]=true;
                $state='destination_not_observed';
                $statuses=$product['productStatus']['destinationStatuses']??[];
                if(!is_array($statuses)||!array_is_list($statuses))throw new \DomainException('Invalid destination status.');
                foreach($statuses as $status){
                    if(!is_array($status))throw new \DomainException('Invalid destination status record.');
                    if(($status['reportingContext']??null)!=='YOUTUBE_SHOPPING')continue;
                    $flags=[];
                    foreach(['approvedCountries'=>'approved','pendingCountries'=>'pending','disapprovedCountries'=>'disapproved'] as $key=>$value){
                        $list=$status[$key]??[];
                        if(!is_array($list)||!array_is_list($list))throw new \DomainException('Invalid destination countries.');
                        if(in_array($country,$list,true))$flags[]=$value;
                    }
                    $next=count($flags)>1?'conflicting_status':($flags[0]??'country_not_observed');
                    if($state!=='destination_not_observed'&&$state!==$next)$state='conflicting_status';
                    else $state=$next;
                }
                if(!empty($product['archived']))$state='archived';
                $issues=[];
                $providerIssues=$product['productStatus']['itemLevelIssues']??[];
                if(!is_array($providerIssues)||!array_is_list($providerIssues))throw new \DomainException('Invalid product issue list.');
                foreach($providerIssues as $issue){
                    if(!is_array($issue)||($issue['reportingContext']??null)!=='YOUTUBE_SHOPPING')continue;
                    $countries=$issue['applicableCountries']??[];
                    if(!is_array($countries)||($countries!==[]&&!in_array($country,$countries,true)))continue;
                    // Bounded machine codes only; no provider error or private text.
                    $code=$issue['code']??null;$severity=$issue['severity']??null;
                    if(is_string($code)&&preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$code)
                        &&in_array($severity,['NOT_IMPACTED','DEMOTED','DISAPPROVED','SEVERITY_UNSPECIFIED'],true))$issues[]=['code'=>$code,'severity'=>$severity];
                    if(count($issues)>=20)break;
                }
                $local[$id]=['offer_id'=>$id,'state'=>$state,'issues'=>$issues];
            }
            ++$pages;
            $next=$page['nextPageToken']??'';
            if(!is_string($next)||strlen($next)>2048||preg_match('/[\x00-\x1f\x7f]/',$next))throw new \DomainException('Invalid product pagination token.');
            if($next!==''&&isset($seenTokens[$next]))throw new \DomainException('Repeated product pagination token.');
            $token=$next;
            if($token===''){$complete=true;break;}
            $seenTokens[$token]=true;
        }while($pages<self::MAX_PAGES);
        if($complete)foreach($local as &$item)if($item['state']==='unobserved')$item['state']='not_in_selected_processed_source';
        unset($item);
        $counts=array_count_values(array_column($local,'state'));
        return ['schema_version'=>1,'operation'=>'merchant.youtube.destination_diagnostics','read_only'=>true,
            'account'=>$account,'data_source'=>$sourceName,'source_input'=>in_array($resource['input']??null,['API','FILE','UI','AUTOFEED'],true)?$resource['input']:'unknown',
            'country'=>$country,'source_country'=>$countryState,'content_language'=>$language,'feed_label'=>$label,'source_youtube_destination'=>$destination,
            'pages_read'=>$pages,'complete'=>$complete,'local_count'=>count($local),'counts'=>$counts,
            'channel_link'=>'unverified','items'=>array_values($local)];
    }

    private function get(string $path,string $token): array {
        $url=self::HOST.$path;
        $headers=['Accept: application/json','Authorization: Bearer '.$token];
        if($this->transport!==null)[$status,$raw]=($this->transport)($url,$headers);
        else {
            $curl=curl_init($url);$raw='';$max=8*1024*1024;
            $protocolOption=defined('CURLOPT_PROTOCOLS_STR')?[CURLOPT_PROTOCOLS_STR=>'HTTPS']:[CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS];
            curl_setopt_array($curl,$protocolOption+[CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($c,string $chunk) use (&$raw,$max):int{
                    if(strlen($raw)+strlen($chunk)>$max)return 0;
                    $raw.=$chunk;return strlen($chunk);
                }]);
            $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);unset($curl);
            if($ok===false)throw new \DomainException('Google Merchant API request failed or exceeded its response bound.');
        }
        if($status===401)throw new \DomainException('Merchant API access token expired or was rejected.');
        if($status===403)throw new \DomainException('Merchant API access denied. Check authorized account, scope and developer registration.');
        if($status===429)throw new \DomainException('Merchant API quota reached.');
        if($status!==200||!is_string($raw)||strlen($raw)>8*1024*1024)throw new \DomainException('Merchant API response unavailable.');
        $decoded=json_decode($raw,true);
        if(!is_array($decoded)||array_is_list($decoded)&&$decoded!==[]||isset($decoded['error'])||!str_starts_with(ltrim($raw),'{')){
            throw new \DomainException('Invalid Merchant API response.');
        }
        return $decoded;
    }
}
