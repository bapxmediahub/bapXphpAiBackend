<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\PublicAgentDiscoveryService;

/** Public metadata only; no actor, credential or cart is read or created. */
final class PublicAgentDiscoveryController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        if($_GET!==[]) {
            http_response_code(400);
            echo '{"error":"Discovery takes no query parameters."}';
            return;
        }
        try {
            $document=(new PublicAgentDiscoveryService())->document();
            http_response_code(200);
            echo json_encode($document,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch(\Throwable) {
            http_response_code(503);
            echo '{"error":"Public tool discovery is temporarily unavailable."}';
        }
    }
}
