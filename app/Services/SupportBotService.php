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
        $reply = !$context['signed_in'] ? $this->fallbackReply($message, $context) : null;
        if ($reply === null) {
            $aiReply = $this->googleReply($message, $context);
            if ($aiReply !== null) {
                $candidate = $this->cleanReply($aiReply);
                if (!$this->looksInternal($candidate)) $reply = $candidate;
            }
        }
        $reply ??= $this->fallbackReply($message, $context);
        return ['reply' => $reply, 'ticket_id' => null, 'memory' => 'browser_session'];
    }

    private function customerContext(?array $user): array {
        if (empty($user['email'])) return ['signed_in' => false] + $this->agentContext->forUserEmail('');
        return ['signed_in' => true] + $this->agentContext->forUserEmail((string)$user['email']);
    }

    private function googleReply(string $message, array $context): ?string {
        $secrets = $this->secrets->all();
        $key = trim((string)(getenv('SUPPORT_BOT_GOOGLE_API_KEY') ?: ($secrets['support_bot_google_api_key'] ?? '')));
        $model = trim((string)(getenv('SUPPORT_BOT_MODEL') ?: ($secrets['support_bot_model'] ?? 'gemma-4-31b-it'))) ?: 'gemma-4-31b-it';
        if ($key === '' || !function_exists('curl_init')) return null;
        $prompt = "You are Sri Panchami Spiritual support bot.\n"
            . "Return only the final customer-facing answer. Do not include reasoning, analysis, markdown bullets, code, tool calls, or hidden thoughts.\n"
            . "Use only this JSON context for the signed-in customer and public site links. Never mention, infer, or access other users' data. If data is missing, ask the customer to use the contact form.\n"
            . "Allowed help: product links, cart/recharge/account links, order/session/wallet details from the JSON, and astrology booking through the contact form.\n"
            . "Customer context JSON: "
            . json_encode($context, JSON_UNESCAPED_SLASHES)
            . "\nCustomer question: " . $message
            . "\nSupport reply:";
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 220],
        ], JSON_UNESCAPED_SLASHES);
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
        if ($status >= 300 || !$body) return null;
        $json = json_decode($body, true) ?: [];
        return trim((string)($json['candidates'][0]['content']['parts'][0]['text'] ?? '')) ?: null;
    }

    private function cleanReply(string $reply): string {
        $reply = preg_replace('/<thought\b[^>]*>.*?<\/thought>/is', '', $reply) ?? $reply;
        $lines = array_filter(array_map('trim', preg_split('/\R/', $reply) ?: []));
        $clean = [];
        foreach ($lines as $line) {
            $line = preg_replace('/^\s*[\*\-]\s*/', '', $line) ?? $line;
            if (preg_match('/^(role|constraint|customer context|customer question|the customer|i need|greeting|list the|maintain a|answer only|support reply)\b/i', $line)) continue;
            $line = trim($line, " \t\n\r\0\x0B`*");
            if ($line !== '') $clean[] = $line;
        }
        $text = trim(implode(' ', $clean));
        if (preg_match_all('/"([^"]{20,700})"/', $text, $matches) && !empty($matches[1])) {
            $text = end($matches[1]);
        }
        if ($text === '') $text = 'I can help with products, wallet recharge, astrologer call/message sessions, orders, and booking requests through the contact form. Please ask one specific question.';
        return strlen($text) > 900 ? substr($text, 0, 897) . '...' : $text;
    }

    private function looksInternal(string $reply): bool {
        return (bool)preg_match('/\b(signed_in|customer context|context json|site\.pages|wallet_transactions|support_scope|generationconfig|tool call|role:|constraint|the user said|the bot should|the bot needs|allowed scope)\b/i', $reply);
    }

    private function fallbackReply(string $message, array $context): string {
        $lower = strtolower($message);
        if (!$context['signed_in']) {
            if ($this->isPrivateAccountQuestion($lower)) {
                return 'Please sign in to ask about your personal orders, wallet balance, or past astrologer sessions. I can still explain public products, services, recharge, and consultation booking.';
            }
            return $this->publicGuestReply($lower, $context);
        }
        if (preg_match('/^(hi|hello|hey|vanakkam|namaste)\b/i', trim($message))) {
            return 'Hello. I can help with product links, cart or recharge guidance, astrology call/message booking through the contact form, and your own order, wallet, or session history.';
        }
        if (str_contains($lower, 'order')) {
            return empty($context['orders']) ? 'I could not find orders in your account yet.' : 'I found your recent order data in the account panel. Open My Orders for full delivery address, status, shipped time, and review options.';
        }
        if (str_contains($lower, 'talk') || str_contains($lower, 'session') || str_contains($lower, 'astrologer')) {
            return empty($context['sessions']) ? 'I could not find astrologer sessions in your account yet.' : 'I found recent astrologer session records. Open My Sessions to see who you contacted, session type, credits spent, and review options.';
        }
        return 'I can help with product orders, wallet recharge, astrologer call/message sessions, reviews, and account history. Please ask one specific question.';
    }

    private function publicGuestReply(string $message, array $context): string {
        $site = $context['site'] ?? [];
        $pages = $site['pages'] ?? [];
        $products = array_slice($site['products'] ?? [], 0, 5);
        if (preg_match('/\b(hi|hello|hey|vanakkam|namaste)\b/i', $message)) {
            return 'Hello. I can help you browse spiritual products, explain remote astrology call/message services, guide wallet recharge, or send you to the consultation booking form.';
        }
        if (preg_match('/\b(product|available|shop|buy|item|pendant|jewelry|jewellery)\b/i', $message)) {
            $names = array_filter(array_map(fn($p) => trim((string)($p['name'] ?? '')), $products));
            $list = $names ? implode(', ', $names) : 'sacred emblems and spiritual jewelry';
            return 'Available products include ' . $list . '. Open ' . ($pages['shop'] ?? '/shop') . ' to browse all products, or add an item to cart from its product page.';
        }
        if (preg_match('/\b(service|consult|booking|book|astrology|call|message|temple)\b/i', $message)) {
            return 'Available services include spiritual product sales, remote astrology call/message consultation requests, wallet recharge for sessions, and temple guidance. Use ' . ($pages['booking_contact_form'] ?? '/contact?subject=astrology#contact-form') . ' to request a consultation booking.';
        }
        if (preg_match('/\b(recharge|wallet|credit|payment)\b/i', $message)) {
            return 'Wallet recharge is available from ' . ($pages['recharge'] ?? '/recharge') . '. You may need to sign in before payment so credits are added to your account.';
        }
        return 'I can help with available products, astrology call/message services, recharge, temple guidance, and consultation booking. For personal order or session history, please sign in first.';
    }

    private function isPrivateAccountQuestion(string $message): bool {
        return (bool)preg_match('/\b(my order|my booking|my session|my wallet|my balance|track|delivery|shipped|spent|history|past session|previous session)\b/i', $message);
    }

}
