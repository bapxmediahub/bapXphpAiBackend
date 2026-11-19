<?php
// The caller uses only the disposable CI database and one connection. Temporary
// tables shadow real tables for the duration of this fixture.
require_once dirname(__DIR__).'/cli/lib/LegacyMediaMigration.php';
(static function (App\Services\DatabaseService $store, PDO $pdo): void {
    $root = sys_get_temp_dir().'/legacy-media-'.bin2hex(random_bytes(6));
    mkdir($root.'/storage/media', 0700, true);
    $path = $root.'/storage/media/products.yaml';
    $pdo->exec("CREATE TEMPORARY TABLE media_files (
        id VARCHAR(64) PRIMARY KEY, _data JSON NOT NULL, _owner VARCHAR(255), _status VARCHAR(64),
        _created_at VARCHAR(40), _updated_at VARCHAR(40), CHECK (id <> 'db-reject')) ENGINE=InnoDB");
    try {
        $yaml = <<<'YAML'
media:
  - id: add
    url: /assets/images/media/a.webp
    context: products
    description: "Historical"
    used_in:
      - type: products
        id: item-1
        name: "Example product"
  - id: existing
    url: /assets/images/media/b.webp
    description: "Old description"
    original_name: B.png
YAML;
        file_put_contents($path, $yaml);
        $input=LegacyMediaMigration::input($root);
        $store->upsert('media_files', ['id'=>'existing','url'=>'/assets/images/media/b.webp','context'=>'products','description'=>'','extra'=>'keep']);
        $before=LegacyMediaMigration::run($input, $store, false);
        if ($before['insert']!==1 || $before['backfill']!==1 || $before['verified']!==0 || $store->find('media_files','add')!==null) {
            throw new RuntimeException('Preflight changed records or miscounted missing data.');
        }
        $applied=LegacyMediaMigration::run($input, $store, true);
        $db=$store->find('media_files','existing');
        if (!$applied['complete'] || $applied['verified']!==2 || $db['description']!=='' || $db['extra']!=='keep' || $db['original_name']!=='B.png'
            || $store->find('media_files','add')['used_in'][0]['id']!=='item-1') throw new RuntimeException('Migration lost metadata or references.');
        $retry=LegacyMediaMigration::run($input, $store, true);
        if ($retry['inserted'] || $retry['backfilled'] || $retry['already_covered']!==2 || file_get_contents($path)!==$yaml) {
            throw new RuntimeException('Repeat changed rows or source YAML.');
        }
        $conflict = ['id'=>'another-id','url'=>'/assets/images/media/b.webp','context'=>'products'];
        $fresh = ['id'=>'fresh','url'=>'/assets/images/media/fresh.webp','context'=>'products'];
        $batch = ['source_rows'=>2,'records'=>[$fresh,$conflict]];
        $blocked=LegacyMediaMigration::run($batch,$store,true);
        if ($blocked['conflicts']!==1 || $blocked['complete'] || $store->find('media_files','fresh')!==null) throw new RuntimeException('Collision allowed a partial migration.');
        $batch['records'][1]=['id'=>'db-reject','url'=>'/assets/images/media/fail.webp','context'=>'products'];
        try { LegacyMediaMigration::run($batch,$store,true); throw new RuntimeException('Database rejection was ignored.'); }
        catch (PDOException) {}
        if ($store->find('media_files','fresh')!==null) throw new RuntimeException('Interrupted migration left partial rows.');
        file_put_contents($path, $yaml."\n    unrecognised: metadata\n");
        try { LegacyMediaMigration::input($root); } catch (RuntimeException) { echo "Legacy media migration: coverage, precedence, idempotence, collision, rollback and unsupported YAML passed.\n"; return; }
        throw new RuntimeException('Unrecognised historical fields must block migration.');
    } finally {
        $pdo->exec('DROP TEMPORARY TABLE media_files');
        if (is_file($path)) unlink($path);
        rmdir($root.'/storage/media'); rmdir($root.'/storage'); rmdir($root);
    }
})($store, $pdo);
