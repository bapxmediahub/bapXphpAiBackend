<?php
namespace App\Controllers;

use App\Services\DatabaseService;
use App\Services\SecretService;

final class RemoteDbController {
    public function __construct() {}

    private function requirePassword(array $input): void {
        $secrets = (new SecretService())->all();
        $expected = trim((string)($secrets['remote_db_password'] ?? ''));
        if ($expected === '') return;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $given = $input['password'] ?? '';
        if ($given === '' && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $given = trim($m[1]);
        }
        if (!hash_equals($expected, $given)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: invalid or missing remote_db_password.']);
            exit;
        }
    }

    public function __invoke() {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $this->requirePassword($input);

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
        $collection = preg_replace('/[^a-z_]/', '', (string)($input['collection'] ?? ''));
        if ($collection === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Collection is required']);
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
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['error' => 'Remote mutation failed: ' . $e->getMessage()]);
        }
    }
}
