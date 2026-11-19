<?php
declare(strict_types=1);

use App\Integrations\GoogleSiteKit\GoogleReportingClient;
use App\Services\SeoService;

$tests['Google owner reporting offline grant, paginated choices and 28-day reports use dedicated read scopes'] = static function (): void {
    $base = ['google_client_id'=>'fixture-client', 'google_client_secret'=>'fixture-secret'];
    $scope = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly';
    $requests = [];
    $responses = [
        ['scope'=>$scope,'refresh_token'=>'offline-fixture'], ['access_token'=>'access-fixture'],
        ['accountSummaries'=>[['propertySummaries'=>[['property'=>'properties/1','displayName'=>'First']]]],'nextPageToken'=>'two'],
        ['accountSummaries'=>[['propertySummaries'=>[['property'=>'properties/2','displayName'=>'Second']]]]],
        ['siteEntry'=>[['siteUrl'=>'sc-domain:example.test','permissionLevel'=>'siteFullUser'], ['siteUrl'=>'https://denied.test/','permissionLevel'=>'siteUnverifiedUser']]],
        ['reports'=>array_map(static fn($label,$value)=>['metricHeaders'=>[['name'=>'metric']], 'rows'=>[['dimensionValues'=>[['value'=>$label]],'metricValues'=>[['value'=>$value]]]]], ['', '/shop', 'organic'], ['14','31','11'])],
        ['rows'=>[['clicks'=>6.0,'impressions'=>52.0]]], ['rows'=>[['keys'=>['fixture query'],'clicks'=>2]]], [],
    ];
    $client = new GoogleReportingClient($base, static function ($url,$headers,$body) use (&$requests,&$responses) {
        $requests[] = compact('url','headers','body');
        return [200, json_encode(array_shift($responses), JSON_THROW_ON_ERROR)];
    });
    $callback='https://example.test/admin/google-reporting/callback';
    parse_str(parse_url($client->consentUrl($callback,'state-fixture'), PHP_URL_QUERY), $query);
    assertSame([$scope,'offline','state-fixture',$callback], [$query['scope'],$query['access_type'],$query['state'],$query['redirect_uri']], 'Admin consent parameters');
    assertSame('offline-fixture',$client->exchange('code',$callback),'Offline access requires both scopes');
    $access=$client->refresh('offline-fixture');
    parse_str($requests[1]['body'], $refresh);
    assertSame('refresh_token',$refresh['grant_type'],'Server refresh');
    $choices=$client->choices($access);
    assertSame(['properties/1','properties/2'],array_keys($choices['property']['data']),'All pages of authorized GA4 properties');
    assertSame(['sc-domain:example.test'],array_keys($choices['site']['data']),'Unverified site excluded');
    $report=$client->reports($access,'properties/2','sc-domain:example.test');
    assertSame([14,31,11],array_map(static fn($rows)=>$rows[0]['value'],array_values($report['analytics']['data'])),'GA4 totals and rankings');
    assertSame(6.0,(float)$report['search']['data']['summary']['clicks'],'Totals include even anonymous traffic');
    assertSame(2,$report['search']['data']['queries'][0]['value'],'Ranked query clicks');
    $ga=json_decode($requests[5]['body'],true);
    assertSame('28daysAgo',$ga['requests'][0]['dateRanges'][0]['startDate'],'GA start inclusive');
    assertSame('yesterday',$ga['requests'][0]['dateRanges'][0]['endDate'],'GA end inclusive');
    assertSame('sessionSourceMedium',$ga['requests'][2]['dimensions'][0]['name'],'Session scoped traffic source');
    assertSame(['Authorization: Bearer access-fixture'],array_values(array_filter($requests[5]['headers'],static fn($h)=>str_starts_with($h,'Authorization:'))),'Access token stays in server header');
    assertTrue(str_contains($requests[6]['url'],'sc-domain%3Aexample.test'),'Search site encoded within fixed Google URL');
    $range=json_decode($requests[6]['body'],true);
    assertSame(27,(new DateTimeImmutable($range['startDate']))->diff(new DateTimeImmutable($range['endDate']))->days,'Search window includes both endpoints');
    assertTrue(!isset($range['dimensions']),'Search aggregate uses independent query');
    $client->revoke('offline-fixture');
    assertSame('https://oauth2.googleapis.com/revoke',$requests[8]['url'],'Revocation endpoint');
    assertSame([], $responses, 'All fixture calls accounted for');
};

$tests['Google reporting shows no rows, quota, denied consent and revoked grants without inventing metrics'] = static function (): void {
    $base=['google_client_id'=>'id','google_client_secret'=>'secret'];
    $responses = [
        [403,'{"error":{"errors":[{"reason":"rateLimitExceeded"}]}}'], [200,'{}'], [200,'{}'],
        [200,'{"scope":"https://www.googleapis.com/auth/analytics.readonly","refresh_token":"partial"}'],
        [400,'{"error":"invalid_grant"}'], [400,'{"error":"invalid_token"}'], [503,'not JSON'],
    ];
    $client=new GoogleReportingClient($base, static function () use (&$responses) { return array_shift($responses); });
    $r=$client->reports('access','properties/42','https://example.test/');
    assertTrue(str_contains($r['analytics']['error'],'quota'),'Quota is explicit');
    assertSame(['summary'=>null,'queries'=>[]],$r['search']['data'],'Independent empty Search Console result');
    foreach ([['exchange',['code','callback'],'permissions'], ['refresh',['bad-token'],'revoked']] as [$method,$args,$word]) {
        try { $client->$method(...$args); throw new RuntimeException('Expected reporting failure'); }
        catch (DomainException $error) { assertTrue(str_contains($error->getMessage(),$word),'Actionable provider error'); }
    }
    $client->revoke('already-revoked');
    try { $client->revoke('token'); throw new RuntimeException('Expected transient error'); }
    catch (DomainException $error) { assertTrue(str_contains($error->getMessage(),'unavailable'),'Failed revocation can be retried'); }
};

$tests['Saved SEO site name and card attribution reach default metadata'] = static function (): void {
    $site='https://example.test';
    $seo=(new SeoService(['seo_site_name'=>' My Store ','seo_twitter_handle'=>' My_store '],$site))->page('home');
    assertSame('My Store',$seo['og_site_name'],'Social site name');
    assertTrue(str_contains($seo['title'],'My Store'),'Configured default page title brand');
    assertSame('@My_store',$seo['twitter_site'],'Card attribution normalized');
    $default=(new SeoService(['seo_site_name'=>'  ','seo_twitter_handle'=>'bad handle'],$site))->page('home');
    assertTrue(trim($default['og_site_name'])!=='','Blank optional setting preserves default organization name');
    assertSame('',$default['twitter_site'],'Invalid handle omitted');
    assertTrue(str_contains(fileRead('views/layouts/app.php'),'name="twitter:site"'),'Public layout emits configured attribution');
};
