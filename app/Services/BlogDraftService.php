<?php
namespace App\Services;
final class BlogDraftService
{
    private array $links;
    public function __construct()
    {
        $this->links = [
            'register' => '/register',
            'sign in' => '/login',
            'account' => '/account',
            'orders' => '/account/orders',
            'cart' => '/cart',
            'checkout' => '/checkout',
            'products' => '/shop',
            'contact' => '/contact',
            'blog' => '/blog',
        ];
    }
    public static function enhancementError(?string $category): string
    {
        return match ($category) {
            'not_configured' => 'Configure an AI provider in Integrations to request a rewrite.',
            'authentication' => 'The AI provider rejected its credentials. Check Integrations.',
            'rate_limit' => 'The AI provider reached its request limit. Try again later.',
            'timeout' => 'The AI provider took too long to respond.',
            'transport' => 'The site could not reach the AI provider.',
            'provider_server' => 'The AI provider reported a service error.',
            'provider_request' => 'The AI provider rejected the request. Check the connection in Integrations.',
            'empty_response' => 'The AI provider returned no text.',
            default => 'The AI provider could not supply a rewrite.',
        };
    }

    public static function reviewableEnhancement(string $field, string $original, string $answer): string
    {
        $candidate = trim($answer);
        if ($field === 'title') {
            $candidate = trim(strip_tags($candidate), " \t\r\n\"'");
            if ($candidate === '' || str_contains($candidate, "\n") || mb_strlen($candidate) > 120) {
                throw new \InvalidArgumentException('The AI provider did not return a single usable headline.');
            }
        } elseif ($field === 'content') {
            if ($candidate === '' || strlen($candidate) > 1048576) {
                throw new \InvalidArgumentException('The AI provider did not return usable article text.');
            }
            // Compare image URLs as the public article will actually render them.
            $images = static function (string $markdown): array {
                preg_match_all('~<img\b[^>]*?\ssrc="([^"]+)"~i', (new MarkdownRenderer())->render($markdown), $matches);
                return $matches[1];
            };
            if ($images($original) !== $images($candidate)) {
                throw new \InvalidArgumentException('The AI suggestion changed the article images and was discarded.');
            }
        } else {
            throw new \InvalidArgumentException('Unknown article field.');
        }
        return $candidate;
    }

    public function draft(string $template, string $title, string $sourceUrl): string
    {
        $method = 'draft' . ucfirst($template);
        if (method_exists($this, $method)) {
            return $this->$method($title, $sourceUrl);
        }
        return $this->draftEditorial($title, $sourceUrl);
    }
    private function link(string $text): string
    {
        $href = $this->links[$text] ?? $text;
        if (str_starts_with($href, '/')) {
            return "[{$text}]({$href})";
        }
        return $text;
    }
    private function draftEditorial(string $title, string $sourceUrl): string
    {
        return "## Overview\n\nThis article covers [{$title}]({$sourceUrl}). Read on to learn the key details and how to get started.\n\n## What you need to know\n\n- Review the main concepts behind {$title}\n- Understand how it fits into your workflow\n- Follow the steps below to apply what you have learned\n\n## Getting started\n\n1. Visit the {$this->link('blog')} to explore related articles\n2. Browse {$this->link('products')} for related items\n3. {$this->link('sign in')} to access your {$this->link('account')}\n\n## Next steps\n\nNeed help with a product or temple guide? [Contact us]({$this->links['contact']}).\n";
    }
    private function draftProduct(string $title, string $sourceUrl): string
    {
        return "## About this product\n\nThis guide covers [{$title}]({$sourceUrl}) — what it is, how to use it, and what to expect.\n\n## Features\n\n- Carefully selected materials and ingredients\n- Easy to use with clear instructions\n- Available for purchase on the {$this->link('products')} page\n\n## How to order\n\n1. Add the item to your {$this->link('cart')}\n2. Proceed to {$this->link('checkout')} and enter your delivery address\n3. Complete payment and track your {$this->link('orders')}\n\n## Related\n\n- {$this->link('sign in')} to save your address and order history\n- {$this->link('contact')} for product or temple guidance\n";
    }
    private function draftTool(string $title, string $sourceUrl): string
    {
        return "## Overview\n\nThis guide explains how to use the {$title} feature. The page shown below demonstrates the interface and key controls.\n\n## Accessing the feature\n\n1. {$this->link('sign in')} to your {$this->link('account')}\n2. Navigate to the feature using the on-page controls shown in the screenshot\n3. Follow the on-screen prompts to complete your task\n\n## Tips\n\n- Refer to the image above for the exact location of each control\n- Use Save where offered to apply your changes\n- Visit the {$this->link('blog')} for more how-to guides\n\n## Need help?\n\n{$this->link('contact')} can help with product or temple questions.\n";
    }
    private function draftHelp(string $title, string $sourceUrl): string
    {
        return "## What this guide covers\n\nFollow these steps to {$title}. The screenshot above shows the page you will be working with.\n\n## Step-by-step instructions\n\n1. **Open the page** — [Visit the page]({$sourceUrl}) shown in the screenshot above\n2. **Enter your details** — Fill in the required fields as shown\n3. **Confirm and continue** — Select the confirmation button to proceed\n\n> Need to {$this->link('sign in')} first? Create your {$this->link('account')} or log in to get started.\n\n## What happens next\n\n- You can track progress from your {$this->link('account')}\n- View your {$this->link('orders')} for order-related tasks\n- Visit {$this->link('blog')} for more help articles\n\n## Still stuck?\n\n{$this->link('contact')} can help with product or temple questions.\n";
    }
}
