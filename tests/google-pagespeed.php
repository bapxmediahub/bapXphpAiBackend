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
