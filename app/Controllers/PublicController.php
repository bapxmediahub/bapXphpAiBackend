<?php
namespace App\Controllers;
use App\Services\{AuthService,BlogService,ProductService,TempleService,CategoryService,SecretService,SeoService,ContactService,ReviewService,MarkdownRenderer};
final class PublicController extends BaseController {
    
    public function home(): void {
        $this->detectApiRequest();
        $this->seoKey = 'home';
        $categories = (new CategoryService())->all();
        $products = (new ProductService())->visible();
        $temples = (new TempleService())->all();
        $this->render('public/home', [
            'products' => $products,
            'temples' => $temples,
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
        $this->render('public/terms', ['document' => $this->markdownDocument('content/legal/terms.md')]);
    }
    
    public function privacy(): void { 
        $this->detectApiRequest();
        $this->seoKey = 'privacy';
        $this->render('public/privacy', ['document' => $this->markdownDocument('content/legal/privacy.md')]);
    }
    
    public function consult(): void {
        $this->redirect('/shop');
    }
    
    public function consultant(string $slug): void {
        $this->redirect('/shop');
    }
    
    public function temples(): void { 
        $this->detectApiRequest();
        $this->seoKey = 'temples';
        $this->render('public/temples', ['items' => (new TempleService())->all()]); 
    }
    
    public function temple(string $slug): void { 
        $this->detectApiRequest();
        $temple = (new TempleService())->findBySlug($slug);
        if (!$temple) $this->renderNotFound();
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
        $categories = (new CategoryService())->all();
        $items = (new ProductService())->visible();
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
            return;
        }
        $this->seoKey = 'shop';
        $this->render('public/categories', ['items' => (new ProductService())->visible(), 'categories' => $categories, 'category' => '']);
    }
    
    public function product(string $slug): void {
        $this->detectApiRequest();
        $adminPreview = ($_GET['preview'] ?? '') === '1';
        if ($adminPreview) {
            (new AuthService())->requireAdmin();
            header('X-Robots-Tag: noindex, nofollow');
        }
        $product = (new ProductService())->findBySlug($slug, $adminPreview);
        // A missing product used to render the template with a null record and return
        // HTTP 200 — a soft 404. Search engines index those as real pages and keep
        // crawling dead URLs.
        if (!$product) $this->renderNotFound();
        $related = [];
        if ($product) {
            $all = (new ProductService())->visible();
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
            if ($adminPreview) {
                $related = [];
                $this->seoOverrides['robots'] = 'noindex, nofollow';
                $this->seoOverrides['json_ld'] = '';
                $this->seoOverrides['title'] = 'Admin preview – ' . ($product['name'] ?? 'Product');
            }
        }
        $reviewSummary = (new ReviewService())->summary('product', $slug);
        $this->render('public/product', compact('product', 'related', 'reviewSummary', 'adminPreview'));
    }
    
    public function cart(): void {
        $this->detectApiRequest();
        $this->seoKey = 'cart';
        $items = $this->resolveCartItems();
        $total = $this->cartTotal($items);
        // Prices are GST-inclusive, so show the tax already contained in the total.
        $settings = (new \App\Services\SettingsService())->public();
        $summary = \App\Services\TaxService::cartSummary($items, $settings);
        $this->render('public/cart', [
            'items' => $items,
            'total' => $total,
            'gstAmount' => $summary['gst_amount'],
            'itemCount' => $summary['item_count'],
        ]);
    }
    
    public function checkout(): void {
        $this->detectApiRequest();
        $this->seoKey = 'checkout';
        $items = [];
        try {
            $items = $this->resolveCartItems();
        } catch (\Throwable $e) {
            error_log('Checkout resolveCartItems error: ' . $e->getMessage());
        }
        $secrets = [];
        $razorpayReady = false;
        $settings = ['shipping_mode' => 'free', 'flat_rate' => 0];
        try {
            $secretService = new SecretService();
            $secrets = $secretService->all();
            $razorpayReady = $secretService->razorpayReadyForCurrentHost($secrets);
            $settings = (new \App\Services\SettingsService())->public();
        } catch (\Throwable $e) {
            error_log('Checkout secrets/settings error: ' . $e->getMessage());
        }
        $addresses = [];
        if (($items !== []) && ($_SESSION['user']['email'] ?? '') !== '') {
            try {
                $addresses = (new \App\Services\AddressService())->forCustomer($_SESSION['user']['email']);
            } catch (\Throwable $e) {
                error_log('Checkout addresses error: ' . $e->getMessage());
            }
        }
        $this->render('public/checkout', ['items' => $items, 'total' => $this->cartTotal($items), 'secrets' => $secrets, 'addresses' => $addresses, 'razorpayReady' => $razorpayReady, 'settings' => $settings]);
    }
    
    public function sitemap(): void {
        header('Content-Type: application/xml; charset=utf-8');
        $host = $_SERVER['HTTP_HOST'] ?? 'sripanchamispiritual.com';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $host;

        $pages = [
            '/', '/about', '/temples', '/shop', '/contact', '/blog',
            '/terms', '/privacy', '/spiritual',
        ];
        $products = [];
        try { $products = (new ProductService())->visible(); } catch (\Throwable) {}
        $blogPosts = [];
        try { $blogPosts = (new BlogService())->all(); } catch (\Throwable) {}

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $path) {
            $xml .= '  <url><loc>' . $base . $path . '</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>' . "\n";
        }

        foreach ($products as $p) {
            if (!empty($p['slug'])) {
                $xml .= '  <url><loc>' . $base . '/product/' . e($p['slug']) . '</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>' . "\n";
            }
        }

        foreach ($blogPosts as $post) {
            if (!empty($post['slug']) && !empty($post['published'])) {
                $xml .= '  <url><loc>' . $base . '/blog/' . e($post['slug']) . '</loc><lastmod>' . e(substr((string)($post['updated_at'] ?? $post['published_at'] ?? ''), 0, 10)) . '</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>' . "\n";
            }
        }

        $xml .= '</urlset>';
        echo $xml;
        exit;
    }

    public function contact(): void {
        $this->detectApiRequest();
        $this->seoKey = 'contact';
        $success = false;
        $subject = $_GET['subject'] ?? '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $this->checkRateLimit('contact', 3, 120);
            $contactService = new ContactService();
            $submission = [
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'message' => $_POST['message'] ?? '',
            ];
            $submission['id'] = $contactService->save($submission);
            // The owner was never told a contact form had been submitted; it only
            // appeared under Admin -> Contacts if someone thought to look.
            try {
                (new \App\Services\MailQueueService())->enqueueContactConfirmation($submission);
            } catch (\Throwable $e) { error_log('Contact receipt failed: ' . $e->getMessage()); }
            try {
                (new \App\Services\MailQueueService())->notifyAdmin(
                    'New contact form submission',
                    '<p>A new message came in through the contact form.</p><dl>'
                    . '<dt>Name</dt><dd>' . e((string)($_POST['name'] ?? '')) . '</dd>'
                    . '<dt>Email</dt><dd>' . e((string)($_POST['email'] ?? '')) . '</dd>'
                    . '<dt>Phone</dt><dd>' . e((string)($_POST['phone'] ?? '')) . '</dd>'
                    . '<dt>Subject</dt><dd>' . e((string)($_POST['subject'] ?? '')) . '</dd>'
                    . '<dt>Message</dt><dd>' . nl2br(e((string)($_POST['message'] ?? '')), false) . '</dd></dl>'
                );
            } catch (\Throwable $e) { error_log('Contact notification failed: ' . $e->getMessage()); }
            $success = true;
        }
        $this->render('public/contact', ['success' => $success, 'subject' => $subject]);
    }
    
    public function login(): void { 
        $this->detectApiRequest();
        // Someone already signed in has no business on the sign-in page; send them to
        // their dashboard instead of offering a second login.
        if (!empty($_SESSION['user'])) $this->redirect((($_SESSION['user']['role'] ?? '') === 'admin') ? '/admin' : '/account/dashboard');
        $this->seoKey = 'login';
        $secrets = (new \App\Services\SecretService())->all();
        $this->render('public/login', [
            'googleAuthEnabled' => !empty($secrets['google_client_id']) && !empty($secrets['google_client_secret']),
        ]); 
    }

    public function docs(): void {
        $this->redirect('/blog/category/help');
    }

    public function doc(string $slug): void {
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
        $this->redirect('/blog/' . $slug);
    }

    private function parseContentDocument(string $raw, string $fallbackSlug): array
    {
        $meta = [];
        $body = $raw;
        if (str_starts_with($raw, '---')) {
            $parts = explode('---', $raw, 3);
            if (count($parts) === 3) {
                foreach (explode("\n", trim($parts[1])) as $line) {
                    if (!str_contains($line, ':')) continue;
                    [$key, $value] = explode(':', $line, 2);
                    $meta[trim($key)] = trim(trim($value), "\"'");
                }
                $body = trim($parts[2]);
            }
        }
        preg_match('/^#\s+(.+)$/m', $body, $heading);
        $title = trim((string)($meta['title'] ?? $heading[1] ?? ucfirst(str_replace('-', ' ', $fallbackSlug))));
        $body = trim((string)preg_replace('/^#\s+.+\R?/m', '', $body, 1));
        return [
            'title' => $title,
            'slug' => (string)($meta['slug'] ?? $fallbackSlug),
            'summary' => (string)($meta['summary'] ?? ''),
            'order' => (int)($meta['order'] ?? 100),
            'icon' => (string)($meta['icon'] ?? 'guide'),
            'html' => (new MarkdownRenderer())->render($body),
        ];
    }

    /**
     * Markdown page with YAML frontmatter, the same file shape blogs use.
     *
     * This previously stripped only a leading "# Heading" and rendered everything else,
     * so the frontmatter block was published as body text — Terms and Privacy opened
     * with "title: Terms & Conditions description: ... category: legal". Frontmatter is
     * metadata and must never reach the page.
     */
    private function markdownDocument(string $relativePath): array
    {
        $raw = (string)@file_get_contents(app_path($relativePath));
        $meta = [];
        $body = $raw;

        if (preg_match('/\A\x{FEFF}?\s*---\R(.*?)\R---\R?(.*)\z/su', $raw, $m)) {
            foreach (preg_split('/\R/', $m[1]) as $line) {
                if (!str_contains($line, ':')) continue;
                [$key, $value] = explode(':', $line, 2);
                $meta[trim($key)] = trim(trim($value), " \"'");
            }
            $body = $m[2];
        }

        // Frontmatter title wins; fall back to a leading H1, which is then removed so it
        // is not printed twice under the page heading.
        $title = trim((string)($meta['title'] ?? ''));
        if ($title === '') {
            preg_match('/^#\s+(.+)$/m', $body, $heading);
            $title = trim($heading[1] ?? 'Document');
        }
        $body = trim((string)preg_replace('/^#\s+.+\R?/m', '', $body, 1));

        return [
            'title' => $title,
            'description' => (string)($meta['description'] ?? ''),
            'html' => (new MarkdownRenderer())->render($body),
        ];
    }
}
