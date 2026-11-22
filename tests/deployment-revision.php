<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';

function verify(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
$root = sys_get_temp_dir().'/sps-deploy-revision-'.bin2hex(random_bytes(8));
mkdir($root.'/.git/refs/heads',0700,true);
$service = new \App\Services\DeploymentRevisionService($root);
$one = str_repeat('a',40);
$two = str_repeat('b',40);
try {
    verify($service->checkoutSha() === null, 'Missing Git HEAD must be unavailable');
    file_put_contents($root.'/.git/HEAD', "ref: refs/heads/main\n");
    file_put_contents($root.'/.git/refs/heads/main', $one."\n");
    verify($service->checkoutSha() === $one, 'Loose branch ref must match');
    unlink($root.'/.git/refs/heads/main');
    file_put_contents($root.'/.git/packed-refs', "# packed-refs\n$two refs/heads/main\n");
    verify($service->checkoutSha() === $two, 'Packed ref must match');
    file_put_contents($root.'/.git/HEAD', $one."\n");
    verify($service->checkoutSha() === $one, 'Detached checkout must match');
    file_put_contents($root.'/.git/HEAD', 'ref: refs/heads/../../../../../secrets');
    verify($service->checkoutSha() === null, 'Traversal must be rejected');
    file_put_contents($root.'/.git/HEAD', 'not-a-git-sha');
    verify($service->checkoutSha() === null, 'Malformed Git HEAD must be rejected');
    unlink($root.'/.git/HEAD');
    symlink($root.'/.git/packed-refs', $root.'/.git/HEAD');
    verify($service->checkoutSha() === null, 'Symlinked HEAD must be rejected');
    echo "Deploy revision: detached, loose, packed, missing, traversal and symlink cases passed.\n";
} finally {
    foreach (glob($root.'/.git/refs/heads/*') ?: [] as $file) @unlink($file);
    foreach (glob($root.'/.git/*') ?: [] as $file) if (!is_dir($file)) @unlink($file);
    @rmdir($root.'/.git/refs/heads'); @rmdir($root.'/.git/refs'); @rmdir($root.'/.git'); @rmdir($root);
}
