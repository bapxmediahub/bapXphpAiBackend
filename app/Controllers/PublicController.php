<?php
namespace App\Controllers;
use App\Services\{ProductService,AstrologerService,TempleService,CategoryService,SecretService,SeoService,ContactService,ReviewService};
final class PublicController extends BaseController {
    
    public function home(): void {
        $this->detectApiRequest();
        $this->seoKey = 'home';
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
        $this->seoKey = 'about';
        $this->render('public/about'); 
    }

    public function spiritual(): void {
        $this->detectApiRequest();
        $this->seoKey = 'spiritual';
        $this->render('public/spiritual');
    }
    
    public function terms(): void { 
        $this->detectApiRequest();
        $this->seoKey = 'terms';
        $this->render('public/terms'); 
    }
    
    public function privacy(): void { 
        $this->detectApiRequest();
        $this->seoKey = 'privacy';
        $this->render('public/privacy'); 
    }
    
    public function consult(): void {
        $this->detectApiRequest();
        $this->seoKey = 'consult';
        $reviews = new ReviewService();
        $this->render('public/consult', ['items' => (new AstrologerService())->all(), 'reviews' => $reviews]);
    }
    
    public function consultant(string $slug): void {
        $this->detectApiRequest();
        $astrologer = (new AstrologerService())->findBySlug($slug);
        $this->seoKey = 'astrologer';
        $exp = !empty($astrologer['experience_years']) ? ' with ' . $astrologer['experience_years'] . ' years of experience' : '';
        $this->seoOverrides = [
            'title' => ($astrologer['name'] ?? 'Astrologer') . ' – Vedic Astrologer Online Consultation at Sri Panchami Spiritual',
            'description' => 'Consult ' . ($astrologer['name'] ?? 'an experienced astrologer') . ' online via private message or direct call.' . ($astrologer['speciality'] ? ' ' . $astrologer['speciality'] . '.' : '') . $exp,
            'og_image' => $astrologer['photo_url'] ?? '',
        ];
        $reviewSummary = (new ReviewService())->summary('astrologer', $slug);
        $this->render('public/astrologer', compact('slug', 'astrologer', 'reviewSummary'));
    }
    
    public function temples(): void { 
        $this->detectApiRequest();
        $this->seoKey = 'temples';
        $this->render('public/temples', ['items' => (new TempleService())->all()]); 
    }
    
    public function temple(string $slug): void { 
        $this->detectApiRequest();
        $temple = (new TempleService())->findBySlug($slug);
        $this->seoKey = 'temple';
        $this->seoOverrides = [
            'title' => ($temple['name'] ?? 'Temple') . ' – Temple Timings, Address, Pooja & Darshan at Sri Panchami Spiritual',
            'description' => 'Explore ' . ($temple['name'] ?? 'this temple') . ' with detailed guide including timings, address, location map, and available pooja services. ' . ($temple['description'] ?? ''),
            'og_image' => $temple['image_url'] ?? '',
        ];
        $this->render('public/temple', ['slug' => $slug, 'temple' => $temple]); 
    }
    
    public function shop(): void {
        $this->detectApiRequest();
        $category = $_GET['category'] ?? '';
        try { $categories = (new CategoryService())->all(); } catch (\Throwable $e) { $categories = []; }
        try { $items = (new ProductService())->all(); } catch (\Throwable $e) { $items = []; }
        $this->seoKey = 'shop';
        if ($category) {
            $items = array_values(array_filter($items, function ($item) use ($category) {
                $categoryList = $item['categories'] ?? [$item['category'] ?? ''];
                if (!is_array($categoryList)) {
                    $categoryList = preg_split('/[\r\n,]+/', (string)$categoryList) ?: [];
                }
                $categoryList[] = $item['category'] ?? '';
                return in_array($category, array_filter(array_map('trim', $categoryList)), true);
            }));
            $catName = '';
            foreach ($categories as $c) {
                if (($c['slug'] ?? '') === $category || ($c['name'] ?? '') === $category) {
                    $catName = $c['name'];
                    break;
                }
            }
            if ($catName) {
                $this->seoOverrides = [
                    'title' => 'Buy ' . $catName . ' Online – Spiritual Products at Sri Panchami Spiritual',
                    'description' => 'Shop authentic ' . $catName . ' online at Sri Panchami Spiritual. Browse our collection of sacred items for your spiritual practice. Fast shipping across India.',
                ];
            }
        }
        $this->render('public/shop', compact('items', 'categories', 'category'));
    }

    public function categories(): void {
        $this->detectApiRequest();
        $categories = (new CategoryService())->all();
        if ($this->isApiRequest) {
            $this->jsonResponse($categories);
        }
        $this->seoKey = 'shop';
        $this->render('public/shop', ['items' => (new ProductService())->all(), 'categories' => $categories, 'category' => '']);
    }
    
    public function product(string $slug): void {
        $this->detectApiRequest();
        $product = (new ProductService())->findBySlug($slug);
        $related = [];
        if ($product) {
            $all = (new ProductService())->all();
            $related = array_values(array_filter($all, fn($p) => ($p['slug'] ?? '') !== $slug));
            $this->seoKey = 'product';
            $price = $product['offer_price'] ?? $product['price'] ?? 0;
            $schema = (new SeoService((new SecretService())->all()))->productSchema($product);
            $this->seoOverrides = [
                'title' => ($product['name'] ?? 'Product') . ' – Buy Online at Sri Panchami Spiritual',
                'description' => 'Buy ' . ($product['name'] ?? 'this product') . ' online at Sri Panchami Spiritual. ' . ($product['description'] ?? '') . ' Price: ₹' . $price . '. Authentic spiritual product with fast shipping.',
                'og_image' => $product['image_url'] ?? '',
                'json_ld' => '<script type="application/ld+json">' . json_encode($schema) . '</script>',
            ];
        }
        $reviewSummary = (new ReviewService())->summary('product', $slug);
        $this->render('public/product', compact('product', 'related', 'reviewSummary'));
    }
    
    public function cart(): void {
        $this->detectApiRequest();
        $this->seoKey = 'cart';
        $items = $this->resolveCartItems();
        $this->render('public/cart', ['items' => $items, 'total' => $this->cartTotal($items)]);
    }
    
    public function checkout(): void {
        $this->detectApiRequest();
        $this->seoKey = 'checkout';
        $items = $this->resolveCartItems();
        $secrets = (new SecretService())->all();
        $this->render('public/checkout', ['items' => $items, 'total' => $this->cartTotal($items), 'secrets' => $secrets]);
    }
    
    public function contact(): void {
        $this->detectApiRequest();
        $this->seoKey = 'contact';
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
        $this->seoKey = 'login';
        $secrets = (new \App\Services\SecretService())->all();
        $this->render('public/login', [
            'googleAuthEnabled' => !empty($secrets['google_client_id']) && !empty($secrets['google_client_secret']),
        ]); 
    }

    public function docs(): void {
        $this->seoKey = 'home';
        $this->render('public/docs');
    }
}
