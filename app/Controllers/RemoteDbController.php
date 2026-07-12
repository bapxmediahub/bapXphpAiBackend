<?php
namespace App\Controllers;

use App\Services\DatabaseService;
use App\Services\SecretService;

final class RemoteDbController {
    public function __construct() {}

    public function __invoke() {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $action = strtolower(trim((string)($input['action'] ?? 'query')));
        if ($action !== 'query') {
            $this->mutate($action, $input);
            return;
        }

        $sql = trim($input['query'] ?? '');
        $params = $input['params'] ?? [];
        if ($sql === '' || !preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $sql)) {
            http_response_code(400);
            echo json_encode(['error' => 'Only read queries are allowed']);
            return;
        }

        try {
            $db = new DatabaseService();
            $result = $db->query($sql, $params);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed']);
        }
    }

    private function mutate(string $action, array $input): void {
        $configured = trim((string)((new SecretService())->all()['remote_db_token'] ?? ''));
        $provided = trim((string)($_SERVER['HTTP_X_BAPX_REMOTE_TOKEN'] ?? $input['token'] ?? ''));
        if ($configured === '' || $provided === '' || !hash_equals($configured, $provided)) {
            http_response_code(403);
            echo json_encode(['error' => 'Remote mutation is not authorized']);
            return;
        }
        $collection = preg_replace('/[^a-z_]/', '', (string)($input['collection'] ?? ''));
        $schema = require app_path('storage/schema/collections.php');
        if ($collection === '' || $collection === 'secrets' || !isset($schema['collections'][$collection])) {
            http_response_code(422);
            echo json_encode(['error' => 'Collection is not available for remote mutation']);
            return;
        }
        try {
            $store = new DatabaseService();
            if ($action === 'upsert') {
                $record = $input['record'] ?? null;
                if (!is_array($record) || empty($record['id'])) throw new \InvalidArgumentException('Record id is required.');
                $saved = $store->upsert($collection, $record);
                http_response_code(200);
                echo json_encode(['success' => true, 'record' => $saved]);
                return;
            }
            if ($action === 'delete') {
                $id = trim((string)($input['id'] ?? ''));
                if ($id === '') throw new \InvalidArgumentException('Record id is required.');
                $store->delete($collection, $id);
                http_response_code(200);
                echo json_encode(['success' => true]);
                return;
            }
            if ($action === 'replace') {
                $records = $input['records'] ?? null;
                if (!is_array($records)) throw new \InvalidArgumentException('Records are required.');
                $store->write($collection, $records);
                http_response_code(200);
                echo json_encode(['success' => true, 'count' => count($records)]);
                return;
            }
            throw new \InvalidArgumentException('Unsupported mutation action.');
        } catch (\Throwable) {
            http_response_code(422);
            echo json_encode(['error' => 'Remote mutation failed']);
        }
    }
}
