<?php
declare(strict_types=1);

use App\Integrations\GoogleSiteKit\PageSpeedInsightsClient;

$tests['Native PageSpeed owner reports use the fixed Google API and bounded observed metrics'] = static function (): void {
    $urls = [];
    $fixture = [
        'lighthouseResult'=>[
            'categories'=>['performance'=>['score'=>0.92]],
            'audits'=>[
                'first-contentful-paint'=>['displayValue'=>'0.9 s'],
                'largest-contentful-paint'=>['displayValue'=>'1.8 s'],
                'speed-index'=>['displayValue'=>'2.1 s'],
                'total-blocking-time'=>['displayValue'=>'30 ms'],
                'cumulative-layout-shift'=>['displayValue'=>'0.02'],
                'unused-css-rules'=>['title'=>'Reduce unused CSS','details'=>['type'=>'opportunity','overallSavingsMs'=>650]],
                'unused-javascript'=>['title'=>'Remove unused JavaScript','details'=>['type'=>'opportunity','overallSavingsMs'=>1200]],
            ],
        ],
        'loadingExperience'=>['metrics'=>[
            'LARGEST_CONTENTFUL_PAINT_MS'=>['percentile'=>1810,'category'=>'FAST'],
            'CUMULATIVE_LAYOUT_SHIFT_SCORE'=>['percentile'=>3,'category'=>'FAST'],
        ]],
    ];
    $client = new PageSpeedInsightsClient(static function(string $url) use (&$urls,$fixture): array {
        $urls[] = $url;
        return [200,json_encode($fixture, JSON_THROW_ON_ERROR)];
    });
    $result=$client->analyze('https://example.org/','mobile');
    assertSame('mobile',$result['strategy'],'User cannot select an arbitrary report mode');
    assertSame('https://example.org/',$result['target'],'Only configured public homepage is measured');
    assertSame('complete',$result['status'],'Lighthouse score marks an observed complete score');
    assertSame(92,$result['performance_score'],'Provider score is normalized to a percentage');
    assertSame('1.8 s',$result['lab'][1]['display'],'Lab values are read from provider, not invented');
    assertSame('FAST',$result['field'][0]['category'],'CrUX status retained');
    assertSame(1810.0,$result['field'][0]['percentile'],'CrUX percentile retained');
    assertSame('1,810 ms',$result['field'][0]['display'],'CrUX latency has units');
    assertSame('0.03',$result['field'][1]['display'],'CLS is normalized from provider centi-units');
    assertSame(null,$result['field'][2]['percentile'],'Missing field metric is unknown, not zero');
    assertSame('Remove unused JavaScript',$result['opportunities'][0]['title'],'Actionable lab opportunities sorted by savings');
    assertSame(1200,$result['opportunities'][0]['savings_ms'],'Estimated lab saving retained');
    $url=parse_url($urls[0]);
    parse_str((string)$url['query'],$query);
    assertSame('www.googleapis.com',$url['host'],'Only Google API can be called');
    assertSame('/pagespeedonline/v5/runPagespeed',$url['path'],'Fixed PageSpeed v5 endpoint');
    assertSame(['url'=>'https://example.org/','strategy'=>'mobile','category'=>'performance'],$query,
        'The target URL is from configured public origin, not user input');

    $empty = new PageSpeedInsightsClient(static fn()=>[200,json_encode([
        'lighthouseResult'=>['categories'=>['performance'=>['score'=>null]],'audits'=>[]],
    ],JSON_THROW_ON_ERROR)]);
    $partial=$empty->analyze('https://example.org','desktop');
    assertSame('partial',$partial['status'],'Absent lab data stays partial');
    assertSame(null,$partial['performance_score'],'Unknown score is not silently zero');
    assertSame('unavailable',$partial['field_status'],'No CrUX data is stated');

    foreach (['mobile-fast','arbitrary'] as $badStrategy) {
        try { $client->analyze('https://example.org',$badStrategy); throw new \RuntimeException('Invalid strategy incorrectly accepted'); }
        catch (\DomainException $expected) {assertTrue(str_contains($expected->getMessage(),'mobile or desktop'),'Strategy rejected');}
    }
    foreach (['http://example.org','https://127.0.0.1','https://localhost','https://internal.local','https://example.org:8080'] as $badOrigin) {
        try { $client->analyze($badOrigin,'mobile'); throw new \RuntimeException('Internal or invalid origin incorrectly accepted'); }
        catch (\DomainException $expected) {assertTrue($expected->getMessage()!=='','Private target denied');}
    }
    assertSame(1,count($urls),'Invalid request never reaches Google');

    foreach ([[429,'quota'],[403,'denied'],[503,'could not'],[200,'incomplete']] as [$status,$fragment]) {
        $failing = new PageSpeedInsightsClient(static fn()=>[$status,'{}']);
        try { $failing->analyze('https://example.org','desktop'); throw new \RuntimeException('Expected PageSpeed error'); }
        catch (\DomainException $expected) {assertTrue(str_contains($expected->getMessage(),$fragment),'PageSpeed status handled safely');}
    }
    $huge = new PageSpeedInsightsClient(static fn()=>[200,str_repeat('x', 3*1024*1024 + 1)]);
    try {$huge->analyze('https://example.org','mobile'); throw new \RuntimeException('Oversize provider response accepted');}
    catch (\DomainException $expected) {assertTrue(str_contains($expected->getMessage(),'larger'),'Oversized provider response refused');}

    routeExists('/admin/google-pagespeed','Native read-only admin PageSpeed view registered');
    routeExists('/admin/google-pagespeed/run','CSRF-protected explicit admin analysis registered');
    fileContains('views/admin/google-pagespeed.php','name="_csrf"','PageSpeed analyze form includes CSRF token');
    fileContains('views/admin/google-pagespeed.php','no WordPress plugin','Native-only UI documents no plugin dependency');
};

$tests['PageSpeed shared report admission and prior success preserve evidence across owner sessions'] = static function (): void {
    $root=sys_get_temp_dir().'/bapx-psi-fixture-'.bin2hex(random_bytes(6));
    mkdir($root,0700);
    $path=$root.'/report.json';$origin='https://example.org';$now=1800000000;
    $cache=new \App\Integrations\GoogleSiteKit\PageSpeedReportCache($path);
    $peer=new \App\Integrations\GoogleSiteKit\PageSpeedReportCache($path);
    $key='fixture-psi-'.str_repeat('A',32);
    $calls=0;
    $fixture=['lighthouseResult'=>['categories'=>['performance'=>['score'=>0.92]],'audits'=>[]]];
    $success=new PageSpeedInsightsClient(static function(string $url,array $headers) use (&$calls,$fixture,$key,$peer,$origin,$now):array {
        $calls++;
        assertTrue(!str_contains($url,$key),'Dedicated key is not exposed in query URL');
        assertTrue(in_array('X-Goog-Api-Key: '.$key,$headers,true),'Key sent only in Google request header');
        assertSame(true,$peer->read($origin,$now)['running'],'Claim persisted before provider transfer');
        assertSame(false,$peer->run($origin,new PageSpeedInsightsClient(static fn()=>throw new RuntimeException('Duplicate call')),$now),'Independent owner instance cannot claim the same cooldown');
        return [200,json_encode($fixture,JSON_THROW_ON_ERROR)];
    },$key);
    try {
        assertSame(null,$cache->read($origin,$now),'Initial cache is empty');
        assertTrue($cache->run($origin,$success,$now),'Explicit first analysis');
        assertSame(2,$calls,'Two authorized strategy calls');
        $first=$peer->read($origin,$now);
        assertSame(false,$first['running'],'Completed claim is distinguishable from interrupted request');
        assertSame(['mobile','desktop'],array_column($first['results'],'strategy'),'Separate strategies');
        assertSame(92,$first['last_good']['mobile']['report']['performance_score'],'Observed score cached');
        assertSame(92,$first['last_good']['desktop']['report']['performance_score'],'Second observed score cached');
        assertTrue(!str_contains(file_get_contents($path),$key),'No credential in cached projection');
        assertSame(0600,fileperms($path)&0777,'Private cache permissions');
        $failCalls=0;
        $limited=new PageSpeedInsightsClient(static function() use (&$failCalls):array {
            $failCalls++;return [429,'{"error":{"message":"private provider response"}}'];
        });
        assertSame(false,$peer->run($origin,$limited,$now+599),'Cooldown is shared, including session changes');
        assertSame(0,$failCalls,'No provider transfer within cooldown');
        assertTrue($peer->run($origin,$limited,$now+600),'New explicit attempt after cooldown');
        assertSame(1,$failCalls,'Quota rejection suppresses second unnecessary transfer');
        $second=$cache->read($origin,$now+600);
        assertSame(['unavailable','not_attempted'],array_column($second['results'],'status'),'Actual failure distinguished from unsent second strategy');
        assertSame(null,$second['results'][0]['performance_score'],'Quota failure is not a numeric score');
        assertSame($first['last_good'],$second['last_good'],'Previous observed reports retained with original timestamps');
        assertTrue(!str_contains(file_get_contents($path),'private provider response'),'Provider error body is never stored');
        assertSame(null,$cache->read('https://other.example.org',$now+600),'Cache is scoped to configured origin');
        assertSame([],$cache->read($origin,$now+604800)['last_good'],'Historical reports expire independently');
        // Simulate an unfinished persisted attempt, without any provider call.
        $second['running']=true;$second['results']=[];
        file_put_contents($path,json_encode($second,JSON_THROW_ON_ERROR));
        assertSame(false,$peer->run($origin,$limited,$now+601),'Interrupted attempt retains its claim');
        assertSame(1,$failCalls,'Unknown outcome must not be replayed');
        file_put_contents($path,'{broken');
        try { $peer->run($origin,$limited,$now+2000); throw new RuntimeException('Corrupt cache accepted'); }
        catch (JsonException $expected) { assertSame(1,$failCalls,'Corrupt admission state fails closed'); }
    } finally {
        foreach (glob($root.'/*')?:[] as $file) @unlink($file);
        @rmdir($root);
    }
};
