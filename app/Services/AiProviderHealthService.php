<?php
namespace App\Services;

/**
 * Best-effort, deployment-local transport cooldown. Not business data, a quota
 * ledger, an authentication control or a guarantee that a model is healthy.
 * Only hashed connection/model identities and bounded expiry times are stored.
 */
final class AiProviderHealthService
{
    private const MAX_COOLDOWN = 86400;
    private const MAX_ENTRIES = 64;
    private const FORMAT_VERSION = 2;

    public function __construct(private ?string $path = null) {
        $this->path ??= storage_path('logs/ai-provider-health-v2.json');
    }

    /** The configured credential scopes holds without being persisted or returned. */
    private static function key(array $connection, string $model): ?string {
        $credential = $connection['apiKey'] ?? null;
        $provider = $connection['provider'] ?? null;
        $endpoint = $connection['endpoint'] ?? null;
        if (!is_string($credential) || trim($credential) === ''
            || !is_string($provider) || $provider === ''
            || !is_string($endpoint) || $endpoint === '' || $model === '') {
            return null;
        }
        $identity = json_encode([self::FORMAT_VERSION, $provider, $endpoint, $model]);
        if (!is_string($identity)) return null;
        return hash_hmac('sha256', $identity, $credential);
    }

    /** Respect observed transient failures and the provider's explicit delay. */
    public static function cooldownSeconds(int $status, int $curlError, ?int $retryAfterMs = null): int {
        return (int)ceil(self::cooldownMs($status, $curlError, $retryAfterMs) / 1000);
    }

    private static function cooldownMs(int $status, int $curlError, ?int $retryAfterMs): int {
        if ($curlError === 28) return 15000;
        if ($curlError !== 0) return 0;
        $default = match ($status) {
            429 => 30000,
            408, 500, 502, 503, 504 => 20000,
            default => 0,
        };
        if ($default === 0 || ($retryAfterMs ?? 0) <= 0) return $default;
        return min(self::MAX_COOLDOWN * 1000, $retryAfterMs);
    }

    /** Atomic replacement makes readers safe without holding a lock during HTTP. */
    private function read(int $now): array {
        if(!is_file($this->path))return [];
        $raw=@file_get_contents($this->path,false,null,0,16385);
        if(!is_string($raw)||strlen($raw)>16384)return [];
        $data=json_decode($raw,true);
        // Unscoped legacy entries cannot identify which credential observed a hold.
        if (!is_array($data) || ($data['version'] ?? null) !== self::FORMAT_VERSION
            || !is_array($data['entries'] ?? null)) return [];
        $out=[];
        foreach($data['entries'] as $key=>$record) {
            if (!is_array($record)) continue;
            $until=$record['until']??null;
            // An exact 24-hour delay may round up to the next epoch second.
            if(is_string($key)&&preg_match('/^[a-f0-9]{64}$/D',$key)&&is_int($until)
                &&$until>$now&&$until<=$now+self::MAX_COOLDOWN+1)$out[$key]=[
                    'until'=>$until,'provider_hold'=>is_array($record)&&($record['provider_hold']??false)===true];
        }
        return array_slice($out,0,self::MAX_ENTRIES,true);
    }

    /** Filter only at the start of a new generation, never switch a locked tool conversation. */
    public function availablePlan(array $plan,?int $now=null,bool $providerHoldsOnly=false): array {
        $cooldowns=$this->read($now??time());
        if($providerHoldsOnly)$cooldowns=array_filter($cooldowns,static fn(array $r):bool=>$r['provider_hold']);
        $out=[];
        foreach($plan as $connection) {
            $models = array_values(array_filter($connection['models'], static function (string $model) use ($connection, $cooldowns): bool {
                $key = self::key($connection, $model);
                return $key === null || !isset($cooldowns[$key]);
            }));
            if($models===[])continue;
            $connection['models']=$models;
            $connection['model']=$models[0];
            $out[]=$connection;
        }
        return $out;
    }

    /** No storage error may prevent an otherwise eligible provider request. */
    public function record(array $connection, string $model, int $status, int $curlError, ?int $retryAfterMs = null, ?float $now = null): void {
        $key = self::key($connection, $model);
        if ($key === null) return;
        $delayMs = self::cooldownMs($status, $curlError, $retryAfterMs);
        $success = $status === 200 && $curlError === 0;
        if ($delayMs === 0 && !$success) return;
        // Capture the observation before filesystem work and round the absolute
        // expiry up so the integer timestamp cannot shorten a provider delay.
        $now ??= microtime(true);
        if($success&&!is_file($this->path))return;
        $dir=dirname($this->path);
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return;
        $lock=@fopen($this->path.'.lock','c');
        if($lock===false)return;
        try {
            if(!flock($lock,LOCK_EX|LOCK_NB))return;
            $data = $this->read((int)$now);
            if ($success) {
                unset($data[$key]);
            } else {
                $providerHold = $curlError === 0 && ($status === 429 || ($retryAfterMs ?? 0) > 0);
                $previous = $data[$key] ?? null;
                if (($previous['provider_hold'] ?? false) && !$providerHold) return;
                $until = (int)ceil($now + $delayMs / 1000);
                if ($previous !== null && $previous['provider_hold'] === $providerHold) {
                    $until = max($until, $previous['until']);
                }
                $data[$key] = ['until' => $until, 'provider_hold' => $providerHold];
            }
            uasort($data,static fn(array $a,array $b):int=>$b['until']<=>$a['until']);
            $data=array_slice($data,0,self::MAX_ENTRIES,true);
            $json=json_encode(['version' => self::FORMAT_VERSION, 'entries' => (object)$data]);
            if(!is_string($json))return;
            $tmp=@tempnam($dir,'.ai-health-');
            if($tmp===false)return;
            try {
                if(@chmod($tmp,0600)&&@file_put_contents($tmp,$json)===strlen($json))@rename($tmp,$this->path);
            }finally{if(is_file($tmp))@unlink($tmp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
