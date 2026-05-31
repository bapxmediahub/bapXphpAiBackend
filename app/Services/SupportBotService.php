<?php
namespace App\Services;

final class SupportBotService {
    public function __construct(
        private SecretService $secrets = new SecretService(),
        private JsonStoreService $store = new JsonStoreService(),
        private AgentContextService $agentContext = new AgentContextService()
    ) {}

    public function answer(string $message, ?array $user): array {
        $message = trim($message);
        if ($message === '') throw new \InvalidArgumentException('Message is required.');
        $context = $this->customerContext($user);
        $reply = $this->googleReply($message, $context) ?? $this->fallbackReply($message, $context);
        $ticket = $this->store->upsert('support_tickets', [
            'id' => bin2hex(random_bytes(8)),
            'customer_email' => $user['email'] ?? '',
            'customer_name' => $user['name'] ?? '',
            'message' => $message,
            'reply' => $reply,
            'status' => 'answered',
            'created_at' => date('c'),
        ]);
        return ['reply' => $reply, 'ticket_id' => $ticket['id']];
    }

    private function customerContext(?array $user): array {
        if (empty($user['email'])) return ['signed_in' => false, 'orders' => [], 'sessions' => []];
        return ['signed_in' => true] + $this->agentContext->forUserEmail((string)$user['email']);
    }

    private function googleReply(string $message, array $context): ?string {
        $secrets = $this->secrets->all();
        $key = trim((string)($secrets['support_bot_google_api_key'] ?? ''));
        $model = trim((string)($secrets['support_bot_model'] ?? 'gemma-4-31b-it')) ?: 'gemma-4-31b-it';
        if ($key === '' || !function_exists('curl_init')) return null;
        $prompt = "You are Sri Panchami Spiritual support. Answer only about this PHP ecommerce, product orders, wallet recharge, astrologer call/message sessions, product details, and customer account data provided. Do not invent order status. If data is missing, ask the customer to contact support. Customer context JSON: "
            . json_encode($context, JSON_UNESCAPED_SLASHES)
            . "\nCustomer question: " . $message;
        $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]], JSON_UNESCAPED_SLASHES);
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            CURLOPT_TIMEOUT => 12,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 300 || !$body) return null;
        $json = json_decode($body, true) ?: [];
        return trim((string)($json['candidates'][0]['content']['parts'][0]['text'] ?? '')) ?: null;
    }

    private function fallbackReply(string $message, array $context): string {
        if (!$context['signed_in']) {
            return 'Please sign in to ask about your orders, wallet balance, or astrologer sessions. I can still help you browse products or explain call and message consultations.';
        }
        if (str_contains(strtolower($message), 'order')) {
            return empty($context['orders']) ? 'I could not find orders in your account yet.' : 'I found your recent order data in the account panel. Open My Orders for full delivery address, status, shipped time, and review options.';
        }
        if (str_contains(strtolower($message), 'talk') || str_contains(strtolower($message), 'session') || str_contains(strtolower($message), 'astrologer')) {
            return empty($context['sessions']) ? 'I could not find astrologer sessions in your account yet.' : 'I found recent astrologer session records. Open My Sessions to see who you contacted, session type, credits spent, and review options.';
        }
        return 'I can help with product orders, wallet recharge, astrologer call/message sessions, reviews, and account history. Please ask one specific question.';
    }
}
