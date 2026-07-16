<?php
/**
 * Download Chrome/Chromium binary for Linux x86_64
 * Uses Chrome for Testing (stable) or Chromium snapshots
 */

$binDir = __DIR__ . '/chrome-linux';
$chromePath = $binDir . '/chrome';

if (is_file($chromePath) && is_executable($chromePath)) {
    echo "Chrome already exists at $chromePath\n";
    exit(0);
}

echo "Downloading Chrome for Testing (Linux x86_64)...\n";

// Use Chrome for Testing API
$apiUrl = 'https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions-with-downloads.json';
$json = @file_get_contents($apiUrl);
if (!$json) {
    echo "Failed to fetch Chrome for Testing manifest\n";
    exit(1);
}

$data = json_decode($json, true);
$version = $data['channels']['Stable']['version'];
$downloads = $data['channels']['Stable']['downloads'];

$chromeDownload = null;
foreach ($downloads['chrome-headless-shell'] ?? [] as $d) {
    if ($d['platform'] === 'linux64') {
        $chromeDownload = $d['url'];
        break;
    }
}

// Fallback to chrome if headless-shell not available
if (!$chromeDownload) {
    foreach ($downloads['chrome'] ?? [] as $d) {
        if ($d['platform'] === 'linux64') {
            $chromeDownload = $d['url'];
            break;
        }
    }
}

if (!$chromeDownload) {
    echo "No Linux x86_64 download found\n";
    exit(1);
}

echo "Downloading Chrome $version from $chromeDownload...\n";

$zipPath = $binDir . '/chrome-linux.zip';
$fp = fopen($zipPath, 'w');
$ch = curl_init($chromeDownload);
curl_setopt_array($ch, [
    CURLOPT_FILE => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_USERAGENT => 'bapXphp-chrome-downloader/1.0',
]);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

if ($httpCode !== 200) {
    echo "Download failed (HTTP $httpCode)\n";
    exit(1);
}

echo "Extracting...\n";
$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    echo "Failed to open zip\n";
    exit(1);
}
$zip->extractTo($binDir);
$zip->close();
unlink($zipPath);

// Find the chrome binary
$finder = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($binDir));
foreach ($finder as $file) {
    if ($file->isFile() && (str_contains($file->getFilename(), 'chrome') || str_contains($file->getFilename(), 'headless_shell'))) {
        $target = $binDir . '/chrome';
        rename($file->getPathname(), $target);
        chmod($target, 0755);
        echo "Chrome installed at $target\n";
        break;
    }
}

// Clean up extra files
$finder = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($binDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($finder as $file) {
    if ($file->isFile() && $file->getPathname() !== $binDir . '/chrome') {
        unlink($file->getPathname());
    } elseif ($file->isDir() && $file->getPathname() !== $binDir) {
        @rmdir($file->getPathname());
    }
}

echo "Done.\n";
