<?php
declare(strict_types=1);

use App\Services\WordPressPackageInspector;

$tests['WordPress ZIP preflight accepts real package shapes without executing plugin PHP'] = static function (): void {
    if (!class_exists(\ZipArchive::class)) throw new RuntimeException('Test runner must have zip enabled');
    $tempDir = sys_get_temp_dir().'/bapx-plugin-preflight-'.bin2hex(random_bytes(5));
    if (!mkdir($tempDir, 0700)) throw new RuntimeException('Cannot create isolated plugin fixture directory');
    $make = static function (array $files) use ($tempDir): string {
        $path = $tempDir.'/sample-'.bin2hex(random_bytes(5)).'.zip';
        $archive = new \ZipArchive();
        if ($archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create isolated ZIP fixture');
        }
        foreach ($files as $name => $contents) $archive->addFromString($name, $contents);
        $archive->close();
        return $path;
    };
    $reject = static function (string $file): void {
        try {
            WordPressPackageInspector::inspect($file);
            throw new RuntimeException('A hostile or unrecognized package was incorrectly accepted');
        } catch (\InvalidArgumentException $expected) {
            assertTrue(trim($expected->getMessage()) !== '', 'Rejected archive has a bounded operator-readable reason');
        }
    };
    try {
        $dangerousFile = $tempDir.'/plugin-php-must-not-run';
        $pluginSource = "<?php\n/**\n * Plugin Name: Verified Fixture\n * Version: 1.2.3\n * Requires at least: 6.0\n * Requires PHP: 8.1\n * Requires Plugins: woocommerce, hello-dolly\n * License: Apache-2.0\n * Text Domain: verified-fixture\n */\n"
            .'file_put_contents('.var_export($dangerousFile, true).", 'unsafe');";
        $plugin = $make(['verified/verified.php'=>$pluginSource, 'verified/asset.txt'=>'normal']);
        $info = WordPressPackageInspector::inspect($plugin);
        assertSame('wordpress_plugin', $info['kind'], 'Valid archive detected as WP plugin package');
        assertSame('Verified Fixture', $info['name'], 'Plugin Name derives only from header');
        assertSame('1.2.3', $info['version'], 'Version derives only from header');
        assertSame(['woocommerce','hello-dolly'], $info['requires_plugins'], 'Dependency header is normalized');
        assertSame('requires_wordpress_runtime', $info['compatibility'], 'Plugin is not misrepresented as executable here');
        assertSame(false, $info['source_executed'], 'No third-party source is evaluated');
        assertSame(false, $info['files_extracted'], 'No files are extracted to the document root');
        assertSame(false, $info['installable'], 'Preflight does not claim activation');
        assertSame(false, file_exists($dangerousFile), 'Plugin PHP must never be executed');
        assertSame(hash_file('sha256', $plugin), $info['archive_sha256'], 'ZIP has reproducible provenance');

        $core = $make([
            'wordpress/wp-includes/version.php'=>"<?php \$wp_version = '7.1.4';",
            'wordpress/wp-admin/admin.php'=>'<?php',
            'wordpress/wp-load.php'=>'<?php',
        ]);
        $coreInfo = WordPressPackageInspector::inspect($core);
        assertSame('wordpress_core', $coreInfo['kind'], 'WordPress distribution is not mislabelled as a plugin');
        assertSame('7.1.4', $coreInfo['version'], 'Core version read statically');
        assertSame(false, $coreInfo['installable'], 'WordPress core is not installable in native app');

        $reject($make(['../escape.php'=>$pluginSource]));
        $reject($make(['/absolute/evil.php'=>$pluginSource]));
        $reject($make(['plugin/../evil.php'=>$pluginSource]));
        $reject($make(['plugin/plugin.php'=>$pluginSource, 'elsewhere/readme.txt'=>'two roots']));
        $reject($make(['plugin/file.php'=>'<?php echo "Not a WordPress header";']));
        $reject($make(['plugin/one.php'=>$pluginSource, 'plugin/two.php'=>$pluginSource]));
        $reject($make(['plugin/foo.php'=>$pluginSource, 'plugin/FOO.php'=>'<?php']));
        $reject($make(['plugin/plugin.php'=>$pluginSource, 'plugin/huge.txt'=>str_repeat('A', 14*1024*1024)]));

        $symlink = $make(['plugin/plugin.php'=>$pluginSource, 'plugin/link.php'=>'../secret']);
        $zip = new \ZipArchive();
        $zip->open($symlink);
        assertTrue($zip->setExternalAttributesName('plugin/link.php', \ZipArchive::OPSYS_UNIX, 0120777 << 16), 'Symlink fixture contains UNIX attributes');
        $zip->close();
        $reject($symlink);
    } finally {
        foreach (glob($tempDir.'/*') ?: [] as $name) @unlink($name);
        @rmdir($tempDir);
    }
};
