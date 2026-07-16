<?php
namespace App\Controllers;

use App\Services\{ProductService,CategoryService,TempleService};

final class ApiController extends BaseController {
    public function index(): void {
        $this->jsonResponse(['success' => true, 'endpoints' => [
            '/api/shop',
            '/api/categories',
            '/api/product/{slug}',
            '/api/consult',
            '/api/temples',
        ]]);
    }

    public function shop(): void {
        $service = new ProductService();
        $products = $service->all();
        $categories = (new CategoryService())->all();
        $this->jsonResponse(['success' => true, 'products' => $products, 'categories' => $categories]);
    }

    public function categories(): void {
        $categories = (new CategoryService())->all();
        $this->jsonResponse(['success' => true, 'categories' => $categories]);
    }

    public function product(string $slug): void {
        $service = new ProductService();
        $products = $service->all();
        $product = null;
        foreach ($products as $p) {
            if (($p['slug'] ?? '') === $slug) { $product = $p; break; }
        }
        if ($product === null) {
            $this->jsonResponse(['success' => false, 'error' => 'Product not found'], 404);
            return;
        }
        $this->jsonResponse(['success' => true, 'product' => $product]);
    }

    public function consult(): void {
        $service = new ProductService();
        $astrologers = $service->all();
        $this->jsonResponse(['success' => true, 'astrologers' => $astrologers]);
    }

    public function temples(): void {
        $temples = (new TempleService())->all();
        $this->jsonResponse(['success' => true, 'temples' => $temples]);
    }
}
