<?php
namespace App\Controllers;

use App\Services\DatabaseService;

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

        $sql = trim($input['query'] ?? '');
        $params = $input['params'] ?? [];
        if ($sql === '' || !preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $sql)) {
            http_response_code(400);
            echo json_encode(['error' => 'Only SELECT queries are allowed']);
            return;
        }

        try {
            $db = new DatabaseService();
            $result = $db->query($sql, $params);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
        }
    }
}
