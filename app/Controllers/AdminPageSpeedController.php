<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Integrations\GoogleSiteKit\PageSpeedInsightsClient;
use App\Services\{AuthService, PublicSiteUrlService};
use DomainException;

/** Owner-only PageSpeed reports for the configured public website. */
final class AdminPageSpeedController extends BaseController
{
    private const PAGE = '/admin/google-pagespeed';
    private const KEY = 'bapx_pagespeed_admin_report';
    private const CACHE_SECONDS = 600;

    protected string $layout = 'admin';
    protected string $seoKey = 'admin';

    public function __construct()
    {
        (new AuthService())->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') $this->validateCsrf();
    }

    public function index(): void
    {
        $origin = '';
        $notice = '';
        try { $origin = PublicSiteUrlService::origin(); }
        catch (\RuntimeException) { $notice = 'Configure the public HTTPS website before running PageSpeed.'; }
        $record = $_SESSION[self::KEY] ?? null;
        if (!is_array($record) || ($record['origin'] ?? null) !== $origin
            || !is_int($record['at'] ?? null)
            || $record['at'] < time() - 1800) $record = null;
        $this->seoOverrides = ['title'=>'PageSpeed Insights'];
        $this->render('admin/google-pagespeed', [
            'origin'=>$origin,
            'notice'=>$notice,
            'record'=>$record,
            'cooldown'=>$record !== null && $record['at'] > time()-self::CACHE_SECONDS,
            'pageTitle'=>'PageSpeed Insights',
        ]);
    }

    public function run(): void
    {
        $record = $_SESSION[self::KEY] ?? null;
        if (is_array($record) && is_int($record['at'] ?? null)
            && $record['at'] > time()-self::CACHE_SECONDS) {
            $this->flash('A PageSpeed report was already requested recently. View the saved result or try again later.', 'info');
            $this->redirect(self::PAGE);
        }
        try {
            $origin = PublicSiteUrlService::origin();
            // Claims the cooldown before provider requests, including failures.
            $_SESSION[self::KEY] = ['at'=>time(), 'origin'=>$origin, 'results'=>[]];
            $client = new PageSpeedInsightsClient();
            $results = [];
            foreach (['mobile','desktop'] as $strategy) {
                try { $results[] = $client->analyze($origin,$strategy); }
                catch (DomainException $error) {
                    $results[] = [
                        'strategy'=>$strategy,
                        'status'=>'unavailable',
                        'message'=>$error->getMessage(),
                        'performance_score'=>null,
                    ];
                }
            }
            $_SESSION[self::KEY]['results'] = $results;
        } catch (\RuntimeException $error) {
            $this->flash('The configured public website is unavailable.', 'error');
        }
        $this->redirect(self::PAGE);
    }
}
