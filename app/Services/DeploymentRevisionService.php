<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Read the commit checked out by the running PHP installation, without shell
 * execution, network access, or any user-selected filesystem path.
 *
 * This does not claim Hostinger deployed unless the live checkout exposes
 * readable Git metadata. Missing/unreadable metadata is an unknown, not a match.
 */
final class DeploymentRevisionService
{
    private string $root;
    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? app_path(), '/');
    }

    private static function sha(?string $value): ?string
    {
        $value = trim((string)$value);
        return preg_match('/^[a-f0-9]{40}$/D', $value) === 1 ? $value : null;
    }

    private static function readFile(string $path, int $maxBytes = 4096): ?string
    {
        if (is_link($path) || !is_file($path)) return null;
        $size = @filesize($path);
        if ($size === false || $size > $maxBytes) return null;
        $data = @file_get_contents($path, false, null, 0, $maxBytes + 1);
        return is_string($data) && strlen($data) <= $maxBytes ? trim($data) : null;
    }

    private function gitDir(): ?string
    {
        $dotGit = $this->root . '/.git';
        if (is_link($dotGit)) return null;
        if (is_dir($dotGit)) return $dotGit;
        // Git worktrees can use a .git text file naming the administrative dir.
        $marker = self::readFile($dotGit, 1024);
        if ($marker === null || !preg_match('/^gitdir: ([^\r\n\x00]{1,768})$/D', $marker, $m)) return null;
        $candidate = str_starts_with($m[1], '/') ? $m[1] : $this->root . '/' . $m[1];
        $directory = realpath($candidate);
        return $directory !== false && is_dir($directory) && !is_link($candidate) ? $directory : null;
    }

    private static function refSha(string $gitDir, string $ref): ?string
    {
        if (!preg_match('~^refs/[a-zA-Z0-9_./-]{1,190}$~D', $ref)
            || str_contains($ref, '..') || str_contains($ref, '//')
            || str_contains($ref, '/./')) return null;
        $loose = self::sha(self::readFile($gitDir . '/' . $ref));
        if ($loose !== null) return $loose;
        // A clone's current branch may be stored only in packed-refs.
        $packed = self::readFile($gitDir . '/packed-refs', 2 * 1024 * 1024);
        if ($packed === null) return null;
        foreach (explode("\n", $packed) as $line) {
            if (preg_match('/^([a-f0-9]{40}) ([^\r\n]+)$/D', trim($line), $matches)
                && $matches[2] === $ref) return $matches[1];
        }
        return null;
    }

    public function checkoutSha(): ?string
    {
        $gitDir = $this->gitDir();
        if ($gitDir === null) return null;
        $head = self::readFile($gitDir . '/HEAD', 1024);
        if ($head === null) return null;
        $detached = self::sha($head);
        if ($detached !== null) return $detached;
        if (!preg_match('/^ref: (refs\/[a-zA-Z0-9_.\/-]{1,190})$/D', $head, $matches)) return null;
        $ref = $matches[1];
        $sha = self::refSha($gitDir, $ref);
        if ($sha !== null) return $sha;
        // Git worktrees can keep refs in a common directory.
        $common = self::readFile($gitDir . '/commondir', 512);
        if ($common === null || str_contains($common, "\0")) return null;
        $resolved = realpath($gitDir . '/' . $common);
        return $resolved !== false && is_dir($resolved) ? self::refSha($resolved, $ref) : null;
    }
}
