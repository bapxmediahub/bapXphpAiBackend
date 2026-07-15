<?php
namespace App\Services;

final class SupportBotService {
    public function __construct(
        private SecretService $secrets = new SecretService(),
        private DatabaseService $store = new DatabaseService(),
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
        $result = ['reply' => $reply, 'ticket_id' => null, 'memory' => 'browser_session'];
        $email = !empty($user['email']) ? (string)$user['email'] : '';
        $escalated = $this->shouldEscalate($message, $reply, $user);
        if ($escalated && $email !== '') {
            try {
                $ticket = (new SupportTicketService())->create($email, $message, 'escalated from support bot');
                $result['ticket_id'] = $ticket['id'];
                $result['reply'] .= ' I have created a support ticket to get a human to review your request.';
            } catch (\Throwable) {}
        }
        $actions = $this->extractActions($reply);
        if ($actions !== []) $result['actions'] = $actions;
        return $result;
    }

    private function customerContext(?array $user): array {
        $cart = array_map(fn($item) => [
            'slug' => $item['slug'] ?? '',
            'qty' => (int)($item['qty'] ?? 0),
            'name' => $item['name'] ?? '',
        ], array_values($_SESSION['cart'] ?? []));
        $base = empty($user['email'])
            ? $this->agentContext->forUserEmail('')
            : $this->agentContext->forUserEmail((string)$user['email']);
        return ['signed_in' => !empty($user['email']), 'cart' => $cart] + $base;
    }

    private function googleReply(string $message, array $context): ?string {
        $secrets = $this->secrets->all();
        $key = trim((string)(getenv('AGENT_API_KEY') ?: getenv('SUPPORT_BOT_GOOGLE_API_KEY') ?: ($secrets['agent_api_key'] ?? $secrets['support_bot_google_api_key'] ?? '')));
        $model = trim((string)(getenv('AGENT_MODEL') ?: getenv('SUPPORT_BOT_MODEL') ?: ($secrets['agent_model'] ?? $secrets['support_bot_model'] ?? 'gemma-4-31b-it'))) ?: 'gemma-4-31b-it';
        if ($key === '' || !function_exists('curl_init')) return null;
        $prompt = "You are Sri Panchami Spiritual support bot.\n"
            . "Return only the final customer-facing answer. Do not include reasoning, analysis, markdown bullets, code, tool calls, or hidden thoughts.\n"
            . "Use only this JSON context for the signed-in customer and public site links. Never mention, infer, or access other users' data. If data is missing, ask the customer to use the contact form.\n"
            . "Allowed help: product, cart, checkout, delivery address, order, consultant booking, and navigation details from the JSON.\n"
            . "When useful, include one exact internal path from site.pages or a matching product path (e.g., /shop, /cart, /checkout, /product/slug, /consult). Mention the path in the reply so the UI can show a navigation button. Never invent admin paths, external URLs, or claim that an action already happened.\n"
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
        if ($text === '') $text = 'I can help with products, orders, delivery addresses, payments, and consultant bookings. Please ask one specific question.';
        return strlen($text) > 900 ? substr($text, 0, 897) . '...' : $text;
    }

    private function looksInternal(string $reply): bool {
        return (bool)preg_match('/\b(signed_in|customer context|context json|site\.pages|wallet_transactions|support_scope|generationconfig|tool call|role:|constraint|the user said|the bot should|the bot needs|allowed scope)\b/i', $reply);
    }

    private function fallbackReply(string $message, array $context): string {
        $lower = strtolower($message);
        if (!$context['signed_in']) {
            if ($this->isPrivateAccountQuestion($lower)) {
                return 'Please sign in to ask about your personal orders or consultant bookings. I can still explain products, checkout, delivery, and booking.';
            }
            return $this->publicGuestReply($lower, $context);
        }
        if (preg_match('/^(hi|hello|hey|vanakkam|namaste)\b/i', trim($message))) {
            return 'Hello. I can help with products, checkout, saved addresses, orders, and consultant bookings.';
        }
        if (str_contains($lower, 'order')) {
            return empty($context['orders']) ? 'I could not find orders in your account yet.' : 'I found your recent order data in the account panel. Open My Orders for full delivery address, status, shipped time, and review options.';
        }
        if (str_contains($lower, 'talk') || str_contains($lower, 'session') || str_contains($lower, 'astrologer')) {
            return empty($context['sessions']) ? 'I could not find astrologer sessions in your account yet.' : 'I found recent astrologer session records. Open My Sessions to see who you contacted, session type, credits spent, and review options.';
        }
        return 'I can help with product orders, delivery addresses, consultant bookings, reviews, and account history. Please ask one specific question.';
    }

    private function publicGuestReply(string $message, array $context): string {
        $site = $context['site'] ?? [];
        $pages = $site['pages'] ?? [];
        $products = array_slice($site['products'] ?? [], 0, 5);
        if (preg_match('/\b(hi|hello|hey|vanakkam|namaste)\b/i', $message)) {
            return 'Hello. I can help you browse spiritual products, place an order, manage delivery addresses, or request a consultant appointment.';
        }
        if (preg_match('/\b(product|available|shop|buy|item|pendant|jewelry|jewellery)\b/i', $message)) {
            $names = array_filter(array_map(fn($p) => trim((string)($p['name'] ?? '')), $products));
            $list = $names ? implode(', ', $names) : 'sacred emblems and spiritual jewelry';
            return 'Available products include ' . $list . '. Open ' . ($pages['shop'] ?? '/shop') . ' to browse all products, or add an item to cart from its product page.';
        }
        if (preg_match('/\b(service|consult|booking|book|astrology|call|message|temple)\b/i', $message)) {
            return 'Available services include spiritual product sales, scheduled consultant appointments, and temple guidance. Open ' . ($pages['consult'] ?? '/consult') . ' to request an appointment.';
        }
        if (preg_match('/\b(recharge|wallet|credit|payment)\b/i', $message)) {
            return 'Product payments are completed securely during checkout. Sign in to reuse saved delivery addresses and view confirmed orders.';
        }
        return 'I can help with products, checkout, delivery addresses, temple guidance, and consultant bookings. For personal order or booking history, please sign in first.';
    }

    private function isPrivateAccountQuestion(string $message): bool {
        return (bool)preg_match('/\b(my order|my booking|my session|track|delivery|shipped|history|past session|previous session)\b/i', $message);
    }

    private function shouldEscalate(string $message, string $reply, ?array $user): bool {
        if (empty($user['email'])) return false;
        if (preg_match('/\b(human|agent|escalate|talk to (a|someone)|speak to|contact support)\b/i', $message)) return true;
        if (preg_match('/\b(complaint|refund|cancel|cancellation|return|wrong|broken|not working|issue|problem)\b/i', $message)) return true;
        if (str_contains($reply, 'contact form')) return true;
        return false;
    }

    private function extractActions(string $reply): array {
        preg_match_all('/\/(?:shop|cart|checkout|consult|temples|contact|blog(?:\/[a-z0-9-]+|\/category\/[a-z0-9-]+)?|product\/[a-z0-9-]+|account\/dashboard(?:\/orders|\/sessions|\/install)?)(?=[\s.,)\/  ]|$)/i', $reply, $matches);
        $seen = [];
        $actions = [];
        foreach ($matches[0] as $path) {
            $path = strtolower($path);
            if (in_array($path, $seen, true)) continue;
            $seen[] = $path;
            $label = match (true) {
                $path === '/shop' => 'View Shop',
                $path === '/cart' => 'View Cart',
                $path === '/checkout' => 'Go to Checkout',
                $path === '/consult' => 'View Consultants',
                $path === '/contact' => 'Contact Us',
                $path === '/temples' => 'View Temples',
                $path === '/blog' => 'Read Blog',
                default => 'Open ' . trim(preg_replace('/^\/+/', '', str_replace(['-', '/'], ' ', $path)))
            };
            $actions[] = ['type' => 'navigate', 'label' => $label, 'path' => $path];
        }
        return $actions;
    }
}
