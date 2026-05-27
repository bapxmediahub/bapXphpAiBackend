<?php
namespace App\Controllers;
use App\Services\{ProductService,AstrologerService,TempleService,CategoryService,AvailabilityService,AppointmentService,SecretService,ContactService};
final class PublicController extends BaseController {
    
    protected function detectApiRequest(): void {
        $this->isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') === 0;
    }
    
    public function home(): void {
        $this->detectApiRequest();
        $categories = (new CategoryService())->all();
        $this->render('public/home', [
            'products' => (new ProductService())->all(),
            'astrologers' => (new AstrologerService())->all(),
            'temples' => (new TempleService())->all(),
            'categories' => $categories,
        ]);
    }
    
    public function about(): void { 
        $this->detectApiRequest();
        $this->render('public/about'); 
    }
    
    public function spiritual(): void { 
        $this->detectApiRequest();
        $this->render('public/spiritual'); 
    }
    
    public function astrologers(): void { 
        $this->detectApiRequest();
        $this->render('public/astrologers', ['items' => (new AstrologerService())->all()]); 
    }
    
    public function astrologer(string $slug): void {
        $this->detectApiRequest();
        $astrologer = (new AstrologerService())->findBySlug($slug);
        $date = $_GET['date'] ?? date('Y-m-d');
        $slots = $astrologer ? (new AvailabilityService())->slotsForDate($astrologer, $date, (new AppointmentService())->all()) : [];
        $this->render('public/astrologer', compact('slug', 'astrologer', 'date', 'slots'));
    }
    
    public function temples(): void { 
        $this->detectApiRequest();
        $this->render('public/temples', ['items' => (new TempleService())->all()]); 
    }
    
    public function temple(string $slug): void { 
        $this->detectApiRequest();
        $temple = (new TempleService())->findBySlug($slug);
        $this->render('public/temple', ['slug' => $slug, 'temple' => $temple]); 
    }
    
    public function shop(): void {
        $this->detectApiRequest();
        $category = $_GET['category'] ?? '';
        $categories = (new CategoryService())->all();
        $items = (new ProductService())->all();
        if ($category) {
            $items = array_values(array_filter($items, fn($item) => ($item['category'] ?? '') === $category));
        }
        $this->render('public/shop', compact('items', 'categories', 'category'));
    }
    
    public function product(string $slug): void {
        $this->detectApiRequest();
        $product = (new ProductService())->findBySlug($slug);
        $related = [];
        if ($product) {
            $all = (new ProductService())->all();
            $related = array_values(array_filter($all, fn($p) => ($p['slug'] ?? '') !== $slug));
        }
        $this->render('public/product', compact('product', 'related'));
    }
    
    public function cart(): void {
        $this->detectApiRequest();
        $items = $this->resolveCartItems();
        $this->render('public/cart', ['items' => $items, 'total' => $this->cartTotal($items)]);
    }
    
    public function checkout(): void {
        $this->detectApiRequest();
        $items = $this->resolveCartItems();
        $secrets = (new SecretService())->all();
        $this->render('public/checkout', ['items' => $items, 'total' => $this->cartTotal($items), 'secrets' => $secrets]);
    }
    
    public function contact(): void {
        $this->detectApiRequest();
        $success = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contactService = new ContactService();
            $contactService->save([
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'message' => $_POST['message'] ?? '',
            ]);
            $success = true;
        }
        $this->render('public/contact', ['success' => $success]);
    }
    
    public function login(): void { 
        $this->detectApiRequest();
        $this->render('public/login'); 
    }
}
