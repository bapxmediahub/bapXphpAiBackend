<?php
namespace App\Services;

final class CampaignDownloadService
{
    public const MAX_BYTES = 5_000_000;
    public const MAX_FILES = 20;
    public const GRANT_SECONDS = 3600;

    private const TYPES = [
        'pdf'=>['application/pdf'],
        'zip'=>['application/zip', 'application/x-zip-compressed'],
        'txt'=>['text/plain'],
        'png'=>['image/png'],
        'jpg'=>['image/jpeg'],
        'jpeg'=>['image/jpeg'],
        'webp'=>['image/webp'],
    ];

    public function __construct(private ?string $directory = null) {}

    public static function definition(mixed $manifest): array
    {
        if (!is_array($manifest) || array_diff(array_keys($manifest), ['enabled','files'])
            || !is_bool($manifest['enabled'] ?? null) || !is_array($manifest['files'] ?? null)
            || !array_is_list($manifest['files']) || count($manifest['files']) > self::MAX_FILES) {
            throw new \InvalidArgumentException('Choose up to '.self::MAX_FILES.' download files.');
        }
        $files = [];
        $bytes = 0;
        foreach ($manifest['files'] as $file) {
            if (!self::validFile($file) || isset($files[$file['id']])) {
                throw new \InvalidArgumentException('Review the selected download files.');
            }
            $files[$file['id']] = $file;
            $bytes += $file['bytes'];
        }
        if ($bytes > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Download files must total 5,000,000 bytes (5 MB) or less.');
        }
        return ['enabled'=>$manifest['enabled'], 'files'=>array_values($files)];
    }

    public static function manifest(array $page): ?array
    {
        if (!isset($page['campaign_downloads'])) return null;
        try {
            return self::definition($page['campaign_downloads']);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function validFile(mixed $file): bool
    {
        if (!is_array($file) || array_diff(array_keys($file), ['id','name','mime','bytes'])
            || !is_string($file['id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $file['id'])
            || !is_int($file['bytes'] ?? null) || $file['bytes'] < 1 || $file['bytes'] > self::MAX_BYTES
            || !is_string($file['name'] ?? null) || !is_string($file['mime'] ?? null)) {
            return false;
        }
        $name = $file['name'];
        if ($name === '' || strlen($name) > 480 || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 120
            || str_contains($name, '/') || str_contains($name, '\\') || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            return false;
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return in_array($file['mime'], self::TYPES[$extension] ?? [], true);
    }

    /** Existing file descriptors come only from the revision the owner opened. */
    public function prepare(?array $previous, mixed $enabled, mixed $positions, array $uploads): array
    {
        $stored = self::manifest($previous ?? []);
        if ($enabled !== null && $enabled !== '1') throw new \InvalidArgumentException('Review the download setting.');
        if (!is_array($positions) || count($positions) > self::MAX_FILES) {
            throw new \InvalidArgumentException('Review the download file order.');
        }
        $known = array_column($stored['files'] ?? [], null, 'id');
        $ordered = [];
        foreach ($positions as $id=>$position) {
            if (!isset($known[$id]) || !is_string($position)
                || ($position !== '' && (!preg_match('/^[1-9][0-9]?$/D', $position) || (int)$position > self::MAX_FILES))) {
                throw new \InvalidArgumentException('Review the download file order.');
            }
            if ($position === '') continue;
            if (isset($ordered[$position])) throw new \InvalidArgumentException('Give each retained download a different position.');
            $ordered[$position] = $known[$id];
        }
        ksort($ordered, SORT_NUMERIC);
        $staged = $this->inspectUploads($uploads);
        if ($staged && $enabled === null) throw new \InvalidArgumentException('Enable downloads before adding files.');
        $files = array_merge(array_values($ordered), array_column($staged, 'file'));
        $manifest = self::definition(['enabled'=>$enabled === '1', 'files'=>$files]);
        return ['manifest'=>$manifest['enabled'] || $files ? $manifest : null, 'uploads'=>$staged];
    }

    private function inspectUploads(array $uploads): array
    {
        $errors = $uploads['error'] ?? [];
        if (!is_array($errors) || count($errors) > self::MAX_FILES) {
            throw new \InvalidArgumentException('Choose up to '.self::MAX_FILES.' download files.');
        }
        $staged = [];
        foreach ($errors as $index=>$error) {
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                throw new \InvalidArgumentException('An upload exceeds the hosting limit. Select a smaller file and try again.');
            }
            $tmp = $uploads['tmp_name'][$index] ?? null;
            $name = $uploads['name'][$index] ?? null;
            if ($error !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp) || !is_string($name)) {
                throw new \InvalidArgumentException('An upload did not finish. Choose the files again and retry.');
            }
            $bytes = filesize($tmp);
            if ($bytes > self::MAX_BYTES) {
                throw new \InvalidArgumentException('Download files must total 5,000,000 bytes (5 MB) or less.');
            }
            $file = ['id'=>bin2hex(random_bytes(16)), 'name'=>trim($name), 'mime'=>mime_content_type($tmp), 'bytes'=>$bytes];
            if (!self::validFile($file)) {
                throw new \InvalidArgumentException('Choose a valid PDF, ZIP, TXT, PNG, JPG or WebP file.');
            }
            $staged[] = compact('file','tmp');
        }
        return $staged;
    }

    /** The page can only reference files after every new upload has been stored. */
    public function store(string $slug, array $uploads): array
    {
        if (!$uploads) return [];
        $directory = $this->folder($slug, true);
        if ($directory === null) throw new \InvalidArgumentException('Private download storage is unavailable.');
        $stored = [];
        try {
            foreach ($uploads as $upload) {
                $file = $upload['file'];
                $target = $directory.'/'.$file['id'];
                if (file_exists($target) || !move_uploaded_file($upload['tmp'], $target)) {
                    throw new \InvalidArgumentException('A download could not be stored. Choose the files again and retry.');
                }
                $stored[] = $file;
                if (!chmod($target, 0600)) throw new \InvalidArgumentException('Private download storage is unavailable.');
            }
        } catch (\Throwable $error) {
            $this->discard($slug, $stored);
            throw $error;
        }
        return $stored;
    }

    /** Discard new files only after a definitive rejection of their page write. */
    public function discard(string $slug, array $files): void
    {
        if (!$files) return;
        $directory = $this->folder($slug);
        if ($directory === null) return;
        foreach ($files as $file) {
            if (self::validFile($file)) @unlink($directory.'/'.$file['id']);
        }
    }

    private function folder(string $slug, bool $create = false): ?string
    {
        if (!AuthoredPageService::validSlug($slug)) return null;
        $root = $this->directory ?? dirname(app_path()).'/.sps-downloads';
        $parent = realpath(dirname($root));
        if ($parent === false) return null;
        $root = $parent.'/'.basename($root);
        $public = realpath($_SERVER['DOCUMENT_ROOT'] ?? app_path());
        if ($public === false) $public = realpath(app_path());
        $checkout = realpath(app_path());
        foreach (array_filter([$public,$checkout]) as $webRoot) {
            if ($root === $webRoot || str_starts_with($root, rtrim($webRoot, '/').'/')) return null;
        }
        if (is_link($root) || ($create && !is_dir($root) && !@mkdir($root, 0700) && !is_dir($root))) return null;
        if (!is_dir($root)) return null;
        $folder = $root.'/'.$slug;
        if (is_link($folder) || ($create && !is_dir($folder) && !@mkdir($folder, 0700) && !is_dir($folder))) return null;
        return is_dir($folder) ? $folder : null;
    }

    public static function canAccess(array $page, array $session, ?int $now = null): bool
    {
        $active = self::manifest($page);
        $slug = $page['slug'] ?? null;
        if (!is_string($slug) || !AuthoredPageService::validSlug($slug)
            || ($page['status'] ?? null) !== 'published' || !AuthoredPageService::validRevision($page['revision'] ?? null)
            || !($active['enabled'] ?? false) || !$active['files']) return false;
        if (!array_key_exists('campaign_form', $page)) return true;
        $grant = $session['campaign_download_grants'][$slug] ?? null;
        return is_array($grant) && ($grant['revision'] ?? null) === $page['revision']
            && is_int($grant['expires'] ?? null) && $grant['expires'] > ($now ?? time());
    }

    /** Called only after a validated enquiry receipt is confirmed. */
    public static function grant(array &$session, array $page, string $revision, ?int $now = null): void
    {
        $manifest = self::manifest($page);
        $slug = $page['slug'] ?? null;
        if (!is_string($slug) || !AuthoredPageService::validSlug($slug) || ($page['status'] ?? null) !== 'published'
            || ($page['revision'] ?? null) !== $revision || !AuthoredPageService::validRevision($revision)
            || !array_key_exists('campaign_form', $page) || !($manifest['enabled'] ?? false) || !$manifest['files']) return;
        $now ??= time();
        $grants = is_array($session['campaign_download_grants'] ?? null) ? $session['campaign_download_grants'] : [];
        $grants = array_filter($grants, static fn($grant): bool => is_array($grant) && ($grant['expires'] ?? 0) > $now);
        if (($grants[$slug]['revision'] ?? null) === $revision) return;
        unset($grants[$slug]);
        $grants[$slug] = ['revision'=>$revision, 'expires'=>$now + self::GRANT_SECONDS];
        $session['campaign_download_grants'] = array_slice($grants, -16, preserve_keys:true);
    }

    /** Caller must confirm entitlement to the exact descriptor before streaming. */
    public function stream(string $slug, array $descriptor): bool
    {
        if (!self::validFile($descriptor)) return false;
        $directory = $this->folder($slug);
        if ($directory === null) return false;
        $path = $directory.'/'.$descriptor['id'];
        if (is_link($path) || !is_file($path) || !is_readable($path)) return false;
        $stream = @fopen($path, 'rb');
        if ($stream === false) return false;
        $size = (fstat($stream) ?: [])['size'] ?? null;
        if ($size !== $descriptor['bytes']) { fclose($stream); return false; }

        $name = $descriptor['name'];
        $fallback = preg_replace('/[^a-z0-9._-]/i', '_', $name);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: '.$descriptor['mime']);
        header('Content-Length: '.$size);
        header('Content-Disposition: attachment; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($name));
        fpassthru($stream);
        fclose($stream);
        return true;
    }
}
