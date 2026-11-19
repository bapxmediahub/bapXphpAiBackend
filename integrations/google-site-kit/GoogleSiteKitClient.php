<?php
declare(strict_types=1);
namespace App\Integrations\GoogleSiteKit;

/** Existing Google tag projection only: no reporting OAuth, network check or settings mutation. */
final class GoogleSiteKitClient
{
    public function __construct(private string $analyticsId,private string $adsId,private string $siteVerification) {}

    public function enabled(): bool {
        return $this->analytics()!==null||$this->ads()!==null||$this->verification()!==null;
    }
    /** Safe bounded configuration metadata, not proof of collection or Google ownership. */
    public function status(): array {
        return [
            'analytics'=>['configuration'=>self::state($this->analyticsId,$this->analytics()),'collection'=>'unverified'],
            'ads'=>['configuration'=>self::state($this->adsId,$this->ads()),'conversion_tracking'=>'unverified'],
            'search_console'=>['configuration'=>self::state($this->siteVerification,$this->verification()),'ownership'=>'unverified'],
            'reporting'=>'not_checked',
        ];
    }
    public function siteVerificationTag(): string {
        $token=$this->verification();if($token===null)return '';
        return '<meta name="google-site-verification" content="'.htmlspecialchars($token,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" />';
    }
    public function gtagCode(): string {
        $ids=array_values(array_unique(array_filter([$this->analytics(),$this->ads()],static fn($id):bool=>$id!==null)));
        if($ids===[])return '';
        $source=htmlspecialchars('https://www.googletagmanager.com/gtag/js?id='.rawurlencode($ids[0]),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $configs=[];
        foreach($ids as $id)$configs[]="gtag('config', ".json_encode($id,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).");";
        // Preserve the site's existing config-based installation; Google continues to support it.
        // Current docs: https://developers.google.com/tag-platform/gtagjs/configure
        return "<!-- Google tag: validated configured destinations, collection unverified -->\n"
            .'<script async data-bapx-google-tag src="'.$source.'"></script>'."\n<script>\n"
            ."window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\n"
            ."gtag('js', new Date());\n".implode("\n",$configs)."\n</script>\n";
    }
    private static function state(string $raw,?string $valid): string {
        return trim($raw)===''?'missing':($valid===null?'invalid':'configured');
    }
    private function analytics(): ?string {
        $id=trim($this->analyticsId);return preg_match('/^(?:G|GT)-[A-Z0-9]{1,64}$/D',$id)?$id:null;
    }
    private function ads(): ?string {
        $id=trim($this->adsId);return preg_match('/^AW-[0-9]{1,20}$/D',$id)?$id:null;
    }
    private function verification(): ?string {
        $token=trim($this->siteVerification);return preg_match('/^[A-Za-z0-9_-]{1,512}$/D',$token)?$token:null;
    }
}
