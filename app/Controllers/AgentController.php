<?php
namespace App\Controllers;

use App\Services\SecretService;
use App\Services\DatabaseService;

class AgentController extends BaseController {
    private function loadAgentConfig(): array {
        $config = ['name' => 'Agent', 'description' => 'AI assistant for the site'];
        $path = app_path('config/agent.yml');
        if (is_file($path)) {
            $yaml = @file_get_contents($path);
            if ($yaml !== false && $yaml !== '') {
                foreach (explode("\n", $yaml) as $line) {
                    if (preg_match('/^\s*(\w+):\s*(.+)$/', $line, $m)) {
                        $config[$m[1]] = trim($m[2], " \"'");
                    }
                }
            }
        }
        return $config;
    }

    public function ask(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $message = trim((string)($input['message'] ?? ''));
        $source = trim((string)($input['source'] ?? 'cli'));
        if ($message === '') {$this->jsonResponse(['error' => 'Message is required'], 400); return;}
        $agentConfig = $this->loadAgentConfig();
        try {
            $secrets = new SecretService();
            $db = new DatabaseService();
            $mc = $secrets->getModelConfig();
            $all = $secrets->all();
            $agentName = trim((string)($all['agent_name'] ?? $agentConfig['name']));
            $siteName = trim((string)($all['seo_site_name'] ?? 'the site'));
            $userCount = count($db->read('users'));
            $orderCount = count($db->read('orders'));
            $productCount = count($db->read('products'));
            $astrologerCount = count($db->read('astrologers'));
            $appointmentCount = count($db->read('appointments'));
            $ticketCount = count($db->read('support_tickets'));
            $revenue = array_sum(array_column($db->read('orders'), 'total'));
            $context = "{$siteName} site data:\n- Users: {$userCount}\n- Orders: {$orderCount}\n- Products: {$productCount}\n- Astrologers: {$astrologerCount}\n- Appointments: {$appointmentCount}\n- Support tickets: {$ticketCount}\n- Revenue: ₹" . number_format($revenue, 2);
            if (!empty($mc['apiKey'])) {
                $answer = $this->callAi($mc, $agentName, $siteName, $message, $context);
            } else {
                $answer = "AI not configured. Go to Admin → Integrations and set endpoint, agent_api_key, and agent_model.";
            }
            $this->jsonResponse(['answer' => $answer, 'model' => $mc['model'] ?? 'unknown', 'source' => $source, 'agent_name' => $agentName]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => $agentName . ' error: ' . $e->getMessage()], 500);
        }
    }
    private function callAi(array $mc, string $agentName, string $siteName, string $message, string $context): string {
        $endpoint = rtrim($mc['endpoint'] ?? 'https://api.openai.com/v1', '/');
        $model = $mc['model'] ?? 'gemma-4-31b-it';
        $key = $mc['apiKey'] ?? '';
        $prompt = "You are {$agentName}, the AI assistant for {$siteName}. Answer concisely.\n\n{$context}\n\nUser: {$message}";
        $provider = $mc['provider'] ?? 'openai';
        if ($provider === 'google') {
            $url = $endpoint . '/' . rawurlencode($model) . ':generateContent';
            $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1024]]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key], CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status !== 200 || $body === false) return "API error (HTTP {$status}). Check model config.";
            $result = json_decode($body, true);
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No response.';
        }
        $url = $endpoint . '/chat/completions';
        $payload = json_encode(['model' => $model, 'messages' => [['role' => 'system', 'content' => $context], ['role' => 'user', 'content' => $message]], 'max_tokens' => 1024]);
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
        if ($provider === 'anthropic') {
            $headers[] = 'x-api-key: ' . $key;
            $headers[] = 'anthropic-version: 2023-06-01';
        } else {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200 || $body === false) return "API error (HTTP {$status}). Check your endpoint/key/model in Admin → Integrations.";
        $result = json_decode($body, true);
        return $result['choices'][0]['message']['content'] ?? 'No response.';
    }
}
