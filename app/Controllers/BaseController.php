<?php
namespace App\Controllers;
use App\Services\{CartService,SeoService,SecretService};
abstract class BaseController {
    protected string $layout = 'app';
    protected bool $isApiRequest = false;
    protected string $seoKey = 'home';
    protected array $seoOverrides = [];
    
    protected function redirect(string $path): never { header('Location: ' . $path); exit; }
    protected function flash(string $message, string $type = 'info'): void { $_SESSION['flash'] = ['message' => $message, 'type' => $type]; }
    
    protected function jsonResponse(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function render(string $view, array $data = []): void {
        if ($this->isApiRequest || strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            $this->jsonResponse($data);
            return;
        }
        $secrets = (new SecretService())->all();
        $seo = (new SeoService($secrets))->page($this->seoKey, $this->seoOverrides);
        $data['seo'] = $seo;
        $data['pageTitle'] = $seo['title'];
        $data['metaDescription'] = $seo['description'];
        $data['metaRobots'] = $seo['robots'];
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
