<?php
namespace App\Controllers;
use App\Services\CartService;
abstract class BaseController {
    protected string $layout = 'app';
    protected function redirect(string $path): never { header('Location: ' . $path); exit; }
    protected function flash(string $message): void { $_SESSION['flash'] = $message; }
    protected function render(string $view, array $data = []): void {
        extract($data);
        $viewFile = app_path('views/' . $view . '.php');
        require app_path('views/layouts/' . $this->layout . '.php');
    }
    protected function resolveCartItems(): array {
        if (empty($_SESSION['cart'])) return [];
        $products = [];
        $store = new \App\Services\JsonStoreService();
        foreach ($store->read('products') as $p) {
            $products[$p['slug'] ?? ''] = $p;
        }
        $items = [];
        foreach ($_SESSION['cart'] as $line) {
            $slug = $line['slug'] ?? '';
            if (isset($products[$slug])) {
                $p = $products[$slug];
                $price = (int)($p['offer_price'] ?: $p['price'] ?: 0);
                $qty = (int)($line['qty'] ?? 1);
                $items[] = ['product' => $p, 'slug' => $slug, 'name' => $p['name'], 'image_url' => $p['image_url'] ?? '', 'category' => $p['category'] ?? '', 'price' => $p['price'], 'offer_price' => $p['offer_price'] ?? null, 'qty' => $qty, 'line_total' => $price * $qty];
            }
        }
        return $items;
    }
    protected function cartTotal(array $items): int {
        return array_sum(array_column($items, 'line_total'));
    }
    protected function cartCount(): int {
        if (empty($_SESSION['cart'])) return 0;
        return array_sum(array_column($_SESSION['cart'], 'qty'));
    }
}
