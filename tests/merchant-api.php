<?php
declare(strict_types=1);
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{MerchantApiService,MerchantFeedService};

$checks=0;
function check(bool $ok,string $message):void {
    global $checks;
    ++$checks;
    if(!$ok)throw new RuntimeException($message);
}
function rejects(callable $fn,string $message):void {
    try{$fn();}catch(InvalidArgumentException|DomainException){check(true,$message);return;}
    check(false,$message);
}
$base=['id'=>'catalogue-1','slug'=>'actual-item','name'=>'Fixture product','description'=>'Actual fixture description',
    'price'=>105.15,'offer_price'=>0,'stock_status'=>'in_stock','status'=>'visible',
    'image_url'=>'/assets/images/blog/order-products.webp','brand'=>'Fixture maker','gtin'=>'012345678905',
    'mpn'=>'Part-A','condition'=>'new','identifier_exists'=>'yes','supplier_cost'=>17,'private'=>'not-exported'];
$rows=[$base,array_replace($base,['id'=>'catalogue-2','slug'=>'item-2','price'=>0.01,'stock_status'=>'out_of_stock',
    'brand'=>'','gtin'=>'','mpn'=>'','condition'=>'','identifier_exists'=>'no']),
    array_replace($base,['id'=>'catalogue-3','slug'=>'item-3','brand'=>'','gtin'=>'','mpn'=>'','condition'=>'','identifier_exists'=>'']),
    array_replace($base,['id'=>'catalogue-4','slug'=>'item-4']),
    array_replace($base,['id'=>'hidden','status'=>'hidden'])];
$feed=new MerchantFeedService(origin:'https://configured.example');
$report=$feed->inspect($rows);
$api=new MerchantApiService(static fn()=>throw new RuntimeException('Preview made an HTTP request'));
$preview=$api->preview($report,'en','IN');
check($preview['input_count']===4&&$preview['excluded_count']===1,'Preview preserves feed inclusion decisions.');
check($preview['external_effects']===false&&$preview['merchant_approval']==='unverified'&&$preview['youtube_channel_link']==='unverified','Preview is not approval or linking.');
$inputs=$preview['productInputs'];
check($inputs[0]['offerId']==='catalogue-1'&&$inputs[0]['contentLanguage']==='en'&&$inputs[0]['feedLabel']==='IN','Stable existing offer identity and explicit locale.');
check($inputs[0]['productAttributes']['price']===['amountMicros'=>'105150000','currencyCode'=>'INR'],'Paise converts exactly into micros.');
check($inputs[1]['productAttributes']['price']['amountMicros']==='10000','One-paise conversion is exact.');
check($inputs[1]['productAttributes']['availability']==='OUT_OF_STOCK','Stock enum maps explicitly.');
check($inputs[0]['productAttributes']['gtins']===['012345678905'],'GTIN stays a leading-zero string.');
check($inputs[0]['productAttributes']['condition']==='NEW'&&$inputs[0]['productAttributes']['identifierExists']===true,'Owner identity and condition become typed attributes.');
check($inputs[1]['productAttributes']['identifierExists']===false&&!isset($inputs[1]['productAttributes']['condition']),'Confirmed no assigned identifiers is distinct from unknown condition.');
check(!isset($inputs[2]['productAttributes']['identifierExists']),'Unknown identity not inferred.');
check(!isset($inputs[0]['customAttributes'])&&!isset($inputs[0]['name'])&&!isset($inputs[0]['channel']),'No implicit partner flags, fabricated resource name or obsolete field.');
check(!str_contains(json_encode($preview),'not-exported')&&!str_contains(json_encode($preview),'supplier_cost'),'No private record fields projected.');
rejects(fn()=>$api->preview($report,'EN','IN'),'Explicit locale required.');
rejects(fn()=>$api->preview($report,'en','in/'),'Malformed feed label denied.');
$bad=$report;$bad['items'][]=$bad['items'][0];
rejects(fn()=>$api->preview($bad,'en','IN'),'Duplicate offer is not silently overwritten.');
$bad=$report;$bad['items'][0]['price']='0.00 INR';
rejects(fn()=>$api->preview($bad,'en','IN'),'Non-payable price denied.');
$channels=['UC'.str_repeat('A',22),'UC'.str_repeat('B',22)];
$attributes=MerchantApiService::partnerPreviewAttributes('https://configured.example','Fixture shop','fixture:shop',$channels);
$partner=$api->preview($report,'en','IN',$attributes);
check($partner['partner_attributes_preview_only']===true&&$partner['external_effects']===false,'Partner values are explicitly preview-only.');
check(array_column(array_slice($partner['productInputs'][0]['customAttributes'],3),'value')===$channels,'Repeated channel attribute retains both actual inputs.');
check(!str_contains($feed->xml($report),'youtube channel id'),'Partner proposal never mutates public feed.');
rejects(fn()=>$api->preview($report,'en','IN',[['name'=>'includedDestination','value'=>'YOUTUBE_SHOPPING']]),'Arbitrary partner claims are not accepted.');

foreach([['@handle'],[$channels[0],$channels[0]],[['invalid']],[]] as $badChannels)
    rejects(fn()=>MerchantApiService::partnerPreviewAttributes('https://configured.example','Fixture shop','fixture:shop',$badChannels),'Reject unsupported or duplicate channel list.');
rejects(fn()=>MerchantApiService::partnerPreviewAttributes('http://configured.example','Fixture shop','fixture:shop',$channels),'Partner store must be HTTPS.');
rejects(fn()=>MerchantApiService::partnerPreviewAttributes('https://configured.example','Fixture shop','missing-group',$channels),'Explicit assigned group required.');

$source=['name'=>'accounts/123/dataSources/456','input'=>'API','primaryProductDataSource'=>[
    'feedLabel'=>'IN','contentLanguage'=>'en','countries'=>['IN'],'destinations'=>[['destination'=>'YOUTUBE_SHOPPING','state'=>'ENABLED']]]];
$makeProduct=static function(string $id,string $context='YOUTUBE_SHOPPING',array $approved=['IN'],array $pending=[],array $denied=[],string $source='accounts/123/dataSources/456'):array{
    return ['name'=>'accounts/123/products/en~IN~'.$id,'offerId'=>$id,'contentLanguage'=>'en','feedLabel'=>'IN','dataSource'=>$source,
        'productStatus'=>['destinationStatuses'=>[['reportingContext'=>$context,'approvedCountries'=>$approved,'pendingCountries'=>$pending,'disapprovedCountries'=>$denied]],
        'itemLevelIssues'=>[['reportingContext'=>'YOUTUBE_SHOPPING','applicableCountries'=>['IN'],'code'=>'fixture_issue','severity'=>'NOT_IMPACTED','detail'=>'not-exposed']]]];
};
$requests=[];
$transport=static function(string $url,array $headers)use(&$requests,$source,$makeProduct):array{
    $requests[]=$url;
    check($headers===['Accept: application/json','Authorization: Bearer fixture-token'],'Only expected ephemeral auth headers.');
    if(count($requests)===1)return [200,json_encode($source)];
    if(count($requests)===2)return [200,json_encode(['products'=>[
        $makeProduct('catalogue-1'),$makeProduct('catalogue-2','YOUTUBE_SHOPPING',[],['IN']),
        $makeProduct('catalogue-3','YOUTUBE_AFFILIATE'),$makeProduct('catalogue-4','YOUTUBE_SHOPPING',['IN'],[],[],'accounts/123/dataSources/999'),
        $makeProduct('unrelated')],'nextPageToken'=>'token/?=x'])];
    return [200,json_encode(['products'=>[]])];
};
$diagnostic=(new MerchantApiService($transport))->diagnose($report,'123','456','en','IN','IN','fixture-token');
check($requests===['https://merchantapi.googleapis.com/datasources/v1/accounts/123/dataSources/456',
    'https://merchantapi.googleapis.com/products/v1/accounts/123/products?pageSize=100',
    'https://merchantapi.googleapis.com/products/v1/accounts/123/products?pageSize=100&pageToken=token%2F%3F%3Dx'],'Fixed official host, exact read paths and encoded opaque pagination.');
check($diagnostic['source_youtube_destination']==='enabled'&&$diagnostic['complete']&&$diagnostic['pages_read']===2,'Enabled data source and complete traversal from actual fixture response.');
check($diagnostic['channel_link']==='unverified','Destination approval never implies channel linked.');
check($diagnostic['source_country']==='listed','Explicit country membership comes from provider source.');

check($diagnostic['counts']===['approved'=>1,'pending'=>1,'destination_not_observed'=>1,'not_in_selected_processed_source'=>1],'Distinguish approvals, pending, affiliate and cross-source.');
check(!str_contains(json_encode($diagnostic),'unrelated')&&!str_contains(json_encode($diagnostic),'fixture-token')&&!str_contains(json_encode($diagnostic),'not-exposed'),'Exclude unrelated data, credential and raw provider text.');
$count=0;
$edge=new MerchantApiService(static function()use(&$count,$source,$makeProduct):array{
    if(++$count===1)return [200,json_encode($source)];
    $conflict=$makeProduct('catalogue-1','YOUTUBE_SHOPPING',['IN'],[],['IN']);
    $archived=$makeProduct('catalogue-2')+['archived'=>true];
    $wrongCountry=$makeProduct('catalogue-3','YOUTUBE_SHOPPING',['US']);
    $denied=$makeProduct('catalogue-4','YOUTUBE_SHOPPING',[],[],['IN']);
    return [200,json_encode(['products'=>[$conflict,$archived,$wrongCountry,$denied]])];
});
$edgeReport=$edge->diagnose($report,'123','456','en','IN','IN','fixture-token');
check($edgeReport['counts']===['conflicting_status'=>1,'archived'=>1,'country_not_observed'=>1,'disapproved'=>1],'Conflicting, archived, different country and disapproved offers cannot become approved.');
$requests=[];
$bounded=new MerchantApiService(static function(string $url,array $headers)use(&$requests,$source):array{
    $requests[]=$url;
    return [200,json_encode(count($requests)===1?$source:['products'=>[],'nextPageToken'=>'page-'.count($requests)])];
});
$r=$bounded->diagnose($report,'123','456','en','IN','IN','fixture-token');
check($r['complete']===false&&$r['pages_read']===5&&$r['counts']===['unobserved'=>4]&&count($requests)===6,'Bounded scan cannot label unseen items missing.');
foreach([401,403,429,503,302] as $http){
    $badApi=new MerchantApiService(static fn()=>[$http,'{"error":{"message":"fixture-token"}}']);
    rejects(fn()=>$badApi->diagnose($report,'123','456','en','IN','IN','fixture-token'),'Provider failure is explicit, not approval.');
}
$badApi=new MerchantApiService(static fn()=>[200,'[]']);
rejects(fn()=>$badApi->diagnose($report,'123','456','en','IN','IN','fixture-token'),'Malformed response rejected.');
$wrong=$source;$wrong['name']='accounts/999/dataSources/456';
$badApi=new MerchantApiService(static fn()=>[200,json_encode($wrong)]);
rejects(fn()=>$badApi->diagnose($report,'123','456','en','IN','IN','fixture-token'),'Cross-account response rejected.');
$badApi=new MerchantApiService(static fn()=>throw new RuntimeException('Unexpected network call'));
rejects(fn()=>$badApi->diagnose($report,'123/other','456','en','IN','IN','fixture-token'),'Invalid account rejected before HTTP.');
rejects(fn()=>$badApi->diagnose($report,'123','456','en','IN','IN',''),'Missing token rejected before HTTP.');
$n=0;$duplicate=new MerchantApiService(static function()use(&$n,$source):array{return [200,json_encode(++$n===1?$source:['products'=>[],'nextPageToken'=>'same'])];});
rejects(fn()=>$duplicate->diagnose($report,'123','456','en','IN','IN','fixture-token'),'Repeated cursor is not an infinite loop.');
echo "PASS Merchant API native compatibility preview and read-only diagnostics ($checks checks)\n";
