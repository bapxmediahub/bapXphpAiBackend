<?php
// Included only by the disposable MySQL integration suite.
(static function (\App\Services\DatabaseService $store, PDO $pdo): void {
    $pdo->exec('CREATE TABLE categories LIKE orders');
    $writer = static function (string $edit): array {
        $root = dirname(__DIR__);
        $proc = proc_open([PHP_BINARY, $root . '/cli/product-write.php', $root, $edit],
            [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, $root);
        if (!is_resource($proc) || !str_contains((string)fgets($pipes[1]), 'Product Writer')) {
            throw new RuntimeException('CLI writer did not start.');
        }
        if ($edit !== '' && !str_contains((string)fgets($pipes[1]), 'Editing: ' . $edit)) {
            throw new RuntimeException('CLI did not load the requested edit.');
        }
        return [$proc, $pipes];
    };
    $send = static function (array $pipes, string $name, string $slug): void {
        // Seven scalar prompts, then a period for each multiline field.
        fwrite($pipes[0], implode("\n", [$name, $slug, '', '', '', '', '', '.', '.']) . "\n");
        fclose($pipes[0]);
    };
    $finish = static function ($proc, array $pipes): array {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($proc), $out, $err];
    };
    $lookup = static fn(string $slug): array => $store->find('products', $slug, 'slug') ?? [];

    $store->importProducts("slug,name,price\ncli-hidden,Imported draft,12");
    $hidden = $lookup('cli-hidden');
    [$proc, $pipes] = $writer('cli-hidden');
    $ownerChange = ['id'=>$hidden['id'], 'price'=>75, 'offer_price'=>60, 'offer_ends_at'=>'2027-01-20',
        'gst_rate'=>18, 'categories'=>['primary','secondary'], 'specifications'=>['Detail'=>'Original'],
        'image_urls'=>['/gallery.webp'], 'custom_field'=>'preserve'];
    $store->saveCatalogProduct($ownerChange, false);
    $send($pipes, 'Renamed in CLI', '');
    [$code, $out] = $finish($proc, $pipes);
    $saved = $lookup('cli-hidden');
    if ($code !== 0 || !str_contains($out, '/admin/products?edit=' . $hidden['id']) ||
        $saved['name'] !== 'Renamed in CLI' || $saved['status'] !== 'hidden' || $saved['stock_status'] !== 'out_of_stock') {
        throw new RuntimeException('A CLI edit lost the imported product identity, visibility or availability.');
    }
    foreach ($ownerChange as $field=>$value) {
        if (($saved[$field] ?? null) !== $value) throw new RuntimeException("A blank CLI answer overwrote $field.");
    }
    if ((new \App\Services\ProductService($store))->findBySlug('cli-hidden') !== null) {
        throw new RuntimeException('The edited hidden product became public.');
    }

    // Hold the catalogue lock while a CLI create and CSV import target the same slug.
    // The same MySQL connection may reacquire the named lock for this import.
    $lock = "CONCAT('sps:products:', SHA1(DATABASE()))";
    if ((int)$pdo->query("SELECT GET_LOCK($lock, 1)")->fetchColumn() !== 1) throw new RuntimeException('Fixture lock unavailable.');
    try {
        [$proc, $pipes] = $writer('');
        $send($pipes, 'Concurrent create', 'cli-race');
        $result = $store->importProducts("slug,name,price\ncli-race,Concurrent import,15");
    } finally { $pdo->query("SELECT RELEASE_LOCK($lock)"); }
    [$code, , $error] = $finish($proc, $pipes);
    $winner = $lookup('cli-race');
    if ($result['added'] !== 1 || $code === 0 || !str_contains($error, 'slug already belongs') || $winner['name'] !== 'Concurrent import'
        || $winner['status'] !== 'hidden' || count($store->existingProductSlugs(['cli-race'])) !== 1) {
        throw new RuntimeException('Concurrent CLI create overwrote or duplicated the imported product.');
    }
    [$proc, $pipes] = $writer('');
    $send($pipes, 'Standalone CLI product', 'cli-new');
    [$code] = $finish($proc, $pipes);
    if ($code !== 0 || $lookup('cli-new')['name'] !== 'Standalone CLI product') throw new RuntimeException('New CLI product failed.');

    [$proc, $pipes] = $writer('cli-new');
    $send($pipes, '', 'cli-race');
    [$code] = $finish($proc, $pipes);
    if ($code === 0 || !$lookup('cli-new') || count($store->existingProductSlugs(['cli-race'])) !== 1) {
        throw new RuntimeException('CLI renamed a product onto an existing slug.');
    }
    echo "Hidden import, partial CLI edit, concurrent creation and slug collision checks passed.\n";
})($store, $pdo);
