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
            $mc = $secrets->getModelConfig();
            $userCount = count($db->read('users'));
            $orderCount = count($db->read('orders'));
            $productCount = count($db->read('products'));
            $astrologerCount = count($db->read('astrologers'));
            $appointmentCount = count($db->read('appointments'));
            $ticketCount = count($db->read('support_tickets'));
            $revenue = array_sum(array_column($db->read('orders'), 'total'));
            $context = "Sri Panchami Spiritual site data:\n- Users: {$userCount}\n- Orders: {$orderCount}\n- Products: {$productCount}\n- Astrologers: {$astrologerCount}\n- Appointments: {$appointmentCount}\n- Support tickets: {$ticketCount}\n- Revenue: ₹" . number_format($revenue, 2);
            if (!empty($mc['apiKey'])) {
                $answer = $this->callAi($mc, $message, $context);
            } else {
                $answer = "AI not configured. Go to Admin → Integrations and set endpoint, api_key, and model.";
            }
            $this->jsonResponse(['answer' => $answer, 'model' => $mc['model'] ?? 'unknown', 'source' => $source]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Maya error: ' . $e->getMessage()], 500);
        }
    }
    private function callAi(array $mc, string $message, string $context): string {
        $endpoint = rtrim($mc['endpoint'] ?? 'https://api.openai.com/v1', '/');
        $model = $mc['model'] ?? 'gpt-4o';
        $key = $mc['apiKey'] ?? '';
        $prompt = "You are Maya, the AI assistant for Sri Panchami Spiritual. Answer concisely.\n\n{$context}\n\nUser: {$message}";
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
