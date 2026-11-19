<?php
declare(strict_types=1);
namespace App\Integrations\GoogleSiteKit;

use DomainException;

/** Admin reporting with Google's OAuth web-server, Analytics Admin/Data and Search Console APIs. */
final class GoogleReportingClient
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/webmasters.readonly',
    ];

    public function __construct(private array $config, private ?\Closure $transport = null) {}

    public function configured(): bool {
        return trim((string)($this->config['google_client_id'] ?? '')) !== ''
            && trim((string)($this->config['google_client_secret'] ?? '')) !== '';
    }

    public function consentUrl(string $callback, string $state): string {
        if (!$this->configured()) throw new DomainException('Save the Google OAuth client ID and secret in Integrations first.');
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'=>$this->config['google_client_id'], 'redirect_uri'=>$callback, 'state'=>$state,
            'response_type'=>'code', 'scope'=>implode(' ', self::SCOPES), 'access_type'=>'offline', 'prompt'=>'consent',
        ]);
    }

    public function exchange(string $code, string $callback): string {
        $result = $this->token(['code'=>$code, 'redirect_uri'=>$callback, 'grant_type'=>'authorization_code']);
        if (array_diff(self::SCOPES, explode(' ', (string)($result['scope'] ?? '')))) {
            throw new DomainException('Google did not grant both reporting permissions. Connect again and select both permissions.');
        }
        if (empty($result['refresh_token'])) throw new DomainException('Google did not issue offline access. Reconnect with consent.');
        return $result['refresh_token'];
    }

    public function refresh(string $refreshToken): string {
        $result = $this->token(['refresh_token'=>$refreshToken, 'grant_type'=>'refresh_token']);
        if (empty($result['access_token'])) throw new DomainException('Google did not return an access token. Reconnect reporting.');
        return $result['access_token'];
    }

    private function token(array $fields): array {
        if (!$this->configured()) throw new DomainException('Save the Google OAuth client ID and secret in Integrations first.');
        return $this->request('https://oauth2.googleapis.com/token', null, $fields + [
            'client_id'=>$this->config['google_client_id'], 'client_secret'=>$this->config['google_client_secret'],
        ]);
    }

    public function revoke(string $refreshToken): void {
        $this->request('https://oauth2.googleapis.com/revoke', null, ['token'=>$refreshToken]);
    }

    /** API requests only target fixed Google endpoints, including for sc-domain sites. */
    private function request(string $url, ?string $access, ?array $fields = null): array {
        $headers = ['Accept: application/json'];
        if ($access !== null) $headers[] = 'Authorization: Bearer ' . $access;
        $body = null;
        if ($fields !== null) {
            $headers[] = $access === null ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
            $body = $access === null ? http_build_query($fields) : json_encode($fields, JSON_THROW_ON_ERROR);
        }
        if ($this->transport !== null) {
            [$status, $raw] = ($this->transport)($url, $headers, $body);
        } else {
            $curl = curl_init($url);
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>7]);
            if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            $raw = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        }
        $result = is_string($raw) ? json_decode($raw, true) : null;
        if ($url === 'https://oauth2.googleapis.com/revoke' && ($status === 200 || ($status === 400 && ($result['error'] ?? '') === 'invalid_token'))) return [];
        if ($status === 429 || ($result['error']['status'] ?? '') === 'RESOURCE_EXHAUSTED'
            || in_array($result['error']['errors'][0]['reason'] ?? '', ['rateLimitExceeded','userRateLimitExceeded','quotaExceeded'], true)) {
            throw new DomainException('Google reporting quota reached. Try again later.');
        }
        if (($result['error'] ?? '') === 'invalid_grant' || $status === 401) throw new DomainException('Google authorization expired or was revoked. Disconnect and connect again.');
        if ($status === 403) throw new DomainException('Google denied access. Check the enabled API and account permissions.');
        if ($status < 200 || $status >= 300 || !is_array($result)) throw new DomainException('Google reporting is unavailable. Check the OAuth setup and retry.');
        return $result;
    }

    /** Each service can succeed even if the other API is disabled for this project. */
    private function independent(array $queries): array {
        $results = [];
        foreach ($queries as $service => $query) {
            try { $results[$service] = ['data'=>$query()]; }
            catch (DomainException $error) { $results[$service] = ['error'=>$error->getMessage()]; }
        }
        return $results;
    }

    public function choices(string $access): array {
        return $this->independent([
            'property'=>function () use ($access) {
                $properties = $seen = [];
                $query = ['pageSize'=>200];
                do {
                    $response = $this->request('https://analyticsadmin.googleapis.com/v1beta/accountSummaries?' . http_build_query($query), $access);
                    foreach ($response['accountSummaries'] ?? [] as $account) {
                        foreach ($account['propertySummaries'] ?? [] as $item) {
                            $id = (string)($item['property'] ?? '');
                            if (preg_match('~^properties/[0-9]+$~D', $id)) $properties[$id] = (string)($item['displayName'] ?? $id) . ' (' . $id . ')';
                        }
                    }
                    $next = (string)($response['nextPageToken'] ?? '');
                    if ($next !== '' && isset($seen[$next])) throw new DomainException('Google could not finish listing properties. Retry discovery.');
                    $seen[$next] = true;
                    $query['pageToken'] = $next;
                } while ($next !== '');
                return $properties;
            },
            'site'=>function () use ($access) {
                $response = $this->request('https://www.googleapis.com/webmasters/v3/sites', $access);
                $sites = [];
                foreach ($response['siteEntry'] ?? [] as $item) {
                    if (empty($item['siteUrl']) || !in_array($item['permissionLevel'] ?? '', ['siteOwner','siteFullUser','siteRestrictedUser'], true)) continue;
                    $sites[$item['siteUrl']] = $item['siteUrl'];
                }
                return $sites;
            },
        ]);
    }

    public function reports(string $access, string $property, string $site): array {
        $reports = [];
        if ($property !== '') $reports['analytics'] = fn() => $this->analytics($access, $property);
        if ($site !== '') $reports['search'] = fn() => $this->search($access, $site);
        return $this->independent($reports);
    }

    private function analytics(string $access, string $property): array {
        if (!preg_match('~^properties/[0-9]+$~D', $property)) throw new DomainException('Select a GA4 property in reporting settings.');
        $requests = [];
        foreach ([['','sessions'], ['pagePath','screenPageViews'], ['sessionSourceMedium','sessions']] as [$dimension, $metric]) {
            $request = ['dateRanges'=>[['startDate'=>'28daysAgo','endDate'=>'yesterday']], 'metrics'=>[['name'=>$metric]]];
            if ($dimension !== '') {
                $request += ['dimensions'=>[['name'=>$dimension]], 'limit'=>'5', 'orderBys'=>[['metric'=>['metricName'=>$metric],'desc'=>true]]];
            }
            $requests[] = $request;
        }
        $data = $this->request('https://analyticsdata.googleapis.com/v1beta/' . $property . ':batchRunReports', $access, ['requests'=>$requests]);
        $reports = $data['reports'] ?? [];
        if (count($reports) !== 3) throw new DomainException('Google returned an incomplete Analytics report. Retry later.');
        $data = [];
        foreach (['sessions', 'pages', 'sources'] as $i=>$key) {
            $values = [];
            if (empty($reports[$i]['metricHeaders'])) throw new DomainException('Google returned an incomplete Analytics report. Retry later.');
            foreach ($reports[$i]['rows'] ?? [] as $row) {
                $value = $row['metricValues'][0]['value'] ?? null;
                if (!is_numeric($value)) throw new DomainException('Google returned an incomplete Analytics report. Retry later.');
                $values[] = ['label'=>(string)($row['dimensionValues'][0]['value'] ?? ''), 'value'=>(int)$value];
            }
            $data[$key] = $values;
        }
        return $data;
    }

    private function search(string $access, string $site): array {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Los_Angeles'));
        $range = ['startDate'=>$today->modify('-28 days')->format('Y-m-d'), 'endDate'=>$today->modify('-1 day')->format('Y-m-d')];
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query';
        // Query-level rows omit anonymized queries. Google's dimensionless aggregate supplies the totals.
        $total = $this->request($url, $access, $range + ['rowLimit'=>1]);
        $top = $this->request($url, $access, $range + ['dimensions'=>['query'], 'rowLimit'=>5]);
        $summary = $total['rows'][0] ?? null;
        if ($summary !== null && (!is_numeric($summary['clicks'] ?? null) || !is_numeric($summary['impressions'] ?? null))) {
            throw new DomainException('Google returned an incomplete Search Console report. Retry later.');
        }
        $queries = [];
        foreach ($top['rows'] ?? [] as $row) {
            if (!isset($row['keys'][0]) || !is_numeric($row['clicks'] ?? null)) throw new DomainException('Google returned an incomplete Search Console report. Retry later.');
            $queries[] = ['label'=>(string)$row['keys'][0], 'value'=>(int)$row['clicks']];
        }
        return ['summary'=>$summary, 'queries'=>$queries];
    }
}
