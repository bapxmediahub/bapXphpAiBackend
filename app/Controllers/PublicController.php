<?php
namespace App\Controllers;
use App\Services\{ProductService,AstrologerService,TempleService,CategoryService,SecretService,ContactService,ReviewService};
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
    
    public function consult(): void {
        $this->detectApiRequest();
        $reviews = new ReviewService();
        $this->render('public/consult', ['items' => (new AstrologerService())->all(), 'reviews' => $reviews]);
    }
    
    public function consultant(string $slug): void {
        $this->detectApiRequest();
        $astrologer = (new AstrologerService())->findBySlug($slug);
        $reviewSummary = (new ReviewService())->summary('astrologer', $slug);
        $this->render('public/astrologer', compact('slug', 'astrologer', 'reviewSummary'));
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
            $items = array_values(array_filter($items, function ($item) use ($category) {
                $categoryList = $item['categories'] ?? [$item['category'] ?? ''];
                if (!is_array($categoryList)) {
                    $categoryList = preg_split('/[\r\n,]+/', (string)$categoryList) ?: [];
                }
                $categoryList[] = $item['category'] ?? '';
                return in_array($category, array_filter(array_map('trim', $categoryList)), true);
            }));
        }
        $this->render('public/shop', compact('items', 'categories', 'category'));
    }

    public function categories(): void {
        $this->detectApiRequest();
        $categories = (new CategoryService())->all();
        if ($this->isApiRequest) {
            $this->jsonResponse($categories);
        }
        $this->render('public/shop', ['items' => (new ProductService())->all(), 'categories' => $categories, 'category' => '']);
    }
    
    public function product(string $slug): void {
        $this->detectApiRequest();
        $product = (new ProductService())->findBySlug($slug);
        $related = [];
        if ($product) {
            $all = (new ProductService())->all();
            $related = array_values(array_filter($all, fn($p) => ($p['slug'] ?? '') !== $slug));
        }
        $reviewSummary = (new ReviewService())->summary('product', $slug);
        $this->render('public/product', compact('product', 'related', 'reviewSummary'));
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
        $subject = $_GET['subject'] ?? '';
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
        $this->render('public/contact', ['success' => $success, 'subject' => $subject]);
    }
    
    public function login(): void { 
        $this->detectApiRequest();
        $this->render('public/login'); 
    }
}
