<?php
declare(strict_types=1);

namespace App\Integrations\GoogleSiteKit;

use App\Services\PublicSiteUrlService;
use DomainException;

/**
 * Native, owner-facing PageSpeed Insights projection.
 *
 * Independently implemented from the documented PageSpeed v5 API contract;
 * no WordPress, Site Kit plugin runtime, Google OAuth scopes, or extra PHP
 * bootstrap is required. Results contain only allowlisted aggregate metrics.
 */
final class PageSpeedInsightsClient
{
    private const API = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private const MAX_RESPONSE_BYTES = 3 * 1024 * 1024;

    public function __construct(private ?\Closure $transport = null, private string $apiKey = '')
    {
        if ($apiKey !== '' && !self::validKey($apiKey))
            throw new DomainException('Configure a valid dedicated PageSpeed API key.');
    }

    public static function validKey(string $key): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key) === 1;
    }

    public function analyze(string $siteUrl, string $strategy): array
    {
        if (!in_array($strategy, ['mobile', 'desktop'], true)) {
            throw new DomainException('Choose mobile or desktop performance analysis.');
        }
        try {
            $origin = PublicSiteUrlService::origin($siteUrl);
        } catch (\RuntimeException) {
            throw new DomainException('Configure the public HTTPS site URL before analyzing performance.');
        }
        $parts = parse_url($origin);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || !str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || $host === 'localhost' || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
            || isset($parts['port'])) {
            throw new DomainException('PageSpeed requires a configured public HTTPS website.');
        }
        $url = self::API.'?'.http_build_query([
            'url'=>$origin.'/', 'strategy'=>$strategy, 'category'=>'performance',
        ], '', '&', PHP_QUERY_RFC3986);
        $headers=['Accept: application/json'];
        // Dedicated Google Cloud key is sent only to the fixed Google endpoint.
        // Never include it in the URL, result, exception, browser or report cache.
        if ($this->apiKey !== '') $headers[]='X-Goog-Api-Key: '.$this->apiKey;
        if ($this->transport !== null) {
            [$status,$body] = ($this->transport)($url,$headers);
        } else {
            [$status,$body] = $this->fetch($url,$headers);
        }
        if ($status === 429) throw new DomainException('PageSpeed quota reached. Try again later.',429);
        if (in_array($status,[401,403],true)) throw new DomainException('Google denied PageSpeed access or its API quota is unavailable.',$status);
        if ($status < 200 || $status >= 300 || !is_string($body)) {
            throw new DomainException('PageSpeed could not analyze the site. Try again later.');
        }
        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new DomainException('Google returned a report larger than the accepted limit.');
        }
        $json = json_decode($body, true);
        if (!is_array($json) || !is_array($json['lighthouseResult'] ?? null)) {
            throw new DomainException('Google returned an incomplete PageSpeed report.');
        }

        $result = $json['lighthouseResult'];
        $score = $result['categories']['performance']['score'] ?? null;
        $score = is_int($score) || is_float($score) ? $score : null;
        if ($score !== null && (!is_finite((float)$score) || $score < 0 || $score > 1)) $score = null;
        $audits = is_array($result['audits'] ?? null) ? $result['audits'] : [];
        $lab = [];
        foreach ([
            'first-contentful-paint'=>'First contentful paint',
            'largest-contentful-paint'=>'Largest contentful paint',
            'speed-index'=>'Speed index',
            'total-blocking-time'=>'Total blocking time',
            'cumulative-layout-shift'=>'Cumulative layout shift',
        ] as $key=>$label) {
            $entry = $audits[$key] ?? [];
            $text = is_string($entry['displayValue'] ?? null)
                ? mb_substr(trim($entry['displayValue']), 0, 80) : null;
            $lab[] = ['name'=>$label,'display'=>$text === '' ? null : $text];
        }

        $field = [];
        $metrics = $json['loadingExperience']['metrics'] ?? [];
        foreach ([
            'LARGEST_CONTENTFUL_PAINT_MS'=>'Largest contentful paint',
            'CUMULATIVE_LAYOUT_SHIFT_SCORE'=>'Cumulative layout shift',
            'INTERACTION_TO_NEXT_PAINT'=>'Interaction to next paint',
        ] as $key=>$label) {
            $v = is_array($metrics[$key] ?? null) ? $metrics[$key] : [];
            $percentile = $v['percentile'] ?? null;
            $field[] = [
                'name'=>$label,
                'percentile'=>(is_numeric($percentile) && (float)$percentile >= 0 && (float)$percentile < 1e9)
                    ? (float)$percentile : null,
                'display'=>is_numeric($percentile) && (float)$percentile >= 0 && (float)$percentile < 1e9
                    ? ($key === 'CUMULATIVE_LAYOUT_SHIFT_SCORE'
                        ? number_format((float)$percentile / 100, 2, '.', '')
                        : number_format((float)$percentile, 0, '.', ',').' ms') : null,
                'category'=>in_array($v['category'] ?? null, ['FAST','AVERAGE','SLOW'], true) ? $v['category'] : null,
            ];
        }

        $opportunities = [];
        foreach ($audits as $audit) {
            if (!is_array($audit) || ($audit['details']['type'] ?? '') !== 'opportunity') continue;
            $saving = $audit['details']['overallSavingsMs'] ?? null;
            if (!is_int($saving) && !is_float($saving)) continue;
            if (!is_finite((float)$saving) || $saving <= 0) continue;
            $title = $audit['title'] ?? null;
            if (!is_string($title) || trim($title) === '') continue;
            $opportunities[] = ['title'=>mb_substr(trim($title),0,120),'savings_ms'=>(int)min(120000,round($saving))];
        }
        usort($opportunities, static fn(array $a,array $b):int => $b['savings_ms'] <=> $a['savings_ms']);
        return [
            'strategy'=>$strategy,
            'target'=>$origin.'/',
            'status'=>$score === null ? 'partial' : 'complete',
            'performance_score'=>$score === null ? null : (int)round($score*100),
            'lab'=>$lab,
            'field'=>$field,
            'opportunities'=>array_slice($opportunities,0,4),
            'field_status'=>empty($json['loadingExperience']['metrics']) ? 'unavailable' : 'reported',
        ];
    }

    /** A fixed Google endpoint is the only network destination, with no redirects. */
    private function fetch(string $url,array $headers): array
    {
        if (!function_exists('curl_init')) throw new DomainException('PageSpeed transport is unavailable.');
        $curl = curl_init($url);
        if ($curl === false) throw new DomainException('PageSpeed transport is unavailable.');
        $body = '';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_CONNECTTIMEOUT=>3,
            CURLOPT_TIMEOUT=>12,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_WRITEFUNCTION=>static function ($curl,string $chunk) use (&$body): int {
                if (strlen($body)+strlen($chunk) > self::MAX_RESPONSE_BYTES) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($ok === false) throw new DomainException('PageSpeed request timed out or could not complete.');
        return [$status,$body];
    }
}
