<?php
declare(strict_types=1);
header('Content-Type: application/json');
$payload=json_decode((string)file_get_contents('php://input'),true)?:[];
if (($payload['password']??'')!=='sps_fixture_only_password') {
    http_response_code(401);echo json_encode(['success'=>false,'error'=>'invalid fixture credentials']);exit;
}
$path=(string)getenv('BAPX_MEDIA_FIXTURE_STATE');
$data=json_decode((string)file_get_contents($path),true)?:[];
if (isset($payload['query'])) {
    $query=(string)$payload['query'];
    if ($query === "SELECT id, _data FROM settings WHERE id NOT LIKE 'pg:%'") {
        $records=array_filter($data['collections']['settings']??[], static fn($r)=>!str_starts_with($r['id'],'pg:'));
    } elseif (str_starts_with($query, 'SELECT SUBSTRING(id, 4) AS slug, ')
        && str_contains($query, " FROM settings WHERE id LIKE 'pg:%'")
        && str_ends_with($query, ' ORDER BY title, id')) {
        // AuthoredPageService::summaries() metadata; no arbitrary SQL executed in this fixture.
        $params = $payload['params'] ?? [];
        if (!is_array($params) || count($params) !== substr_count($query, '?')) {
            http_response_code(400); echo json_encode(['success'=>false,'error'=>'invalid page parameters']); exit;
        }
        $slugCount = preg_match('/ AND id IN \((\?(?:,\?)*)\)/', $query, $matches)
            ? substr_count($matches[1], '?') : 0;
        $slugs = array_slice($params, 0, $slugCount);
        $words = array_slice($params, $slugCount);
        $public = str_contains($query, " = 'published'");
        $pages = array_values(array_filter($data['collections']['settings'] ?? [],
            static function (array $page) use ($public, $slugs, $slugCount, $words): bool {
                $id = (string)($page['id'] ?? '');
                if (!str_starts_with($id, 'pg:') || ($public && ($page['status'] ?? null) !== 'published')) return false;
                if ($slugCount && !in_array($id, $slugs, true)) return false;
                $text = (string)($page['title'] ?? '') . ' ' . (string)($page['content'] ?? '');
                foreach ($words as $word) {
                    if (!is_string($word) || mb_stripos($text, $word) === false) return false;
                }
                return true;
            }));
        usort($pages, static fn(array $a, array $b): int =>
            [strtolower((string)($a['title'] ?? '')), (string)($a['id'] ?? '')]
            <=> [strtolower((string)($b['title'] ?? '')), (string)($b['id'] ?? '')]);
        $rows = array_map(static fn(array $page): array => [
            'slug' => substr((string)$page['id'], 3),
            'title' => (string)($page['title'] ?? ''),
            'status' => (string)($page['status'] ?? ''),
            'revision' => (string)($page['revision'] ?? ''),
        ], $pages);
        echo json_encode(['success'=>true,'data'=>$rows], JSON_THROW_ON_ERROR); exit;
    } elseif (preg_match('/^SELECT \* FROM (media_files|settings) WHERE id = \?$/D', $query, $match)) {
        $id = $payload['params'][0] ?? null;
        $records = array_filter($data['collections'][$match[1]] ?? [], static fn(array $row): bool => $row['id'] === $id);
    } elseif (preg_match("/^SELECT id, _data FROM media_files\s+WHERE BINARY JSON_UNQUOTE/", $query)) {
        $url = $payload['params'][0] ?? null;
        $records = array_slice(array_values(array_filter($data['collections']['media_files'] ?? [],
            static fn(array $row): bool => ($row['url'] ?? null) === $url)), 0, 2);
    } elseif (preg_match('/^SELECT \* FROM (media_files|products|temples|categories|settings)$/D',$query,$m)) {
        $records=$data['collections'][$m[1]]??[];
    } else {
        http_response_code(400);echo json_encode(['success'=>false,'error'=>'fixture query not allowed']);exit;
    }
    if (isset($m[1]) && in_array($m[1], $data['reject_read_collections'] ?? [], true)) {
        http_response_code(503); echo json_encode(['success'=>false, 'error'=>'fixture read unavailable']); exit;
    }
    $rows=array_values(array_map(static fn(array $r):array=>['id'=>$r['id']??'', '_data'=>json_encode($r,JSON_THROW_ON_ERROR)], $records));
    echo json_encode(['success'=>true,'data'=>$rows],JSON_THROW_ON_ERROR);exit;
}
if (($payload['action'] ?? '') === 'save_authored_page' && ($payload['collection'] ?? '') === 'settings') {
    $record = $payload['record'];
    $expected = $payload['expected'];
    $url = $record['header_image_url'] ?? null;
    if ($url !== null && !in_array($url, array_column($data['collections']['media_files'], 'url'), true)) {
        echo json_encode(['success'=>true, 'saved'=>false, 'header_unavailable'=>true], JSON_THROW_ON_ERROR); exit;
    }
    foreach ($data['collections']['settings'] as $index => $saved) {
        if ($saved['id'] !== $record['id']) continue;
        if ($saved['revision'] !== $expected) {
            echo json_encode(['success'=>true, 'saved'=>false], JSON_THROW_ON_ERROR); exit;
        }
        $data['collections']['settings'][$index] = $record;
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
        echo json_encode(['success'=>true, 'saved'=>true], JSON_THROW_ON_ERROR); exit;
    }
    if ($expected === '0') {
        $data['collections']['settings'][] = $record;
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
    }
    echo json_encode(['success'=>true, 'saved'=>$expected === '0'], JSON_THROW_ON_ERROR); exit;
}
if (($payload['action']??'')==='delete' && ($payload['collection']??'')==='media_files') {
    $id=(string)($payload['id']??'');
    if (in_array($id,$data['reject_delete_ids']??[],true)) {
        http_response_code(503);echo json_encode(['success'=>false,'error'=>'fixture metadata removal unavailable']);exit;
    }
    $data['collections']['media_files']=array_values(array_filter($data['collections']['media_files']??[],
        static fn(array $r):bool=>($r['id']??'')!==$id));
    file_put_contents($path,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX);
    echo json_encode(['success'=>true],JSON_THROW_ON_ERROR);exit;
}
http_response_code(400);echo json_encode(['success'=>false,'error'=>'unsupported fixture operation']);
