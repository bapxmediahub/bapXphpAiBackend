<?php
declare(strict_types=1);
namespace App\Services;

/** Canonical public links use deployment configuration, never a request Host header. */
final class PublicSiteUrlService
{
    public static function origin(?string $publicUrl = null): string
    {
        $url = parse_url($publicUrl ?? (string)getenv('APP_URL'));
        if (!is_array($url) || ($url['scheme']??'')!=='https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || !in_array($url['path']??'', ['', '/'], true)) {
            throw new \RuntimeException('The public HTTPS site origin is unavailable.');
        }
        return 'https://'.strtolower($url['host'])
            .(isset($url['port']) && $url['port']!==443 ? ':'.$url['port'] : '');
    }
}
