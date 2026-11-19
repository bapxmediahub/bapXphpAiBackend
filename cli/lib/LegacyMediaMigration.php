<?php
declare(strict_types=1);

use App\Services\DatabaseService;

/** Developer CLI only. No runtime catalogue reads depend on the archived inputs. */
final class LegacyMediaMigration {
    private const MAX_ROWS = 250;
    private const MAX_BYTES = 1048576;
    private const CONTEXTS = ['shared','products','temples','astrologers','blog'];

    /** Parse the historical media writer's bounded YAML subset. Refuse unrecognised data. */
    public static function input(string $root): array {
        $files = [[$root.'/storage/media.yaml', null], [$root.'/storage/migrations/legacy-media/products.yaml', 'products']];
        foreach (glob($root.'/storage/media/*.yaml') ?: [] as $path) {
            $context = basename($path, '.yaml');
            if (!in_array($context, self::CONTEXTS, true)) throw new RuntimeException('Unrecognised legacy catalogue context.');
            $files[] = [$path, $context];
        }
        $records = [];
        $count = $bytes = 0;
        foreach ($files as [$file, $context]) {
            if (!file_exists($file)) continue;
            if (!is_file($file) || !is_readable($file) || is_link($file)) throw new RuntimeException('Legacy input is unreadable or linked.');
            $yaml = file_get_contents($file, false, null, 0, self::MAX_BYTES - $bytes + 1);
            if ($yaml === false || ($bytes += strlen($yaml)) > self::MAX_BYTES) throw new RuntimeException('Legacy input exceeds 1 MiB or cannot be read.');
            $rows = self::parse($yaml, $context);
            if (($count += count($rows)) > self::MAX_ROWS) throw new RuntimeException('Legacy input exceeds 250 records.');
            foreach ($rows as $row) {
                $id = $row['id'];
                if (isset($records[$id]) && $records[$id] != $row) throw new RuntimeException('Legacy files disagree on a media ID.');
                $records[$id] = $row;
            }
        }
        $urls = array_column($records, 'url');
        if (count(array_unique($urls)) !== count($records)) throw new RuntimeException('More than one legacy ID claims a URL.');
        ksort($records, SORT_STRING);
        return ['source_rows'=>$count, 'records'=>array_values($records)];
    }

    private static function parse(string $yaml, ?string $context): array {
        $rows = [];
        $current = null;
        $usage = false;
        $entry = -1;
        foreach (preg_split('/\r\n|\n|\r/', $yaml) as $index=>$line) {
            if (trim($line) === '' || preg_match('/^\s*#/', $line) || trim($line) === 'media:') continue;
            if (preg_match('/^\s*-\s*id:\s*([a-zA-Z0-9_-]{1,64})\s*$/D', $line, $m)) {
                if ($current !== null) $rows[] = $current;
                $current = ['id'=>$m[1]];
                $usage = false;
                continue;
            }
            if ($current !== null && preg_match('/^\s+used_in:\s*(?:\[\s*\])?\s*$/D', $line) && !isset($current['used_in'])) {
                $current['used_in'] = [];
                $usage = true; $entry = -1;
                continue;
            }
            if ($current !== null && $usage && preg_match('/^\s+-\s*type:\s*(.+)$/D', $line, $m)) {
                $current['used_in'][++$entry] = ['type'=>self::scalar($m[1])];
                continue;
            }
            if ($current !== null && $usage && $entry >= 0 && preg_match('/^\s+(id|name):\s*(.*)$/D', $line, $m)
                && !array_key_exists($m[1], $current['used_in'][$entry])) {
                $current['used_in'][$entry][$m[1]] = self::scalar($m[2]);
                continue;
            }
            if ($current !== null && preg_match('/^\s+(filename|original_name|url|original_url|path|description|context|mime|created_at):\s*(.*)$/D', $line, $m)
                && !array_key_exists($m[1], $current)) {
                $usage = false;
                $current[$m[1]] = self::scalar($m[2]);
                continue;
            }
            throw new RuntimeException('Unsupported legacy YAML at line '.($index + 1).'. No records changed.');
        }
        if ($current !== null) $rows[] = $current;
        foreach ($rows as &$row) {
            $row['context'] ??= $context ?? 'shared';
            $row['url'] ??= $row['path'] ?? '';
            if ($row['url'] === '' || !in_array($row['context'], self::CONTEXTS, true)
                || ($context !== null && $context !== $row['context'])) throw new RuntimeException('Legacy record has an invalid URL or context.');
            $row['source'] = 'legacy';
        }
        return $rows;
    }

    private static function scalar(string $input): string {
        $input = trim($input);
        if (str_starts_with($input, '"')) {
            $decoded = json_decode($input, true, flags: JSON_THROW_ON_ERROR);
            if (!is_string($decoded)) throw new RuntimeException('Invalid quoted legacy scalar.');
            return $decoded;
        }
        if (preg_match('/^[\x27|>\[\]{}&*!#]|\s#/', $input)) throw new RuntimeException('Unsupported legacy scalar.');
        return $input;
    }

    /** Count by ID and URL. Existing MySQL keys, including empty values, always win. */
    private static function compare(array $records, DatabaseService $store, bool $lock): array {
        $plan = ['covered'=>0, 'insert'=>[], 'backfill'=>[], 'conflicts'=>0];
        if (!$records) return $plan;
        $ids = array_column($records, 'id');
        $urls = array_column($records, 'url');
        $in = implode(',', array_fill(0, count($records), '?'));
        $path = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.url')), 'null'),JSON_UNQUOTE(JSON_EXTRACT(_data, '$.path')))";
        $rows = $store->query("SELECT id,_data FROM media_files WHERE id IN ($in) OR $path IN ($in) LIMIT 501".($lock ? ' FOR UPDATE' : ''), [...$ids, ...$urls]);
        if (count($rows) === 501) throw new RuntimeException('Too many matching MySQL records to reconcile this batch.');
        $byId = $owners = [];
        foreach ($rows as $r) {
            $record = ['id'=>$r['id']] + json_decode($r['_data'], true, flags: JSON_THROW_ON_ERROR);
            $byId[$r['id']] = $record;
            $owners[$record['url'] ?? $record['path'] ?? ''][] = $r['id'];
        }
        foreach ($records as $legacy) {
            $id = $legacy['id']; $url = $legacy['url'];
            $db = $byId[$id] ?? null;
            if (array_diff($owners[$url] ?? [], [$id]) ||
                ($db !== null && (($db['url'] ?? $db['path'] ?? $url) !== $url || ($db['context'] ?? $legacy['context']) !== $legacy['context']))) {
                $plan['conflicts']++;
            } elseif ($db === null) {
                $plan['insert'][] = $legacy;
            } elseif (array_diff_key($legacy, $db)) {
                $plan['backfill'][] = $db + $legacy;
            } else {
                $plan['covered']++;
            }
        }
        return $plan;
    }

    public static function run(array $input, DatabaseService $store, bool $apply): array {
        $records = $input['records'];
        $pdo = $apply ? $store->connection() : null;
        if ($pdo) {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $pdo->beginTransaction();
        }
        try {
            $plan = self::compare($records, $store, $apply);
            $verified = $plan['covered'];
            if ($pdo && $plan['conflicts'] === 0) {
                $insert = $pdo->prepare('INSERT INTO media_files (id,_data,_created_at,_updated_at) VALUES (?,?,NOW(),NOW())');
                $update = $pdo->prepare('UPDATE media_files SET _data=? WHERE id=?');
                foreach ($plan['insert'] as $row) $insert->execute([$row['id'],json_encode($row, JSON_THROW_ON_ERROR)]);
                foreach ($plan['backfill'] as $row) $update->execute([json_encode($row, JSON_THROW_ON_ERROR),$row['id']]);
                $verified = self::compare($records, $store, true)['covered'];
                if ($verified !== count($records)) throw new RuntimeException('Migration verification failed.');
            }
            if ($pdo) $pdo->commit();
            return ['source_rows'=>$input['source_rows'], 'unique_ids'=>count($records), 'already_covered'=>$plan['covered'],
                'insert'=>count($plan['insert']), 'backfill'=>count($plan['backfill']), 'conflicts'=>$plan['conflicts'],
                'inserted'=>$apply && $plan['conflicts'] === 0 ? count($plan['insert']) : 0,
                'backfilled'=>$apply && $plan['conflicts'] === 0 ? count($plan['backfill']) : 0,
                'verified'=>$verified, 'complete'=>$verified===count($records)];
        } catch (Throwable $error) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
