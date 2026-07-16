<?php
namespace App\Controllers;

use App\Services\SecretService;

final class BrowserAgentController extends BaseController {
    public function search(): void {
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $query = trim((string)($input["query"] ?? ""));
        $engine = trim((string)($input["engine"] ?? "ddg"));
        if ($query === "") {
            $this->jsonResponse(["success" => false, "error" => "Query is required"], 400);
            return;
        }
        $session = $this->browserSession();
        $results = $session->search($query, $engine);
        $this->jsonResponse(["success" => true, "results" => $results]);
    }

    public function open(): void {
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $url = trim((string)($input["url"] ?? ""));
        if ($url === "") {
            $this->jsonResponse(["success" => false, "error" => "URL is required"], 400);
            return;
        }
        $session = $this->browserSession();
        $snapshot = $session->open($url);
        $this->jsonResponse(["success" => true, "snapshot" => $snapshot]);
    }

    public function click(): void {
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $selector = trim((string)($input["selector"] ?? ""));
        if ($selector === "") {
            $this->jsonResponse(["success" => false, "error" => "Selector is required"], 400);
            return;
        }
        $session = $this->browserSession();
        $snapshot = $session->click($selector);
        $this->jsonResponse(["success" => true, "snapshot" => $snapshot]);
    }

    public function fill(): void {
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $selector = trim((string)($input["selector"] ?? ""));
        $text = (string)($input["text"] ?? "");
        if ($selector === "" || $text === "") {
            $this->jsonResponse(["success" => false, "error" => "Selector and text are required"], 400);
            return;
        }
        $session = $this->browserSession();
        $snapshot = $session->fill($selector, $text);
        $this->jsonResponse(["success" => true, "snapshot" => $snapshot]);
    }

    public function snapshot(): void {
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $maxElements = (int)($input["max_elements"] ?? 500);
        $maxDepth = (int)($input["max_depth"] ?? 10);
        $session = $this->browserSession();
        $yaml = $session->snapshot($maxElements, $maxDepth);
        $this->jsonResponse(["success" => true, "yaml" => $yaml]);
    }

    public function links(): void {
        $session = $this->browserSession();
        $links = $session->extractLinks();
        $this->jsonResponse(["success" => true, "links" => $links]);
    }

    public function forms(): void {
        $session = $this->browserSession();
        $forms = $session->extractForms();
        $this->jsonResponse(["success" => true, "forms" => $forms]);
    }

    public function captcha(): void {
        $session = $this->browserSession();
        $detected = $session->detectCaptcha();
        $this->jsonResponse(["success" => true, "detected" => $detected]);
    }

    public function smoke(): void {
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $url = trim((string)($input["url"] ?? ""));
        if ($url === "") {
            $this->jsonResponse(["success" => false, "error" => "URL is required"], 400);
            return;
        }
        $session = $this->browserSession();
        $result = $session->smoke($url);
        $this->jsonResponse(["success" => true, "result" => $result]);
    }

    public function cdp(): void {
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $wsUrl = trim((string)($input["ws_url"] ?? ""));
        $method = trim((string)($input["method"] ?? "version"));
        $params = $input["params"] ?? [];
        
        if ($wsUrl === "") {
            $this->jsonResponse(["success" => false, "error" => "ws_url is required"], 400);
            return;
        }
        
        $session = $this->browserSession();
        $result = $session->cdp($wsUrl, $method, $params);
        $this->jsonResponse(["success" => true, "result" => $result]);
    }

    public function cdpLaunch(): void {
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $port = (int)($input["port"] ?? 9222);
        $headless = $input["headless"] ?? true;
        $noSandbox = $input["no_sandbox"] ?? true;
        
        $session = $this->browserSession();
        $result = $session->cdpLaunch($port, $headless, $noSandbox);
        $this->jsonResponse(["success" => true, "result" => $result]);
    }

    public function status(): void {
        $session = $this->browserSession();
        $status = $session->getStatus();
        $this->jsonResponse(["success" => true, "status" => $status]);
    }

    private function browserSession(): \App\Services\BrowserSession {
        return new \App\Services\BrowserSession();
    }
}
