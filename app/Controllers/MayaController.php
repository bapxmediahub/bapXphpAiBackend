<?php
namespace App\Controllers;

use App\Services\SecretService;
use App\Services\DatabaseService;

class MayaController extends BaseController {
    public function ask(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $message = trim((string)($input['message'] ?? ''));
        $source = trim((string)($input['source'] ?? 'cli'));
        if ($message === '') {$this->jsonResponse(['error' => 'Message is required'], 400); return;}
        try {
            $secrets = new SecretService();
            $db = new DatabaseService();
            $modelConfig = $secrets->getModelConfig();
            $userCount = count($db->read('users'));
            $orderCount = count($db->read('orders'));
            $productCount = count($db->read('products'));
            $astrologerCount = count($db->read('astrologers'));
            $appointmentCount = count($db->read('appointments'));
            $ticketCount = count($db->read('support_tickets'));
            $revenue = array_sum(array_column($db->read('orders'), 'total'));
            $context = "Sri Panchami Spiritual site data:\n- Users: {$userCount}\n- Orders: {$orderCount}\n- Products: {$productCount}\n- Astrologers: {$astrologerCount}\n- Appointments: {$appointmentCount}\n- Support tickets: {$ticketCount}\n- Revenue (sum of totals): ₹" . number_format($revenue, 2);
            if (!empty($modelConfig['apiKey'])) {
                $answer = $this->callAi($modelConfig, $message, $context);
            } else {
                $answer = "AI model not configured. CEO: go to Admin → Integrations and set google_api_key and model.";
            }
            $this->jsonResponse(['answer' => $answer, 'model' => $modelConfig['model'] ?? 'unknown', 'source' => $source]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Maya error: ' . $e->getMessage()], 500);
        }
    }
    private function callAi(array $config, string $message, string $context): string {
        $endpoint = $config['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/';
        $model = $config['model'] ?? 'gemini-2.5-flash';
        $key = $config['apiKey'] ?? '';
        $prompt = "You are Maya, the AI assistant for Sri Panchami Spiritual. Answer concisely.\n\n{$context}\n\nCEO: {$message}";
        $provider = $config['provider'] ?? 'google';
        if ($provider === 'google') {
            $url = rtrim($endpoint, '/') . '/' . rawurlencode($model) . ':generateContent';
            $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1024]]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key], CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status !== 200 || $body === false) return "API error (HTTP {$status}). Check model config.";
            $result = json_decode($body, true);
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No response from model.';
        }
        if ($provider === 'openai' || $provider === 'anthropic') {
            $url = rtrim($endpoint, '/') . '/chat/completions';
            $payload = json_encode(['model' => $model, 'messages' => [['role' => 'system', 'content' => $context], ['role' => 'user', 'content' => $message]], 'max_tokens' => 1024]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key], CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status !== 200 || $body === false) return "API error (HTTP {$status}). Check model config.";
            $result = json_decode($body, true);
            return $result['choices'][0]['message']['content'] ?? 'No response from model.';
        }
        return "Provider '{$provider}' not supported.";
    }
}
