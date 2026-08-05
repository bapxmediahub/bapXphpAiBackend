<?php
namespace App\Controllers;

use App\Services\DatabaseService;
use App\Services\SecretService;

final class RemoteDbController {
    private DatabaseService $store;

    public function __construct() {
        // The HTTP bridge must terminate at hosted MySQL. It must never fall back to
        // its own /remotedb URL when direct MySQL is unavailable.
        $this->store = new DatabaseService(true);
    }

    /**
     * The endpoint authenticates with the MySQL password from config, not a separate
     * invented token. Anyone entitled to query the database already holds it, and it
     * cannot drift out of sync with a second secret.
     *
     * Fails closed: an unset password rejects every request instead of allowing all of
     * them, which is how the endpoint came to accept unauthenticated queries.
     */
    private function requirePassword(array $input): void {
        $config = require app_path('config/database.php');
        $expected = trim((string)($config['pass'] ?? ''));
        if ($expected === '') {
            http_response_code(503);
            echo json_encode(['error' => 'Remote database access is not configured.']);
            exit;
        }
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $given = $input['password'] ?? '';
        if ($given === '' && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $given = trim($m[1]);
        }
        if (!hash_equals($expected, $given)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: invalid or missing database password.']);
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
            $result = $this->store->query($sql, $params);
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
            $store = $this->store;
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
