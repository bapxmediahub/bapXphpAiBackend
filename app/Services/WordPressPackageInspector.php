<?php
declare(strict_types=1);
namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Static, read-only WordPress package preflight.
 *
 * No extracted files, includes, activation hooks, runtime compatibility shims,
 * remote requests, configuration reads or installer side effects.
 */
final class WordPressPackageInspector
{
    private const MAX_ARCHIVE_BYTES = 80 * 1024 * 1024;
    private const MAX_EXPANDED_BYTES = 256 * 1024 * 1024;
    private const MAX_FILE_BYTES = 32 * 1024 * 1024;
    private const MAX_ENTRIES = 9000;
    private const MAX_COMPRESSION_RATIO = 1000;
    private const HEADER_READ_LIMIT = 12000;

    public static function inspect(string $archivePath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP support is unavailable; install the PHP zip extension on the QA runner.');
        }
        if (!is_file($archivePath) || !is_readable($archivePath)) {
            throw new InvalidArgumentException('Choose a readable local ZIP file.');
        }
        $bytes = filesize($archivePath);
        if ($bytes === false || $bytes <= 0 || $bytes > self::MAX_ARCHIVE_BYTES) {
            throw new InvalidArgumentException('ZIP size exceeds the inspection limit.');
        }
        $archiveSha = hash_file('sha256', $archivePath);
        if (!is_string($archiveSha)) throw new RuntimeException('ZIP checksum is unavailable.');
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('The supplied file is not a readable ZIP archive.');
        }
        try {
            return self::inspectOpenZip($zip, basename($archivePath), $archiveSha);
        } finally {
            $zip->close();
        }
    }

    private static function inspectOpenZip(ZipArchive $zip, string $filename, string $sha): array
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
            throw new InvalidArgumentException('ZIP entry count is outside the inspection limit.');
        }
        $entries = [];
        $folded = [];
        $topLevel = [];
        $expanded = 0;
        $phpCount = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $stat = $zip->statIndex($i);
            if (!is_string($name) || !is_array($stat) || $name === ''
                || str_contains($name, "\0") || str_contains($name, '\\')
                || str_starts_with($name, '/') || str_contains($name, ':')) {
                throw new InvalidArgumentException('ZIP contains an unsafe member path.');
            }
            $directory = str_ends_with($name, '/');
            $trimmed = rtrim($name, '/');
            $parts = explode('/', $trimmed);
            if ($trimmed === '' || in_array('', $parts, true)
                || in_array('.', $parts, true) || in_array('..', $parts, true)) {
                throw new InvalidArgumentException('ZIP contains a traversal or empty path component.');
            }
            $canonical = strtolower($name);
            if (isset($folded[$canonical])) {
                throw new InvalidArgumentException('ZIP contains a duplicate or case-colliding member.');
            }
            $folded[$canonical] = true;
            if (count($parts) < 2) {
                // The only top-level record allowed is the package directory itself.
                if (!$directory) throw new InvalidArgumentException('ZIP needs one top-level package folder.');
            }
            $topLevel[$parts[0]] = true;
            $size = $stat['size'] ?? null;
            $compressed = $stat['comp_size'] ?? null;
            if (!is_int($size) || !is_int($compressed) || $size < 0 || $compressed < 0
                || $size > self::MAX_FILE_BYTES
                || ($expanded += $size) > self::MAX_EXPANDED_BYTES
                || ($size > 0 && $compressed === 0)
                || ($compressed > 0 && $size / $compressed > self::MAX_COMPRESSION_RATIO)) {
                throw new InvalidArgumentException('ZIP declares unsafe expansion or compression sizes.');
            }
            $operatingSystem = $attributes = 0;
            if ($zip->getExternalAttributesIndex($i, $operatingSystem, $attributes)
                && $operatingSystem === ZipArchive::OPSYS_UNIX
                && (($attributes >> 16) & 0170000) === 0120000) {
                throw new InvalidArgumentException('ZIP symlinks are not accepted.');
            }
            $entries[$name] = $i;
            if (!$directory && str_ends_with(strtolower($name), '.php')) $phpCount++;
        }
        if (count($topLevel) !== 1) {
            throw new InvalidArgumentException('ZIP must have exactly one package root.');
        }
        $root = (string) array_key_first($topLevel);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $root)) {
            throw new InvalidArgumentException('ZIP package root is invalid.');
        }
        $core = isset($entries[$root.'/wp-includes/version.php'])
            && isset($entries[$root.'/wp-admin/admin.php'])
            && isset($entries[$root.'/wp-load.php']);
        $result = [
            'schema' => 'bapx.wordpress-package-preflight.v1',
            'filename' => $filename,
            'archive_sha256' => $sha,
            'package_root' => $root,
            'entry_count' => $zip->numFiles,
            'php_file_count' => $phpCount,
            'expanded_bytes' => $expanded,
            'source_executed' => false,
            'files_extracted' => false,
            'installable' => false,
        ];
        if ($core) {
            $text = $zip->getFromIndex($entries[$root.'/wp-includes/version.php']);
            $version = is_string($text) && preg_match('/\$wp_version\s*=\s*([\'"])([0-9A-Za-z.\\-]+)\1/', $text, $m)
                ? $m[2] : null;
            return $result + [
                'kind' => 'wordpress_core',
                'name' => 'WordPress',
                'version' => $version,
                'entrypoint' => null,
                'compatibility' => 'core_not_plugin',
                'reason' => 'WordPress core is not an installable plugin for this native PHP application.',
            ];
        }
        $candidates = [];
        foreach ($entries as $name => $index) {
            if (!preg_match('~^'.preg_quote($root,'~').'/[^/]+\.php$~iD', $name)) continue;
            $stat = $zip->statIndex($index);
            if (($stat['size'] ?? self::MAX_FILE_BYTES) > 512 * 1024) continue;
            $source = $zip->getFromIndex($index);
            if (!is_string($source)) continue;
            $header = self::pluginHeader(substr($source, 0, self::HEADER_READ_LIMIT));
            if ($header !== null) $candidates[] = ['file'=>$name,'header'=>$header];
        }
        if (count($candidates) !== 1) {
            throw new InvalidArgumentException(count($candidates) === 0
                ? 'No unique WordPress Plugin Name header is present.'
                : 'ZIP contains multiple root plugin entrypoints.');
        }
        $entry = $candidates[0];
        $meta = $entry['header'];
        return $result + [
            'kind' => 'wordpress_plugin',
            'name' => $meta['plugin_name'],
            'version' => $meta['version'] ?? null,
            'entrypoint' => $entry['file'],
            'requires_wordpress' => $meta['requires_at_least'] ?? null,
            'requires_php' => $meta['requires_php'] ?? null,
            'requires_plugins' => self::requirements($meta['requires_plugins'] ?? ''),
            'license' => $meta['license'] ?? null,
            'text_domain' => $meta['text_domain'] ?? null,
            'compatibility' => 'requires_wordpress_runtime',
            'reason' => 'The ZIP is recognized but cannot be activated: the native application does not implement the WordPress plugin runtime. Use a reviewed native adapter or an independently isolated WordPress environment.',
        ];
    }

    private static function pluginHeader(string $source): ?array
    {
        if (!preg_match('~^\s*<\?php\s*/\*+(.*?)\*/~s', $source, $match)) return null;
        $fields = [];
        foreach (explode("\n", $match[1]) as $line) {
            if (!preg_match('/^\s*\*?\s*(Plugin Name|Version|Requires at least|Requires PHP|Requires Plugins|License|Text Domain)\s*:\s*(.*?)\s*$/i', $line, $item)) continue;
            $key = str_replace(' ', '_', strtolower($item[1]));
            $value = trim($item[2]);
            if ($value !== '' && strlen($value) <= 200 && !preg_match('/[\x00-\x1f\x7f]/', $value)) {
                $fields[$key] = $value;
            }
        }
        return isset($fields['plugin_name']) ? $fields : null;
    }

    private static function requirements(string $value): array
    {
        if ($value === '') return [];
        $result = [];
        foreach (explode(',', $value) as $slug) {
            $slug = strtolower(trim($slug));
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/D', $slug)) $result[$slug] = true;
        }
        return array_keys($result);
    }
}
