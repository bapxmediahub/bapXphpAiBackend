<?php
namespace App\Services;

/** Live business prompts only. Developer harness files are never prompt sources. */
final class AgentInstructionService
{
    public const VERSION = 2;
    private const MAX_FILE_BYTES = 12288;
    private const MAX_BUNDLE_BYTES = 49152;
    private const DOCUMENTS = [
        'support' => [
            'supportagent.md', 'bapXcl/skill.md',
            'bapXcl/support/catalog.md', 'bapXcl/support/policy-and-tickets.md',
        ],
        'admin' => [
            'enterpriseagent.md', 'bapXcl/skill.md',
            'bapXcl/support/catalog.md', 'bapXcl/support/policy-and-tickets.md',
            'bapXcl/enterprise/reporting.md',
        ],
    ];

    public static function forSurface(string $surface, ?array $actor = null): string
    {
        $bundle = self::bundle($surface, $actor);
        AgentTraceService::observeInstructionManifest($bundle['manifest']);
        return $bundle['text'];
    }

    /** Hashes and fixed relative paths only; never expose instruction body via HTTP. */
    public static function manifestForSurface(string $surface, ?array $actor = null): array
    {
        return self::bundle($surface, $actor)['manifest'];
    }

    private static function bundle(string $surface, ?array $actor): array
    {
        if (!array_key_exists($surface, self::DOCUMENTS)) {
            throw new \InvalidArgumentException('Unknown agent surface');
        }
        $actor ??= is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : null;
        if ($surface === 'admin' && ($actor['role'] ?? null) !== 'admin' && ($actor['is_admin'] ?? null) !== true) {
            throw new \RuntimeException('Admin access required for Enterprise instructions.');
        }
        $root = app_path('.bapX');
        $files = []; $parts = []; $bytes = 0;
        foreach (self::DOCUMENTS[$surface] as $relative) {
            $text = self::readRequiredDocument($root, $relative);
            $bytes += strlen($text);
            if ($bytes > self::MAX_BUNDLE_BYTES) throw new \RuntimeException('Runtime instruction bundle exceeds its bound.');
            $files[] = ['path'=>'.bapX/'.$relative, 'sha256'=>hash('sha256',$text)];
            $parts[] = trim($text);
        }
        $manifest = ['version'=>self::VERSION, 'surface'=>$surface, 'files'=>$files];
        $manifest['sha256'] = hash('sha256',json_encode($manifest,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
        return ['text'=>implode("\n\n",$parts), 'manifest'=>$manifest];
    }

    /** Fixed manifest paths only; refuse symlink hops and oversized/non-text inputs. */
    private static function readRequiredDocument(string $root, string $relative): string
    {
        $allowed = array_merge(...array_values(self::DOCUMENTS));
        if (!in_array($relative,$allowed,true) || is_link($root)) {
            throw new \RuntimeException('Runtime instruction source is not allowed.');
        }
        $base = realpath($root);
        if ($base === false || !is_dir($base)) throw new \RuntimeException('Runtime instruction directory is unavailable.');
        $candidate = $base;
        foreach (explode('/',$relative) as $part) {
            $candidate .= DIRECTORY_SEPARATOR.$part;
            if (is_link($candidate)) throw new \RuntimeException('Linked runtime instruction files are not allowed.');
        }
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved,$base.DIRECTORY_SEPARATOR)
            || !is_file($resolved) || !is_readable($resolved)) {
            throw new \RuntimeException('Required runtime instruction file is unavailable.');
        }
        $text = @file_get_contents($resolved,false,null,0,self::MAX_FILE_BYTES+1);
        if (!is_string($text) || trim($text)==='' || strlen($text)>self::MAX_FILE_BYTES
            || str_contains($text,"\0") || !mb_check_encoding($text,'UTF-8')) {
            throw new \RuntimeException('Required runtime instruction file is invalid or oversized.');
        }
        return $text;
    }
}
