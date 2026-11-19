<?php
namespace App\Services;
final class ResourceService {
    public function __construct(private string $collection, private DatabaseService $store = new DatabaseService()) {}
    public function all(): array { return $this->store->read($this->collection); }
    public static function listOptions(array $query): array {
        $search = is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, 150) : '';
        $pageSize = filter_var($query['per_page'] ?? 25, FILTER_VALIDATE_INT);
        $pageSize = in_array($pageSize, [10, 20, 25, 50, 100, 200], true) ? $pageSize : 25;
        $page = max(1, (int)filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT));
        $sort = ($query['sort'] ?? '') === 'oldest' ? 'oldest' : 'newest';
        return compact('search', 'pageSize', 'page', 'sort');
    }

    public static function pageBounds(array $options, int $count): array {
        $pages = max(1, (int)ceil($count / $options['pageSize']));
        $page = min($options['page'], $pages);
        $offset = ($page - 1) * $options['pageSize'];
        return compact('page', 'pages', 'offset', 'count') + $options;
    }

    public function adminPage(array $query): array {
        $fields = match ($this->collection) {
            'orders' => ['customer_email','customer_name','status','created_at','invoice_number','total'],
            'appointments' => ['customer_email','email','name','astrologer_slug','mode','status','created_at','preferred_date','preferred_time'],
            default => throw new \InvalidArgumentException('Unsupported record list'),
        };
        $options = self::listOptions($query);
        // Match the list's meaningful-record check before counting or paging.
        // A stored numeric zero, false, or array is a value; an empty string or null is not.
        $where = " WHERE COALESCE(JSON_EXTRACT(JSON_REMOVE(_data, '$.id'), '$.*'), '[]') NOT REGEXP ?";
        // The wildcard returns the top-level values as an array on both database engines.
        $params = ['^\[[[:space:]]*((null|"")[[:space:]]*(,[[:space:]]*(null|"")[[:space:]]*)*)?\]$'];
        if ($options['search'] !== '') {
            $columns = ['id'];
            foreach ($fields as $field) $columns[] = "JSON_UNQUOTE(JSON_EXTRACT(_data, '$.$field'))";
            // Preserve the labels staff see in the list as well as the stored codes.
            $columns[] = "REPLACE(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.status')), 'active'), '_', ' ')";
            if ($this->collection === 'appointments') {
                $columns[] = "REPLACE(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.mode')), 'booking'), '_', ' ')";
            } else {
                $columns[] = "CONCAT('₹', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(_data, '$.total')), '0'))";
                foreach (['tax_review_required' => 'Tax review required', 'fulfillment_review_required' => 'Fulfillment review required'] as $field => $label) {
                    $flag = "JSON_EXTRACT(_data, '$.$field')";
                    // Match !empty() for JSON booleans and older string or numeric flags.
                    $columns[] = "CASE JSON_TYPE($flag)
                        WHEN 'STRING' THEN IF(JSON_UNQUOTE($flag) NOT IN ('', '0'), '$label', NULL)
                        WHEN 'BOOLEAN' THEN IF(JSON_UNQUOTE($flag) = 'true', '$label', NULL)
                        WHEN 'NULL' THEN NULL
                        ELSE IF(CAST($flag AS DECIMAL(65,20)) <> 0, '$label', NULL) END";
                }
            }
            $where .= " AND LOCATE(LOWER(?), LOWER(CONCAT_WS(' ', ".implode(', ', $columns)."))) > 0";
            $params[] = $options['search'];
        }
        $table = $this->collection;
        $count = (int)($this->store->query("SELECT COUNT(*) AS total FROM $table$where", $params)[0]['total'] ?? 0);
        $paging = self::pageBounds($options, $count);
        $direction = $options['sort'] === 'oldest' ? 'ASC' : 'DESC';
        $rows = $this->store->query("SELECT id, _data FROM $table$where ORDER BY _created_at $direction, id ASC"
            ." LIMIT {$paging['pageSize']} OFFSET {$paging['offset']}", $params);
        $items = array_map(static fn(array $row): array => ['id' => $row['id']]
            + json_decode($row['_data'], true, flags: JSON_THROW_ON_ERROR), $rows);
        return compact('paging', 'items');
    }

    public function save(array $record): array {
        $hadId = isset($record['id']) && (string)$record['id'] !== '';
        $record['id'] ??= bin2hex(random_bytes(8));
        $record['slug'] ??= strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $record['name'] ?? $record['code'] ?? $record['id']), '-'));

        if ($this->collection === 'products') return $this->store->saveCatalogProduct($record, !$hadId);

        // Creating a record with a slug that already exists updates that record instead
        // of inserting a second one. Saves now travel over HTTP to the remote database
        // and take seconds, so a second click before the redirect used to produce two
        // rows with different generated ids and the same slug.
        if (!$hadId) {
            $slug = (string)($record['slug'] ?? '');
            if ($slug !== '') {
                $existing = $this->store->find($this->collection, $slug, 'slug');
                if ($existing && !empty($existing['id'])) {
                    $record['id'] = $existing['id'];
                }
            }
        }
        return $this->store->upsert($this->collection, $record);
    }
    public function delete(string $id): void { $this->store->delete($this->collection, $id); }
}
