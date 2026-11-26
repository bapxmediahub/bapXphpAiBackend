<?php
namespace App\Services;
final class SeoService {
    private string $siteName;
    private string $defaultOgImage;
    private string $twitterHandle;
    private SiteProfileService $profile;
    private string $baseUrl;

    public function __construct(array $secrets = [], ?string $configuredUrl = null, ?SiteProfileService $profile = null) {
        $configuredUrl ??= (string)(require app_path('config/database.php'))['app_url'];
        $parts=parse_url($configuredUrl);
        if(!self::validWebUrl($configuredUrl)||!is_array($parts)||isset($parts['query'])||isset($parts['fragment'])) {
            throw new \RuntimeException('A valid canonical APP_URL is required.');
        }
        $this->baseUrl=rtrim($configuredUrl,'/');
        $this->profile = $profile ?? SiteProfileService::current($secrets, $configuredUrl);
        $this->siteName = $this->profile->name();
        $this->defaultOgImage = '';
        if ($this->profile->isReference()) {
            foreach (['/assets/images/og-image.jpg', '/assets/images/hero-temple-bg.webp'] as $fallback) {
                if ($this->localImageExists($fallback)) {
                    $this->defaultOgImage = $this->pageUrl($fallback);
                    break;
                }
            }
        }
        $this->defaultOgImage=$this->imageUrl((string)($secrets['seo_default_og_image']??''));
        $handle = trim((string)($secrets['seo_twitter_handle'] ?? ''));
        $this->twitterHandle = preg_match('/^@?[A-Za-z0-9_]{1,15}$/D', $handle) ? '@' . ltrim($handle, '@') : '';
    }

    public function page(string $key, array $overrides = []): array {
        $url = $this->pageUrl((string)($_SERVER['REQUEST_URI'] ?? '/'));

        $defaults = $this->defaults($key);
        $meta = array_merge($defaults, $overrides);

        if (!empty($overrides['keywords'])) {
            $meta['keywords'] = $overrides['keywords'];
        } elseif (!isset($meta['keywords'])) {
            $meta['keywords'] = $defaults['keywords'] ?? '';
        }

        $meta['canonical'] = $this->overrideUrl($meta['canonical'] ?? null) ?? $url;
        $meta['og_url'] = $this->overrideUrl($meta['og_url'] ?? null) ?? $meta['canonical'];
        $meta['og_site_name'] = $this->siteName;
        $meta['twitter_site'] = $this->twitterHandle;
        $meta['og_image'] = $this->imageUrl((string)($meta['og_image'] ?? ''));
        $meta['twitter_image'] = empty($meta['twitter_image']) ? $meta['og_image'] : $this->imageUrl((string)$meta['twitter_image']);
        $meta['organization_json_ld'] = $this->jsonLdScript($this->organizationSchema());
        if ($key === 'about') {
            $meta['about_json_ld'] = $this->jsonLdScript($this->aboutPageSchema($meta['description'], $meta['canonical']));
        } elseif ($key === 'contact') {
            $meta['contact_json_ld'] = $this->jsonLdScript($this->contactPageSchema($meta['canonical']));
        }
        $meta['twitter_title'] ??= $meta['og_title'] ?? $meta['title'];
        $meta['twitter_description'] ??= $meta['og_description'] ?? $meta['description'];
        $meta['og_title'] ??= $meta['title'];
        $meta['og_description'] ??= $meta['description'];

        return $meta;
    }

    private function defaults(string $key): array {
        $brand = $this->siteName;
        $desc = 'Shop authentic spiritual products, sacred jewellery, pooja items, and devotional essentials.';
        $maps = [
            'home' => [
                'title' => $this->profile->isReference() ? $brand . ' – Spiritual Products, Sacred Jewellery & Temple Guide' : $brand,
                'description' => $this->profile->summary() ?: ($this->profile->isReference()
                    ? 'Shop authentic spiritual products, rudraksha, pooja items, and sacred jewellery for your devotional practice.' : $brand),
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'shop' => [
                'title' => $this->profile->isReference() ? 'Shop Spiritual Products – Rudraksha, Pooja Items, Sacred Jewellery Online' : 'Shop – ' . $brand,
                'description' => $this->profile->isReference()
                    ? 'Browse authentic spiritual products online at ' . $brand . '. Shop rudraksha, pooja items, sacred jewellery, and more for your spiritual practice. Fast shipping across India.'
                    : 'Browse available products from ' . $brand . '.',
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
                'title' => 'Spiritual Products & Temple Guidance',
                'description' => 'Discover authentic spiritual products, devotional essentials, and temple guidance from ' . $brand . '.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'astrologer' => [
                'title' => 'Spiritual Products & Temple Guidance',
                'description' => 'Discover authentic spiritual products, devotional essentials, and temple guidance from ' . $brand . '.',
                'og_type' => 'profile',
                'robots' => 'index, follow',
            ],
            'temples' => [
                'title' => $this->profile->isReference() ? 'Temples in Tamil Nadu – Temple Guide, Address & Pooja Services' : 'Temple guides – ' . $brand,
                'description' => $this->profile->isReference()
                    ? 'Explore temples across Tamil Nadu with detailed guides including addresses and available visitor information. Plan your temple visit with ' . $brand . '.'
                    : 'Browse published temple guides from ' . $brand . '.',
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
                'title' => 'About ' . $brand,
                'description' => $this->profile->summary() ?: 'Learn about ' . $brand . '.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'contact' => [
                'title' => 'Contact ' . $brand . ($this->profile->isReference() ? ' – Spiritual Products & Support' : ''),
                'description' => $this->profile->isReference()
                    ? 'Reach out to ' . $brand . ' for inquiries about spiritual products, temple pooja services, order support, or bulk orders.'
                    : 'Send a message to ' . $brand . ' using the contact form.',
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
                'description' => 'Read the privacy policy of ' . $brand . '. Learn how we collect, use, and protect your personal information when you use our spiritual products and support services.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'terms' => [
                'title' => 'Terms & Conditions – ' . $brand,
                'description' => 'Read the terms and conditions of ' . $brand . '. Understand the guidelines for purchasing spiritual products and using our website.',
                'og_type' => 'website',
                'robots' => 'index, follow',
            ],
            'login' => [
                'title' => 'Sign In – ' . $brand,
                'description' => 'Sign in to your ' . $brand . ' account to manage orders and saved delivery addresses.',
                'og_type' => 'website',
                'robots' => 'noindex, follow',
            ],
            'register' => [
                'title' => 'Create Account – ' . $brand,
                'description' => 'Create your ' . $brand . ' account to save delivery addresses and order spiritual products.',
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
                'description' => 'Manage your ' . $brand . ' account, view product orders, and track deliveries.',
                'og_type' => 'website',
                'robots' => 'noindex, nofollow',
            ],
            'blog' => [
                'title' => 'Blog & Updates – ' . $brand,
                'description' => 'Read the latest blog posts, feature updates, and spiritual guides from ' . $brand . '.',
                'og_type' => 'website',
                'robots' => 'index, follow',
                'keywords' => 'spiritual blog, rudraksha guide, pooja tips, temple guidance, devotional practice',
            ],
            'blog.post' => [
                'title' => 'Blog Post – ' . $brand,
                'description' => 'Read articles, guides, and updates from ' . $brand . '.',
                'og_type' => 'article',
                'robots' => 'index, follow',
                'keywords' => 'spirituality, devotional practice, temple guidance, spiritual products',
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
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . '</script>';
    }

    public function organizationSchema(): array {
        $contact = $this->profile->contact();
        $organization = [
            '@context' => 'https://schema.org',
            '@type' => (new SettingsService())->moduleEnabled('shop') ? ['Organization', 'OnlineStore'] : 'Organization',
            'name' => $this->siteName,
            'url' => $this->pageUrl(''),
        ];
        $description = $this->profile->summary();
        if ($description === '' && $this->profile->isReference()) {
            $description = 'Authentic spiritual products, sacred jewellery, pooja essentials, and temple guidance.';
        }
        if ($description !== '') $organization['description'] = $description;
        if ($contact['phones']) $organization['telephone'] = array_column($contact['phones'], 'number');
        if (isset($contact['email'])) $organization['email'] = $contact['email'];
        return $organization;
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
        $price = (new ProductService())->priceOf($product);
        $metadata=MerchantFeedService::metadata($product)['fields'];
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'image' => $this->imageUrl((string)($product['image_url'] ?? '')),
            'offers' => [
                '@type' => 'Offer',
                'price' => (float)$price,
                'priceCurrency' => 'INR',
                'availability' => in_array((string)($product['stock_status'] ?? ''), ['in_stock','active'], true)
                    ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $this->pageUrl('/product/' . rawurlencode((string)($product['slug'] ?? ''))),
            ],
        ];
        if (isset($metadata['brand'])) $schema['brand']=['@type'=>'Brand','name'=>$metadata['brand']];
        if (isset($metadata['gtin'])) $schema['gtin'.strlen($metadata['gtin'])]=$metadata['gtin'];
        if (isset($metadata['mpn'])) $schema['mpn']=$metadata['mpn'];
        if (isset($metadata['condition'])) $schema['offers']['itemCondition']='https://schema.org/'.ucfirst($metadata['condition']).'Condition';
        return $schema;
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

    public function aboutPageSchema(?string $description = null, ?string $canonical = null): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'AboutPage',
            'name' => 'About ' . $this->siteName,
            'description' => $description ?? $this->defaults('about')['description'],
            'url' => $this->overrideUrl($canonical) ?? $this->pageUrl('/about'),
            'mainEntity' => $this->organizationSchema(),
        ];
    }

    public function contactPageSchema(?string $canonical = null): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ContactPage',
            'name' => 'Contact ' . $this->siteName,
            'description' => $this->defaults('contact')['description'],
            'url' => $this->overrideUrl($canonical) ?? $this->pageUrl('/contact'),
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

    /** Encode route segments once and keep meaningful existing query strings; never derive authority from a request. */
    private function pageUrl(string $path): string {
        $parts=parse_url($path);
        $route=is_array($parts)?(string)($parts['path']??'/'):'/';
        $route=str_replace(["\r","\n","\0"],'',$route);
        $route=implode('/',array_map(static fn(string $segment):string=>rawurlencode(rawurldecode($segment)),explode('/',$route)));
        $query=is_array($parts)&&isset($parts['query'])?'?'.str_replace(["\r","\n","\0"],'',(string)$parts['query']):'';
        return $this->baseUrl.'/'.ltrim($route,'/').$query;
    }

    private static function validWebUrl(string $url): bool {
        $parts=parse_url($url);
        return is_array($parts)&&in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)
            &&!empty($parts['host'])&&!isset($parts['user'])&&!isset($parts['pass'])
            &&!preg_match('/[\x00-\x20\x7f\\\\]/',$url)&&filter_var($url,FILTER_VALIDATE_URL)!==false;
    }

    private function overrideUrl(mixed $value): ?string {
        if(!is_string($value)||trim($value)==='')return null;
        if(str_starts_with($value,'/')&&!str_starts_with($value,'//'))return $this->pageUrl($value);
        return self::validWebUrl($value)?explode('#',$value,2)[0]:null;
    }

    private function localImageExists(string $path): bool {
        $decoded=rawurldecode($path);
        if(!preg_match('~^/assets/images/[a-z0-9_/ .-]+\.(?:jpe?g|png|webp|gif)$~iD',$decoded)
            ||str_contains($decoded,'..'))return false;
        $file=MediaService::storedPathForUrl($decoded)??app_path(ltrim($decoded,'/'));
        return is_file($file)&&is_readable($file)&&@getimagesize($file)!==false;
    }

    /** Existing local images are verified without fetching arbitrary external hosts. */
    private function imageUrl(string $value): string {
        $value=trim($value);if($value==='')return $this->defaultOgImage;
        $parts=parse_url($value);if(!is_array($parts))return $this->defaultOgImage;
        $base=parse_url($this->baseUrl);
        if(isset($parts['host'])){
            if(!self::validWebUrl($value))return $this->defaultOgImage;
            if(strcasecmp((string)$parts['host'],(string)$base['host'])!==0)return $value;
        }elseif(isset($parts['scheme'])||str_starts_with($value,'//'))return $this->defaultOgImage;
        $path='/'.ltrim((string)($parts['path']??''),'/');
        $basePath=rtrim((string)($base['path']??''),'/');
        if($basePath!==''&&str_starts_with($path,$basePath.'/'))$path=substr($path,strlen($basePath));
        if(!$this->localImageExists($path))return $this->defaultOgImage;
        return $this->pageUrl($path);
    }
}
