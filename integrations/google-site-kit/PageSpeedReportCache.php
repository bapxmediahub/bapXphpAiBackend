<?php
declare(strict_types=1);
namespace App\Integrations\GoogleSiteKit;

/** Disposable deployment-local owner report cache and atomic quota admission.
 * Only the client's bounded projection is stored, never keys or raw responses. */
final class PageSpeedReportCache
{
    public const COOLDOWN = 600;
    public const RETENTION = 604800;
    private const MAX_BYTES = 65536;
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('logs/pagespeed-report.json');
    }

    public function read(string $origin, ?int $now = null): ?array
    {
        $now ??= time();
        if (is_link($this->path)) throw new \RuntimeException('PageSpeed cache is unavailable.');
        if (!is_file($this->path)) return null;
        $raw = @file_get_contents($this->path, false, null, 0, self::MAX_BYTES + 1);
        if (!is_string($raw) || strlen($raw) > self::MAX_BYTES)
            throw new \RuntimeException('PageSpeed cache is unavailable.');
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['version'] ?? null) !== 1
            || !is_string($data['origin'] ?? null)
            || !is_int($data['at'] ?? null) || $data['at'] > $now
            || !is_string($data['attempt'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D',$data['attempt'])
            || !is_bool($data['running'] ?? null)
            || !is_array($data['results'] ?? null) || !array_is_list($data['results']) || count($data['results']) > 2
            || !is_array($data['last_good'] ?? null))
            throw new \RuntimeException('PageSpeed cache is unavailable.');
        if ($data['origin'] !== $origin || $data['at'] <= $now-self::RETENTION) return null;
        foreach ($data['last_good'] as $strategy => $entry) {
            if (!in_array($strategy,['mobile','desktop'],true) || !is_array($entry)
                || !is_int($entry['at'] ?? null) || $entry['at'] > $now
                || !is_array($entry['report'] ?? null)
                || ($entry['report']['strategy'] ?? null) !== $strategy
                || ($entry['report']['status'] ?? null) !== 'complete'
                || ($entry['report']['target'] ?? null) !== $origin.'/')
                throw new \RuntimeException('PageSpeed cache is unavailable.');
            if ($entry['at'] <= $now-self::RETENTION) unset($data['last_good'][$strategy]);
        }
        return $data;
    }

    /** Claim before external work. A crashed attempt retains its cooldown.
     * No lock is held while the provider runs, and old claims cannot commit
     * over a later attempt or another configured website. */
    public function run(string $origin, PageSpeedInsightsClient $client, ?int $now = null): bool
    {
        $testClock = $now;
        $now ??= time();
        $claim = $this->locked(function () use ($origin,$now): ?array {
            $old = $this->read($origin,$now);
            if ($old !== null && $old['at'] > $now-self::COOLDOWN) return null;
            $record = ['version'=>1,'origin'=>$origin,'at'=>$now,
                'attempt'=>bin2hex(random_bytes(16)),'running'=>true,
                'results'=>[],'last_good'=>$old['last_good'] ?? []];
            $this->write($record);
            return $record;
        });
        if ($claim === null) return false;

        $blocked = false;
        foreach (['mobile','desktop'] as $strategy) {
            if ($blocked) {
                $result=['strategy'=>$strategy,'status'=>'not_attempted','performance_score'=>null,
                    'message'=>'Not requested because Google rejected the preceding strategy.'];
            } else {
                try {
                    $result=$client->analyze($origin,$strategy);
                } catch (\DomainException $error) {
                    $blocked = in_array($error->getCode(),[401,403,429],true);
                    $message=match($error->getCode()) {
                        429 => 'PageSpeed quota reached. Try again later.',
                        401,403 => 'Google denied PageSpeed access or its API quota is unavailable.',
                        default => 'PageSpeed could not complete this report. Try again later.',
                    };
                    $result=['strategy'=>$strategy,'status'=>'unavailable','performance_score'=>null,'message'=>$message];
                } catch (\Throwable) {
                    $result=['strategy'=>$strategy,'status'=>'unavailable','performance_score'=>null,
                        'message'=>'PageSpeed could not complete this report. Try again later.'];
                }
            }
            $claim['results'][]=$result;
            if (($result['status'] ?? null)==='complete')
                $claim['last_good'][$strategy]=['at'=>$now,'report'=>$result];
        }
        $claim['running']=false;
        $this->locked(function () use ($origin,$testClock,$claim): void {
            // Read with real time in production (test clocks may be supplied).
            $current=$this->read($origin,$testClock??time());
            if (($current['attempt'] ?? null) !== $claim['attempt'])
                throw new \RuntimeException('PageSpeed attempt was superseded.');
            $this->write($claim);
        });
        return true;
    }

    private function locked(callable $operation): mixed
    {
        $dir=dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir))
            throw new \RuntimeException('PageSpeed cache is unavailable.');
        if (is_link($this->path.'.lock')) throw new \RuntimeException('PageSpeed cache is unavailable.');
        $lock=@fopen($this->path.'.lock','c');
        if ($lock===false) throw new \RuntimeException('PageSpeed cache lock is unavailable.');
        try {
            if (!flock($lock,LOCK_EX)) throw new \RuntimeException('PageSpeed cache lock failed.');
            return $operation();
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }

    private function write(array $record): void
    {
        $encoded=json_encode($record,JSON_THROW_ON_ERROR);
        if (strlen($encoded)>self::MAX_BYTES) throw new \RuntimeException('PageSpeed cache exceeded its bound.');
        $temp=@tempnam(dirname($this->path),'.pagespeed-');
        if ($temp===false) throw new \RuntimeException('PageSpeed cache is unavailable.');
        try {
            if (!@chmod($temp,0600) || @file_put_contents($temp,$encoded)!==strlen($encoded)
                || !@rename($temp,$this->path))
                throw new \RuntimeException('PageSpeed cache write failed.');
        } finally { if (is_file($temp)) @unlink($temp); }
    }
}
