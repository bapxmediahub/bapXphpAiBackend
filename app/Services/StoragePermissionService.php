<?php
namespace App\Services;

final class StoragePermissionService {
    private array $paths = [
        ['label'=>'Environment file', 'path'=>'.env', 'type'=>'file', 'mode'=>0664],
        ['label'=>'Storage root', 'path'=>'storage', 'type'=>'dir', 'mode'=>0775],
        ['label'=>'App data', 'path'=>'storage', 'type'=>'dir', 'mode'=>0775],
        ['label'=>'Backups', 'path'=>'storage/backups', 'type'=>'dir', 'mode'=>0775],
        ['label'=>'Media assets', 'path'=>'assets/images/media', 'type'=>'dir', 'mode'=>0775],
    ];

    public function status(): array {
        return array_map(function (array $item): array {
            $absolute = app_path($item['path']);
            return $item + [
                'absolute' => $absolute,
                'exists' => file_exists($absolute),
                'writable' => is_writable($absolute),
                'permission' => file_exists($absolute) ? substr(sprintf('%o', fileperms($absolute)), -4) : 'missing',
            ];
        }, $this->paths);
    }

    public function fix(): array {
        foreach ($this->paths as $item) {
            $absolute = app_path($item['path']);
            if ($item['type'] === 'dir' && !is_dir($absolute)) mkdir($absolute, 0775, true);
            if ($item['type'] === 'file' && !is_file($absolute)) touch($absolute);
            if (file_exists($absolute)) @chmod($absolute, $item['mode']);
        }
        return $this->status();
    }
}
