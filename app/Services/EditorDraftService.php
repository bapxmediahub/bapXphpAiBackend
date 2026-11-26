<?php
declare(strict_types=1);
namespace App\Services;

/** Private editor recovery, separate from publication and the canonical article/page. */
final class EditorDraftService
{
    public const HISTORY_LIMIT = 3;
    private const BLOG_FIELDS = ['slug','title','category','type','template','og_image','image_alt','source_url',
        'summary','order','excerpt','keywords','published_at','author','content'];
    private const PAGE_FIELDS = ['slug','title','content','header_image_url','header_image_alt',
        'campaign_enabled','campaign_payment_enabled','campaign_booking_amount','download_enabled'];

    public static function owner(array $user): string
    {
        if (($user['role'] ?? '') !== 'admin' && empty($user['is_admin'])) throw new \DomainException('Owner access required.');
        $id = $user['sub'] ?? null;
        if (!is_string($id) || $id === '' || strlen($id) > 255) throw new \DomainException('Missing editor identity.');
        return hash('sha256', $id);
    }

    /** The hosted settings primary key is VARCHAR(36). Keep the original
     * truncated prefix so existing narrow-schema recovery rows stay readable. */
    public static function identity(string $owner,string $kind,string $target): string
    {
        return substr(self::legacyIdentity($owner,$kind,$target),0,36);
    }

    /** Previous full-width key, retained only to resolve existing recovery rows. */
    public static function legacyIdentity(string $owner,string $kind,string $target): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$owner) || !in_array($kind,['blog','page'],true)
            || ($target !== '' && ($kind === 'page' ? !AuthoredPageService::validSlug($target)
                : !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,159}$/D',$target)))) {
            throw new \InvalidArgumentException('Invalid editor identity.');
        }
        return 'ed:'.substr(hash('sha256', $owner."\0".$kind."\0".$target),0,60);
    }

    public static function revision(string $kind,string $target,string $revision): void
    {
        $valid = $kind === 'page' ? AuthoredPageService::validRevision($revision)
            : preg_match('/^[a-f0-9]{64}$/D',$revision) === 1;
        if ($target === '' ? $revision !== ($kind === 'page' ? '0' : 'new') : !$valid) {
            throw new \InvalidArgumentException('Invalid editor base revision.');
        }
    }

    public static function values(string $kind,array $values): array
    {
        $fields = $kind === 'blog' ? self::BLOG_FIELDS : self::PAGE_FIELDS;
        $out = [];
        if (count($values) > 64) throw new \InvalidArgumentException('Too many draft fields.');
        foreach ($values as $name=>$value) {
            if (!is_string($name) || !is_string($value) || !mb_check_encoding($value,'UTF-8') || str_contains($value,"\0")) {
                throw new \InvalidArgumentException('Invalid draft field.');
            }
            $limit = $name === 'content' ? ($kind === 'blog' ? 1048576 : 65536) : 16000;
            if (!in_array($name,$fields,true)) {
                if ($kind !== 'page') throw new \InvalidArgumentException('Unsupported draft field.');
                if (preg_match('/^campaign_fields\[(detail_[123])\]\[(type|label|required)\]$/D',$name,$match)) {
                    $limit = 288;
                    if ($match[2] === 'type' && $value !== '' && !isset(CampaignLeadService::OPTIONAL_TYPES[$value])) throw new \InvalidArgumentException('Invalid enquiry type.');
                    if ($match[2] === 'required' && !in_array($value,['','1'],true)) throw new \InvalidArgumentException('Invalid checkbox.');
                } elseif (preg_match('/^download_existing\[[a-f0-9]{32}\]$/D',$name)) {
                    $limit = 2;
                    if ($value !== '' && (!preg_match('/^[1-9][0-9]?$/D',$value) || (int)$value > CampaignDownloadService::MAX_FILES)) throw new \InvalidArgumentException('Invalid download position.');
                } else throw new \InvalidArgumentException('Unsupported draft field.');
            }
            if (in_array($name,['campaign_enabled','campaign_payment_enabled','download_enabled'],true) && !in_array($value,['','1'],true)) throw new \InvalidArgumentException('Invalid checkbox.');
            if ($kind === 'page') $limit = match($name) {'slug'=>128,'title'=>640,'header_image_url'=>512,'header_image_alt'=>1020,'campaign_booking_amount'=>32,default=>$limit};
            if (strlen($value) > $limit) throw new \InvalidArgumentException('Draft exceeds its field limit.');
            $out[$name] = $value;
        }
        foreach (['slug','title','content'] as $required) if (!array_key_exists($required,$out)) throw new \InvalidArgumentException('Incomplete draft.');
        if (strlen(json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) > 1250000) throw new \InvalidArgumentException('Draft exceeds its storage limit.');
        return $out;
    }

    public static function assertRecord(array $row,string $owner,string $kind,string $target): array
    {
        $record = $row['editor_recovery'] ?? null;
        if (!in_array($row['id'] ?? null,[self::identity($owner,$kind,$target),self::legacyIdentity($owner,$kind,$target)],true) || !is_array($record)
            || ($record['owner'] ?? null) !== $owner || ($record['kind'] ?? null) !== $kind || ($record['target'] ?? null) !== $target
            || !is_string($record['token'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D',$record['token'])
            || !is_array($record['versions'] ?? null) || !array_is_list($record['versions'])
            || count($record['versions']) < 1 || count($record['versions']) > self::HISTORY_LIMIT) throw new \RuntimeException('Invalid editor recovery record.');
        foreach ($record['versions'] as $version) {
            if (!is_array($version) || !is_string($version['revision'] ?? null) || !is_int($version['at'] ?? null)
                || !is_array($version['values'] ?? null)) throw new \RuntimeException('Invalid editor recovery version.');
            self::revision($kind,$target,$version['revision']);
            self::values($kind,$version['values']);
        }
        return $record;
    }

    /**
     * Only a confirmed InnoDB deadlock rollback is safe to retry here.
     * The caller must own the entire single-statement/autocommit operation,
     * including a fresh read of the original expected recovery token.
     * Never use this for transport errors, payments or external effects.
     */
    public static function retryDeadlock(callable $attempt): array
    {
        for ($i=0; ; $i++) {
            try { return $attempt(); }
            catch (\PDOException $error) {
                if ($i>=2 || ($error->errorInfo[0] ?? null)!=='40001'
                    || (int)($error->errorInfo[1] ?? 0)!==1213) throw $error;
                // Two bounded retries, with short jitter; no busy wait.
                usleep(random_int(10000,25000)*($i+1));
            }
        }
    }

    /** Bounded append; identical snapshots preserve their existing token. */
    public static function next(string $owner,string $kind,string $target,string $revision,array $values,?array $previous,int $now): array
    {
        self::revision($kind,$target,$revision);
        $values = self::values($kind,$values);
        if (($previous['versions'][0]['revision'] ?? null) === $revision
            && ($previous['versions'][0]['values'] ?? null) == $values) return $previous;
        $versions = array_slice([['revision'=>$revision,'at'=>$now,'values'=>$values],...($previous['versions'] ?? [])],0,self::HISTORY_LIMIT);
        // Match actual database JSON encoding, including escaped Unicode.
        // Trim earlier versions, never truncate the latest recovery content.
        $record = ['owner'=>$owner,'kind'=>$kind,'target'=>$target,'token'=>bin2hex(random_bytes(16)),'versions'=>$versions];
        while (strlen(json_encode($record,JSON_THROW_ON_ERROR))>2500000) {
            if (count($record['versions'])<=1) throw new \InvalidArgumentException('Draft exceeds its encoded storage limit.');
            array_pop($record['versions']);
        }
        return $record;
    }
}
