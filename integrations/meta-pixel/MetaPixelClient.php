<?php
namespace App\Integrations\MetaPixel;
final class MetaPixelClient {
    public function __construct(private string $pixelId){}
    public function pixelId(): string {
        return $this->pixelId;
    }
    public function enabled(): bool {
        return $this->pixelId !== '';
    }
    public function baseCode(): string {
        if (!$this->enabled()) return '';
        $id = $this->pixelId;
        return <<<HTML
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$id}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
HTML;
    }
    public function trackEvent(string $event, array $data = []): string {
        if (!$this->enabled()) return '';
        $params = $data ? ', ' . json_encode($data) : '';
        return "<script>fbq('track', '" . $event . "'" . $params . ");</script>";
    }
}
