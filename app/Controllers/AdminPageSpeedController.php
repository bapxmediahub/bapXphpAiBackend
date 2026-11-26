<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Integrations\GoogleSiteKit\{PageSpeedInsightsClient,PageSpeedReportCache};
use App\Services\{AuthService, PublicSiteUrlService, SecretService};
use DomainException;

/** Owner-only PageSpeed reports for the configured public website. */
final class AdminPageSpeedController extends BaseController
{
    private const PAGE = '/admin/google-pagespeed';

    protected string $layout = 'admin';
    protected string $seoKey = 'admin';

    public function __construct()
    {
        (new AuthService())->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') $this->validateCsrf();
    }

    public function index(): void
    {
        $origin='';$notice='';$record=null;$cooldown=false;
        try {
            $origin=PublicSiteUrlService::origin();
            $record=(new PageSpeedReportCache())->read($origin);
            $cooldown=$record!==null && $record['at']>time()-PageSpeedReportCache::COOLDOWN;
        } catch (\Throwable) {
            $notice='The configured website or shared report cache is unavailable.';
        }
        $this->seoOverrides=['title'=>'PageSpeed Insights'];
        $this->render('admin/google-pagespeed',[
            'origin'=>$origin,'notice'=>$notice,'record'=>$record,'cooldown'=>$cooldown,
            'pageTitle'=>'PageSpeed Insights',
        ]);
    }

    public function run(): void
    {
        try {
            $origin=PublicSiteUrlService::origin();
            $secrets=(new SecretService())->all();
            $key=trim((string)($secrets['google_pagespeed_api_key']??''));
            $enabled=($secrets['google_pagespeed_key_enabled']??($key!==''?'1':'0'))==='1';
            if ($enabled && $key==='') throw new DomainException('Save a dedicated PageSpeed API key or select anonymous mode in Integrations.');
            $client=new PageSpeedInsightsClient(apiKey:$enabled?$key:'');
            if (!(new PageSpeedReportCache())->run($origin,$client))
                $this->flash('A PageSpeed report was already requested recently. View the saved result or try again later.','info');
        } catch (DomainException $error) {
            $this->flash($error->getMessage(),'error');
        } catch (\Throwable) {
            $this->flash('PageSpeed report state is unavailable. Check the saved report before retrying.','error');
        }
        $this->redirect(self::PAGE);
    }
}
