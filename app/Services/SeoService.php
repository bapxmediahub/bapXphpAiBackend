<?php
namespace App\Services;
final class SeoService {
    private string $siteName;
    private string $defaultOgImage;
    private string $twitterHandle;
    private array $telephone;

    public function __construct(array $secrets = []) {
        $envName = getenv('APP_NAME') ?: 'Sri Panchami Spiritual';
        $this->siteName = $secrets['seo_site_name'] ?? $envName;
        $this->defaultOgImage = $secrets['seo_default_og_image'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'sripanchamispiritual.com') . '/assets/images/og-image.jpg';
        $this->twitterHandle = $secrets['seo_twitter_handle'] ?? '';
        $phone = $secrets['phone'] ?? getenv('CONTACT_PHONE') ?: '';
        $this->telephone = $phone !== '' ? [$phone] : ['+919789444037', '+919789444038'];
    }

    public function page(string $key, array $overrides = []): array {
        $host = $_SERVER['HTTP_HOST'] ?? 'sripanchamispiritual.com';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $url = $scheme . '://' . $host . $uri;

        $defaults = $this->defaults($key);
        $meta = array_merge($defaults, $overrides);

        if (!empty($overrides['keywords'])) {
            $meta['keywords'] = $overrides['keywords'];
        } elseif (!isset($meta['keywords'])) {
            $meta['keywords'] = $defaults['keywords'] ?? '';
        }

        $meta['canonical'] ??= $url;
        $meta['og_url'] ??= $url;
        $meta['og_site_name'] = $this->siteName;
        $meta['og_image'] ??= $this->defaultOgImage;
        $meta['twitter_image'] ??= $meta['og_image'];
        $meta['twitter_title'] ??= $meta['og_title'] ?? $meta['title'];
        $meta['twitter_description'] ??= $meta['og_description'] ?? $meta['description'];
        $meta['og_title'] ??= $meta['title'];
        $meta['og_description'] ??= $meta['description'];

        return $meta;
    }

    private function defaults(string $key): array {
        $brand = 'Sri Panchami Spiritual';
        $desc = 'Shop authentic spiritual products and request scheduled consultations with verified consultants.';
        $maps = [
            'home' => [
                'title' => $brand . ' – Online Astrology Consultation, Spiritual Products & Temple Guide',
                'description' => 'Shop authentic spiritual products, rudraksha, pooja items, and sacred jewellery, or request a scheduled consultation.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'shop' => [
                'title' => 'Shop Spiritual Products – Rudraksha, Pooja Items, Sacred Jewellery Online',
                'description' => 'Browse authentic spiritual products online at ' . $brand . '. Shop rudraksha, pooja items, sacred jewellery, and more for your spiritual practice. Fast shipping across India.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'product' => [
                'title' => 'Buy Spiritual Products Online',
                'description' => 'Browse our collection of authentic spiritual products.',
                'og_type' => 'product',
                'robots' => 'index, follow',
            ],
            'consult' => [
                'title' => 'Book a Vedic Astrology Consultation Online',
                'description' => 'Request a scheduled appointment with a verified Vedic astrology consultant for personalised guidance.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'astrologer' => [
                'title' => 'Vedic Astrologer Online Consultation',
                'description' => 'Request a scheduled appointment with an experienced Vedic astrology consultant.',
                'og_type' => 'profile',
                'robots' => 'index, follow',
            ],
            'temples' => [
                'title' => 'Temples in Chennai – Temple Guide, Timings, Address & Pooja Services',
                'description' => 'Explore temples in Chennai with detailed guides including timings, addresses, and available pooja services. Plan your temple visit with ' . $brand . '.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'temple' => [
                'title' => 'Temple Guide – Timings, Address, Pooja & Darshan',
                'description' => 'View temple details including timings, address, location map, and available pooja services.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'about' => [
                'title' => 'About ' . $brand . ' – Chennai\'s Trusted Spiritual Store & Astrology Platform',
                'description' => 'Learn about ' . $brand . ', Chennai\'s trusted destination for authentic spiritual products, Vedic astrology consultations, and temple guidance since 2020.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'contact' => [
                'title' => 'Contact ' . $brand . ' – Get in Touch for Spiritual Products & Astrology',
                'description' => 'Reach out to ' . $brand . ' for inquiries about spiritual products, astrology consultations, temple pooja services, or bulk orders. Call or email us.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'spiritual' => [
                'title' => $brand . ' – Traditional Wisdom & Devotional Practice',
                'description' => 'Explore ' . $brand . ' for authentic spiritual guidance, traditional wisdom, devotional practices, and sacred products for your spiritual journey.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'cart' => [
                'title' => 'Shopping Cart – ' . $brand,
                'description' => 'Review your shopping cart at ' . $brand . '. Proceed to checkout for authentic spiritual products and sacred items.',
                'og_type' => 'website',
                'robots' => 'noindex, follow',
            ],
            'checkout' => [
                'title' => 'Checkout – ' . $brand,
                'description' => 'Complete your purchase at ' . $brand . '. Secure payment for spiritual products, rudraksha, pooja items, and sacred jewellery.',
                'og_type' => 'website',
                'robots' => 'noindex, follow',
            ],
            'privacy' => [
                'title' => 'Privacy Policy – ' . $brand,
                'description' => 'Read the privacy policy of ' . $brand . '. Learn how we collect, use, and protect your personal information when you use our spiritual products and astrology services.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'terms' => [
                'title' => 'Terms & Conditions – ' . $brand,
                'description' => 'Read the terms and conditions of ' . $brand . '. Understand the guidelines for using our astrology consultation services and purchasing spiritual products.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'login' => [
                'title' => 'Sign In – ' . $brand,
                'description' => 'Sign in to your ' . $brand . ' account to manage orders, saved delivery addresses, and consultation bookings.',
                'og_type' => 'website',
                'robots' => 'noindex, follow',
            ],
            'register' => [
                'title' => 'Create Account – ' . $brand,
                'description' => 'Create your ' . $brand . ' account to save delivery addresses, order spiritual products, and book consultations.',
                'og_type' => 'website',
                'robots' => 'noindex, follow',
            ],
            'forgot-password' => [
                'title' => 'Forgot Password – ' . $brand,
                'description' => 'Reset your ' . $brand . ' account password.',
                'og_type' => 'website',
                'robots' => 'noindex, follow',
            ],
            'reset-password' => [
                'title' => 'Reset Password – ' . $brand,
                'description' => 'Reset your ' . $brand . ' account password.',
                'og_type' => 'website',
                'robots' => 'noindex, follow',
            ],
            'account' => [
                'title' => 'My Account – ' . $brand,
                'description' => 'Manage your ' . $brand . ' account, view product orders, and track consultation bookings.',
                'og_type' => 'website',
                'robots' => 'noindex, nofollow',
            ],
            'blog' => [
                'title' => 'Blog & Updates – ' . $brand,
                'description' => 'Read the latest blog posts, feature updates, and spiritual guides from ' . $brand . '.',
                'og_type' => 'website',
                'robots' => 'index, follow',
                'keywords' => 'spiritual blog, astrology articles, vedic astrology blog, rudraksha guide, pooja tips',
            ],
            'blog.post' => [
                'title' => 'Blog Post – ' . $brand,
                'description' => 'Read articles, guides, and updates from ' . $brand . '.',
                'og_type' => 'article',
                'robots' => 'index, follow',
                'keywords' => 'astrology, spirituality, vedic astrology, spiritual products',
            ],
            'blog.category' => [
                'title' => 'Blog Category – ' . $brand,
                'description' => 'Browse blog articles by category at ' . $brand . '.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'admin' => [
                'title' => 'Admin Panel – ' . $brand,
                'description' => '',
                'robots' => 'noindex, nofollow',
            ],
        ];
        return $maps[$key] ?? [
            'title' => $brand,
            'description' => $desc,
            'og_type' => 'website',
            'robots' => 'index, follow',
        ];
    }

    public function jsonLdScript(array $data): string {
        if (!$data) return '';
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    public function organizationSchema(): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => ['Organization', 'OnlineStore'],
            'name' => $this->siteName,
            'description' => 'Authentic spiritual products, sacred jewellery, expert Vedic astrology consultation, and temple guidance.',
            'url' => $this->pageUrl(''),
            'telephone' => $this->telephone,
            'email' => 'sripanchamispiritual@gmail.com',
        ];
    }

    public function breadcrumbSchema(array $items): array {
        $itemList = [];
        $position = 1;
        foreach ($items as $item) {
            $entry = ['@type' => 'ListItem', 'position' => $position++];
            if (is_string($item)) {
                $entry['name'] = $item;
                $entry['item'] = $this->pageUrl($item === 'Home' ? '' : strtolower(str_replace(' ', '-', $item)));
            } else {
                $entry['name'] = $item['name'] ?? '';
                $entry['item'] = $item['url'] ?? '';
            }
            $itemList[] = $entry;
        }
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemList,
        ];
    }

    public function productSchema(array $product): array {
        $price = $product['offer_price'] ?? $product['price'] ?? 0;
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'image' => $product['image_url'] ?? $this->defaultOgImage,
            'offers' => [
                '@type' => 'Offer',
                'price' => (float)$price,
                'priceCurrency' => 'INR',
                'availability' => 'https://schema.org/InStock',
                'url' => $this->pageUrl('/product/' . ($product['slug'] ?? '')),
            ],
        ];
    }

    public function personSchema(array $astrologer): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $astrologer['name'] ?? '',
            'description' => ($astrologer['speciality'] ?? '') . ' astrologer with ' . ($astrologer['experience_years'] ?? '') . ' years of experience.',
            'image' => $astrologer['photo_url'] ?? $this->defaultOgImage,
            'knowsLanguage' => $astrologer['languages'] ?? [],
        ];
    }

    public function aboutPageSchema(): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'AboutPage',
            'name' => 'About ' . $this->siteName,
            'description' => 'Learn about ' . $this->siteName . ', Chennai\'s trusted destination for authentic spiritual products and Vedic astrology consultations.',
            'mainEntity' => $this->organizationSchema(),
        ];
    }

    public function contactPageSchema(): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ContactPage',
            'name' => 'Contact ' . $this->siteName,
            'description' => 'Get in touch with ' . $this->siteName . ' for spiritual products, astrology consultations, and temple pooja services.',
            'mainEntity' => $this->organizationSchema(),
        ];
    }

    public function faqPageSchema(array $questions): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(function ($q) {
                return [
                    '@type' => 'Question',
                    'name' => $q['question'] ?? '',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $q['answer'] ?? '',
                    ],
                ];
            }, $questions),
        ];
    }

    private function pageUrl(string $path): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'sripanchamispiritual.com';
        return $scheme . '://' . $host . '/' . ltrim($path, '/');
    }
}
