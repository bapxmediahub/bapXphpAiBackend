<?php
namespace App\Services;

/**
 * Best-effort, deployment-local transport cooldown. Not business data, a quota
 * ledger, an authentication control or a guarantee that a model is healthy.
 * Only hashed connection/model identities and bounded expiry times are stored.
 */
final class AiProviderHealthService
{
    private const MAX_COOLDOWN = 120;
    private const MAX_ENTRIES = 64;

    public function __construct(private ?string $path = null) {
        $this->path ??= storage_path('logs/ai-provider-health.json');
    }

    private static function key(array $connection,string $model): string {
        return hash('sha256',(string)($connection['provider']??'')."\0".(string)($connection['endpoint']??'')."\0".$model);
    }

    /** Respect observed transient failures, never persist a request/schema/authentication error. */
    public static function cooldownSeconds(int $status,int $curlError,?int $retryAfterMs=null): int {
        if($curlError===28)return 15;
        if($curlError!==0)return 0;
        $seconds=match($status){429=>30,408,500,502,503,504=>20,default=>0};
        if($seconds===0)return 0;
        return min(self::MAX_COOLDOWN,max($seconds,(int)ceil(max(0,$retryAfterMs??0)/1000)));
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
            if(is_string($key)&&preg_match('/^[a-f0-9]{64}$/D',$key)&&is_int($until)
                &&$until>$now&&$until<=$now+self::MAX_COOLDOWN)$out[$key]=[
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
    public function record(array $connection,string $model,int $status,int $curlError,?int $retryAfterMs=null,?int $now=null): void {
        if(empty($connection['provider'])||empty($connection['endpoint'])||$model==='')return;
        $seconds=self::cooldownSeconds($status,$curlError,$retryAfterMs);
        $success=$status===200&&$curlError===0;
        if($seconds===0&&!$success)return;
        if($success&&!is_file($this->path))return;
        $dir=dirname($this->path);
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return;
        $lock=@fopen($this->path.'.lock','c');
        if($lock===false)return;
        try {
            if(!flock($lock,LOCK_EX|LOCK_NB))return;
            $now??=time();
            $data=$this->read($now);
            $key=self::key($connection,$model);
            if($success)unset($data[$key]);else $data[$key]=[
                'until'=>max($data[$key]['until']??0,$now+$seconds),
                'provider_hold'=>($data[$key]['provider_hold']??false)||$status===429||($retryAfterMs??0)>2000];
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
