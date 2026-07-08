<?php
namespace App\Services;

final class ImageOptimizerService {
    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1920;
    private const THUMB_WIDTH = 480;
    private const THUMB_HEIGHT = 640;
    private const WEBP_QUALITY = 80;
    private const JPEG_QUALITY = 85;

    public function optimize(string $sourcePath, ?string $destDir = null, array $opts = []): ?string {
        if (!is_file($sourcePath)) return null;
        $maxW = $opts['max_width'] ?? self::MAX_WIDTH;
        $maxH = $opts['max_height'] ?? self::MAX_HEIGHT;
        $quality = $opts['quality'] ?? self::WEBP_QUALITY;
        $thumb = $opts['thumb'] ?? false;

        $info = @getimagesize($sourcePath);
        if (!$info) return null;
        [$srcW, $srcH, $type] = $info;

        $src = $this->createSrcImage($sourcePath, $type);
        if (!$src) return null;

        $targetDir = $destDir ?? dirname($sourcePath);
        if (!is_dir($targetDir)) mkdir($targetDir, 0775, true);

        $base = pathinfo($sourcePath, PATHINFO_FILENAME);

        $webpPath = $targetDir . '/' . $base . '.webp';

        $ratio = min($maxW / $srcW, $maxH / $srcH, 1);
        $dstW = (int)round($srcW * $ratio);
        $dstH = (int)round($srcH * $ratio);

        if ($ratio < 1) {
            $dst = imagecreatetruecolor($dstW, $dstH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        } else {
            $dst = $src;
        }

        imagewebp($dst, $webpPath, $quality);

        if (!is_file($webpPath)) return null;

        return $webpPath;
    }

    public function makeThumbnail(string $sourcePath, ?string $destDir = null): ?string {
        return $this->optimize($sourcePath, $destDir, [
            'max_width' => self::THUMB_WIDTH,
            'max_height' => self::THUMB_HEIGHT,
            'quality' => 75,
            'thumb' => true,
        ]);
    }

    public function batchOptimize(string $dir, array $exts = ['png', 'jpg', 'jpeg'], array $opts = []): array {
        $results = [];
        foreach (glob($dir . '/*.{' . implode(',', $exts) . '}', GLOB_BRACE) ?: [] as $file) {
            $out = $this->optimize($file, $opts['dest_dir'] ?? dirname($file), $opts);
            if ($out) {
                $results[] = ['source' => $file, 'webp' => $out, 'size_before' => filesize($file), 'size_after' => filesize($out)];
            }
        }
        return $results;
    }

    private function createSrcImage(string $path, int $type): ?\GdImage {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            IMAGETYPE_GIF => @imagecreatefromgif($path) ?: null,
            IMAGETYPE_WEBP => @imagecreatefromwebp($path) ?: null,
            default => null,
        };
    }
}
