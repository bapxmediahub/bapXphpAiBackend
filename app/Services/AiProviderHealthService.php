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

    public function __construct(private ?string $path = null) {
        $this->path ??= storage_path('logs/ai-provider-health.json');
    }

    private static function key(array $connection,string $model): string {
        return hash('sha256',(string)($connection['provider']??'')."\0".(string)($connection['endpoint']??'')."\0".$model);
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
        if(!is_array($data))return [];
        $out=[];
        foreach($data as $key=>$record) {
            // Older deployments stored integer expiries for all transient errors.
            // Such observations are not provider-mandated holds.
            $until=is_int($record)?$record:($record['until']??null);
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
            $models=array_values(array_filter($connection['models'],static fn(string $model):bool=>!isset($cooldowns[self::key($connection,$model)])));
            if($models===[])continue;
            $connection['models']=$models;
            $connection['model']=$models[0];
            $out[]=$connection;
        }
        return $out;
    }

    /** No storage error may prevent an otherwise eligible provider request. */
    public function record(array $connection, string $model, int $status, int $curlError, ?int $retryAfterMs = null, ?float $now = null): void {
        if (empty($connection['provider']) || empty($connection['endpoint']) || $model === '') return;
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
            $key = self::key($connection, $model);
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
            $json=json_encode((object)$data);
            if(!is_string($json))return;
            $tmp=@tempnam($dir,'.ai-health-');
            if($tmp===false)return;
            try {
                if(@chmod($tmp,0600)&&@file_put_contents($tmp,$json)===strlen($json))@rename($tmp,$this->path);
            }finally{if(is_file($tmp))@unlink($tmp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
