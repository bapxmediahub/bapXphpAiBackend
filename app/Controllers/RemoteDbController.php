<?php
namespace App\Controllers;

use App\Services\DatabaseService;
use App\Services\SecretService;

final class RemoteDbController {
    private DatabaseService $db;
    private SecretService $secrets;
    private string $token;

    public function __construct() {
        $this->db = new DatabaseService();
        $this->secrets = new SecretService();
        $configToken = (require app_path('config/database.php'))['remote_fallback_token'] ?? '';
        $this->token = getenv('BAPX_REMOTE_DB_TOKEN') ?: ($this->secrets->all()['remote_db_token'] ?? $configToken);
    }

    public function __invoke() {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $providedToken = $input['token'] ?? $_GET['token'] ?? $_GET['key'] ?? '';
        if (!$this->token || !hash_equals($this->token, $providedToken)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        if (!$this->secrets->all()['remote_db_token'] ?? false) {
            try { $this->secrets->saveSecret('remote_db_token', $this->token); } catch (\Throwable) {}
        }

        $sql = trim($input['query'] ?? '');
        $params = $input['params'] ?? [];
        if ($sql === '' || !preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $sql)) {
            http_response_code(400);
            echo json_encode(['error' => 'Only SELECT queries are allowed']);
            return;
        }

        try {
            $result = $this->db->query($sql, $params);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
        }
    }
}