<?php
namespace App\Integrations\GoogleSiteKit;
final class GoogleSiteKitClient {
    public function __construct(
        private string $analyticsId,
        private string $adsId,
        private string $siteVerification
    ){}

    public function enabled(): bool {
        return $this->analyticsId !== '' || $this->adsId !== '' || $this->siteVerification !== '';
    }
    public function siteVerificationTag(): string {
        if ($this->siteVerification === '') return '';
        return '<meta name="google-site-verification" content="' . $this->siteVerification . '" />';
    }
    public function gtagCode(): string {
        $ids = array_filter([$this->analyticsId, $this->adsId]);
        if (!$ids) return '';
        $configs = '';
        $src = '';
        foreach ($ids as $id) {
            $configs .= "\ngtag('config', '{$id}');";
            if (!$src) {
                $src = $id;
            }
        }
        return <<<HTML
<!-- Google Site Kit (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$src}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());{$configs}
</script>
<!-- End Google Site Kit -->
HTML;
    }
}
