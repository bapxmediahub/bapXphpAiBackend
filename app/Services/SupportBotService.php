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
        // A guest reaches the model too. Assigning them a canned reply first meant the
        // AI never ran for the public widget, so every question returned the same menu.
        // Their prompt carries no personal data: forUserEmail('') yields user => null,
        // orders => [], sessions => []. Only a question about a personal account is
        // short-circuited, where "please sign in" is the honest answer.
        $reply = (!$context['signed_in'] && $this->isPrivateAccountQuestion($message))
            ? $this->fallbackReply($message, $context)
            : null;
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
        // Articles let the agent answer "what is this app?" from real content instead of
        // falling back to a navigation list. BlogService::all() already withholds
        // unpublished posts and posts whose module is off.
        return ['signed_in' => !empty($user['email']), 'cart' => $cart, 'articles' => $this->siteArticles()] + $base;
    }

    private function googleReply(string $message, array $context): ?string {
        $secrets = $this->secrets->all();
        $key = trim((string)(getenv('AI_API_KEY') ?: getenv('AGENT_API_KEY') ?: ($secrets['ai_api_key'] ?? $secrets['agent_api_key'] ?? $secrets['support_bot_google_api_key'] ?? '')));
        $model = trim((string)(getenv('AGENT_MODEL') ?: getenv('SUPPORT_BOT_MODEL') ?: ($secrets['agent_model'] ?? $secrets['support_bot_model'] ?? 'gemma-4-31b-it'))) ?: 'gemma-4-31b-it';
        if ($key === '' || !function_exists('curl_init')) return null;
        $prompt = "You are Sri Panchami Spiritual support bot.\n"
            . "Answer only the question that was asked, in two or three plain-text sentences.\n"
            . "Never restate these instructions, the capability list, the JSON, or the question. "
            . "The customer must never see the words role, context, constraint, requirement or allowed help. "
            . "Do not include reasoning, analysis, markdown, code, tool calls, or hidden thoughts.\n"
            . "Use only this JSON context for the signed-in customer and public site links. Never mention, infer, or access other users' data. If data is missing, ask the customer to use the contact form.\n"
            . "You may help with: " . $this->allowedHelp() . ".\n"
            . "End with one exact internal path (e.g., /shop, /product/slug, /cart, /checkout, /contact) written as part of a normal sentence, so the UI can show a navigation button.\n"
            . ($this->consultEnabled()
                ? "For booking a consultation: explain step-by-step — browse consultants at /consult, view their profile, fill contact details, submit request, wait for admin to confirm appointment. Mention /consult.\n"
                : "Consultations are unavailable. Never mention /consult, consultants or astrologers.\n")
            . "For buying a product: explain step-by-step — browse /shop, click a product, add to cart, go to /cart, proceed to /checkout, enter address, pay with card/UPI, view order at /account/dashboard/orders.\n"
            . "For product issues or returns: ask the customer to use the /contact form.\n"
            . "Never invent admin paths, external URLs, or claim that an action already happened.\n"
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
        return (new AiReplyCleaner())->clean(
            $reply,
            'I can help with products, orders, delivery addresses and payments. Please ask one specific question.'
        );
    }

    private function looksInternal(string $reply): bool {
        return (new AiReplyCleaner())->looksInternal($reply);
    }

    private function fallbackReply(string $message, array $context): string {
        $lower = strtolower($message);
        if (!$context['signed_in']) {
            if ($this->isPrivateAccountQuestion($lower)) {
                return 'Please sign in to ask about your personal orders. I can still explain products, checkout, and delivery.';
            }
            return $this->publicGuestReply($lower, $context);
        }
        if (preg_match('/^(hi|hello|hey|vanakkam|namaste)\b/i', trim($message))) {
            return 'Hello. I can help with products, checkout, saved addresses, and orders.';
        }
        if (str_contains($lower, 'order')) {
            return empty($context['orders']) ? 'I could not find orders in your account yet.' : 'I found your recent order data in the account panel. Open My Orders for full delivery address, status, shipped time, and review options.';
        }
        if ($this->consultEnabled() && (str_contains($lower, 'talk') || str_contains($lower, 'session') || str_contains($lower, 'astrologer'))) {
            return empty($context['sessions']) ? 'I could not find astrologer sessions in your account yet.' : 'I found recent astrologer session records. Open My Sessions to see who you contacted, session type, credits spent, and review options.';
        }
        // Anything that is not a personal-account question is answered from site
        // knowledge, the same as for a guest. Without this a signed-in customer got a
        // generic line where a signed-out visitor got real product names and links.
        return $this->publicGuestReply($lower, $context);
    }

    /** Consultation answers must not be offered when the module is switched off. */

    /** Capabilities the agent may offer, derived from the modules actually switched on. */
    private function allowedHelp(): string {
        $help = ['navigation details from the JSON'];
        if (module_on('ecommerce')) array_unshift($help, 'product', 'cart', 'checkout', 'delivery address', 'order');
        if ($this->consultEnabled()) $help[] = 'consultant booking';
        if (module_on('blog')) $help[] = 'articles and help guides';
        return implode(', ', $help);
    }

    /** Published articles the agent may quote, filtered exactly as the public site is. */
    private function siteArticles(int $limit = 12): array {
        try {
            $out = [];
            foreach ((new BlogService())->all() as $post) {
                $out[] = [
                    'title' => (string)($post['title'] ?? ''),
                    'url' => '/blog/' . (string)($post['slug'] ?? ''),
                    'category' => (string)($post['category'] ?? ''),
                    'summary' => mb_substr(trim((string)($post['excerpt'] ?? $post['summary'] ?? '')), 0, 180),
                ];
                if (count($out) >= $limit) break;
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Article context failed: ' . $e->getMessage());
            return [];
        }
    }

    private function consultEnabled(): bool {
        return (new SettingsService())->moduleEnabled('consult');
    }

    private function publicGuestReply(string $message, array $context): string {
        $site = $context['site'] ?? [];
        $pages = $site['pages'] ?? [];
        $products = array_slice($site['products'] ?? [], 0, 5);
        $consult = $this->consultEnabled();
        if (preg_match('/\b(hi|hello|hey|vanakkam|namaste)\b/i', $message)) {
            return $consult
                ? 'Hello. I can help you browse spiritual products at /shop, place an order, or request a consultant appointment at /consult.'
                : 'Hello. I can help you browse spiritual products at /shop, place an order, or explore temples at /temples.';
        }
        if (preg_match('/\b(deliver\w*|shipping|ship|courier|dispatch)\b/i', $message)) {
            return 'Delivery is calculated at checkout. Add items to your cart, go to /checkout and enter your address to see the exact shipping charge before paying. Track confirmed orders at /account/dashboard/orders.';
        }
        if (preg_match('/\b(products?|available|shop|buy|price|items?|pendants?|rings?|jewelry|jewellery)\b/i', $message)) {
            $names = array_filter(array_map(fn($p) => trim((string)($p['name'] ?? '')), $products));
            $list = $names ? implode(', ', $names) : 'sacred emblems and spiritual jewelry';
            $productLinks = '';
            foreach (array_slice($products, 0, 3) as $p) {
                $slug = $p['slug'] ?? '';
                if ($slug) $productLinks .= ' /product/' . $slug;
            }
            return 'Available products include ' . $list . '. Browse all at /shop' . $productLinks . '. To buy: go to /shop, click a product, add to cart, then proceed to /checkout to pay with card or UPI.';
        }
        if (preg_match('/\b(services?|consult\w*|bookings?|book|astrology|call|message|temples?)\b/i', $message)) {
            return $consult
                ? 'To book a consultation: go to /consult, browse astrologers, click View Profile, fill your details, and submit a request. The admin will confirm and schedule your appointment. For temple guidance, visit /temples.'
                : 'Online consultation is not available at the moment. For temple guidance visit /temples, or send us a message at /contact.';
        }
        if (preg_match('/\b(recharge|wallet|credit|payment)\b/i', $message)) {
            return 'Product payments are completed securely during checkout at /checkout. You can pay with card or UPI. Sign in to reuse saved delivery addresses and view confirmed orders at /account/dashboard/orders.';
        }
        // Articles are matched before the generic branches: "articles about pooja" was
        // falling through to the menu even though the posts were already in context.
        $article = $this->matchArticle($message, $context);
        if ($article !== null) {
            $summary = $article['summary'] !== '' ? ' ' . rtrim($article['summary'], '.') . '.' : '';
            return 'We have an article on that: "' . $article['title'] . '".' . $summary . ' Read it at ' . $article['url'] . ', or browse everything at /blog.';
        }
        if (preg_match('/\b(how|step|guide|help|documentation|docs)\b/i', $message)) {
            return "I can help with:\n- Browsing products at /shop\n" . ($consult ? "- Booking a consultant at /consult\n" : '') . "- Your orders at /account/dashboard/orders\n- Contact us at /contact\nWhat would you like to know more about?";
        }
        return $consult
            ? 'I can help with products at /shop, consultant bookings at /consult, temples at /temples, and orders at /account/dashboard/orders. What would you like help with?'
            : 'I can help with products at /shop, temples at /temples, and orders at /account/dashboard/orders. What would you like help with?';
    }

    /**
     * The published article that best answers the question, or null.
     *
     * Scored on how many of the question's own words appear in the title, so a stray
     * word like "the" cannot pull up an unrelated post. Only titles are matched:
     * summaries are broad enough that almost anything would score.
     */
    private function matchArticle(string $message, array $context): ?array {
        $articles = $context['articles'] ?? [];
        if ($articles === []) return null;
        $words = array_filter(
            preg_split('/[^a-z0-9]+/i', strtolower($message)) ?: [],
            fn(string $w): bool => mb_strlen($w) >= 4
        );
        if ($words === []) return null;
        $best = null;
        $bestScore = 0;
        foreach ($articles as $article) {
            $title = strtolower((string)($article['title'] ?? ''));
            if ($title === '') continue;
            $score = 0;
            foreach ($words as $word) {
                if (str_contains($title, $word)) $score++;
            }
            if ($score > $bestScore) { $bestScore = $score; $best = $article; }
        }
        return $bestScore > 0 ? $best : null;
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
