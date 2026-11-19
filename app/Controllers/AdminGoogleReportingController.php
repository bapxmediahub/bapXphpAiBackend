<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Integrations\GoogleSiteKit\GoogleReportingClient;
use App\Services\{AuditLogService, AuthService, SecretService};
use DomainException;

final class AdminGoogleReportingController extends BaseController
{
    private const PAGE = '/admin/google-reporting';
    private SecretService $secrets;
    private GoogleReportingClient $google;
    protected string $layout = 'admin';
    protected string $seoKey = 'admin';

    public function __construct() {
        (new AuthService())->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->validateCsrf();
        $this->secrets = new SecretService();
        $this->google = new GoogleReportingClient($this->secrets->all());
    }

    public function settings(): void {
        $grant = $this->secrets->googleReporting();
        $connected = !empty($grant['refresh_token']);
        $choices = [];
        $message = '';
        if ($connected) {
            try { $choices = $this->google->choices($this->google->refresh($grant['refresh_token'])); }
            catch (DomainException $error) { $message = $error->getMessage(); }
        }
        $this->seoOverrides = ['title'=>'Google reporting'];
        $this->render('admin/google-reporting', compact('connected','choices','message') + [
            'configured'=>$this->google->configured(), 'callback'=>$this->siteUrl(self::PAGE . '/callback'),
            'property'=>$grant['property'] ?? '', 'site'=>$grant['site'] ?? '', 'generation'=>$grant['generation'] ?? '', 'revision'=>$grant['revision'] ?? '',
        ]);
    }

    public function connect(): void {
        if (empty($this->secrets->googleReporting()['refresh_token'])) {
            $state = bin2hex(random_bytes(32));
            $_SESSION['google_reporting_consent'] = ['state'=>$state, 'issued'=>time(), 'owner'=>$this->owner()];
            try { $this->redirect($this->google->consentUrl($this->siteUrl(self::PAGE . '/callback'), $state)); }
            catch (DomainException $error) { $this->flash($error->getMessage(), 'error'); }
        }
        $this->redirect(self::PAGE);
    }

    public function callback(): void {
        $pending = $_SESSION['google_reporting_consent'] ?? [];
        unset($_SESSION['google_reporting_consent']);
        try {
            if (!isset($pending['state']) || !is_string($_GET['state'] ?? null) || !hash_equals($pending['state'], $_GET['state'])
                || $pending['issued'] < time()-600 || $pending['owner'] !== $this->owner()) {
                throw new DomainException('The reporting consent request expired or did not match this session. Start again.');
            }
            if (isset($_GET['error']) || !is_string($_GET['code'] ?? null) || $_GET['code'] === '') {
                throw new DomainException('Google reporting consent was declined or interrupted.');
            }
            if (!empty($this->secrets->googleReporting()['refresh_token'])) throw new DomainException('Reporting is already connected.');
            $token = $this->google->exchange($_GET['code'], $this->siteUrl(self::PAGE . '/callback'));
            $this->secrets->saveGoogleGrant($token);
            (new AuditLogService())->record('connect', 'google-reporting', 'oauth');
            $this->flash('Google authorized reporting. Choose the properties for this dashboard.', 'success');
        } catch (DomainException $error) { $this->flash($error->getMessage(), 'error'); }
        $this->redirect(self::PAGE);
    }

    private function owner(): string {
        $user = (new AuthService())->user();
        return (string)($user['id'] ?? $user['email'] ?? $user['username'] ?? '');
    }

    private function currentGrant(): array {
        $grant = $this->secrets->googleReporting();
        if (!self::matchesForm($grant, $_POST)) {
            throw new DomainException('The reporting settings changed. Reload the page before saving or disconnecting.');
        }
        return $grant;
    }

    public static function matchesForm(array $grant, array $form): bool {
        return !empty($grant['refresh_token'])
            && isset($grant['generation'], $grant['revision'])
            && is_string($form['generation'] ?? null) && hash_equals($grant['generation'], $form['generation'])
            && is_string($form['revision'] ?? null) && hash_equals($grant['revision'], $form['revision']);
    }

    public function select(): void {
        try {
            $grant = $this->currentGrant();
            $choices = $this->google->choices($this->google->refresh($grant['refresh_token']));
            $selection = [];
            foreach (['property','site'] as $key) {
                $selection[$key] = (string)($grant[$key] ?? '');
                if (!array_key_exists($key, $_POST)) continue;
                if (isset($choices[$key]['error'])) throw new DomainException($choices[$key]['error']);
                $value = $_POST[$key];
                if (!is_string($value) || ($value !== '' && !isset($choices[$key]['data'][$value]))) {
                    throw new DomainException('Choose one of the properties available to this Google account.');
                }
                $selection[$key] = $value;
            }
            $this->secrets->saveGoogleTargets($grant, $selection['property'], $selection['site']);
            (new AuditLogService())->record('save', 'google-reporting', 'targets');
            $this->flash('Dashboard reporting properties saved.', 'success');
        } catch (DomainException $error) { $this->flash($error->getMessage(), 'error'); }
        $this->redirect(self::PAGE);
    }

    public function disconnect(): void {
        try {
            $grant = $this->currentGrant();
            $this->google->revoke($grant['refresh_token']);
            $this->secrets->clearRevokedGoogleGrant($grant);
            unset($_SESSION['google_reporting_consent']);
            (new AuditLogService())->record('disconnect', 'google-reporting', 'oauth');
            $this->flash('Google reporting disconnected.', 'success');
        } catch (DomainException $error) { $this->flash($error->getMessage(), 'error'); }
        $this->redirect(self::PAGE);
    }
}
