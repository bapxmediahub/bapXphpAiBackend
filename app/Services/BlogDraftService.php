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
            'products' => '/products',
            'consultants' => '/consult',
            'blog' => '/blog',
        ];
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
        return "## Overview\n\nThis article covers [{$title}]({$sourceUrl}). Read on to learn the key details and how to get started.\n\n## What you need to know\n\n- Review the main concepts behind {$title}\n- Understand how it fits into your workflow\n- Follow the steps below to apply what you have learned\n\n## Getting started\n\n1. Visit the [{$this->link('blog')}] to explore related articles\n2. Browse [{$this->link('products')}] for related items\n3. [{$this->link('sign in')}] to access your [{$this->link('account')}]\n\n## Next steps\n\nNeed personal guidance? [Book a consultation]({$this->links['consultants']}) with one of our experts.\n";
    }
    private function draftProduct(string $title, string $sourceUrl): string
    {
        return "## About this product\n\nThis guide covers [{$title}]({$sourceUrl}) — what it is, how to use it, and what to expect.\n\n## Features\n\n- Carefully selected materials and ingredients\n- Easy to use with clear instructions\n- Available for purchase on the [{$this->link('products')}] page\n\n## How to order\n\n1. Add the item to your [{$this->link('cart')}]\n2. Proceed to [{$this->link('checkout')}] and enter your delivery address\n3. Complete payment and track your [{$this->link('orders')}]\n\n## Related\n\n- [{$this->link('sign in')}] to save your address and order history\n- [{$this->link('consultants')}] for personalised recommendations\n";
    }
    private function draftTool(string $title, string $sourceUrl): string
    {
        return "## Overview\n\nThis guide explains how to use the {$title} feature. The page shown below demonstrates the interface and key controls.\n\n## Accessing the feature\n\n1. [{$this->link('sign in')}] to your [{$this->link('account')}]\n2. Navigate to the feature using the on-page controls shown in the screenshot\n3. Follow the on-screen prompts to complete your task\n\n## Tips\n\n- Refer to the image above for the exact location of each control\n- Your changes are saved automatically\n- Visit the [{$this->link('blog')}] for more how-to guides\n\n## Need help?\n\n[{$this->link('consultants')}] are available if you need further assistance.\n";
    }
    private function draftHelp(string $title, string $sourceUrl): string
    {
        return "## What this guide covers\n\nFollow these steps to {$title}. The screenshot above shows the page you will be working with.\n\n## Step-by-step instructions\n\n1. **Open the page** — [Visit the page]({$sourceUrl}) shown in the screenshot above\n2. **Enter your details** — Fill in the required fields as shown\n3. **Confirm and continue** — Select the confirmation button to proceed\n\n> Need to [{$this->link('sign in')}] first? Create your [{$this->link('account')}] or log in to get started.\n\n## What happens next\n\n- You can track progress from your [{$this->link('account')}]\n- View your [{$this->link('orders')}] for order-related tasks\n- Visit [{$this->link('blog')}] for more help articles\n\n## Still stuck?\n\n[{$this->link('consultants')}] are ready to assist you with personalised guidance.\n";
    }
}
