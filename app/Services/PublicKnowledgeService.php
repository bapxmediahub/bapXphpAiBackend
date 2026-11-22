<?php
declare(strict_types=1);
namespace App\Services;

/**
 * One read-only published-knowledge source shared by local MCP and both agents.
 *
 * The browser and protocol adapters may wrap this data, but must never read
 * raw Markdown files, private instructions, transcripts or unrestricted rows.
 */
final class PublicKnowledgeService
{
    /** These are the existing public contact form topics, not bookable services. */
    private const CONTACT_SUBJECTS = [
        'product' => 'Product Inquiry',
        'temple' => 'Temple Guidance',
        'order' => 'Order Support',
        'general' => 'General Question',
    ];

    private const SERVICE_DESCRIPTIONS = [
        'product' => 'Ask about a spiritual product or its published details.',
        'temple' => 'Ask for temple guidance or help planning a temple visit.',
        'order' => 'Get help with an order through the existing support team.',
        'general' => 'Send a general store enquiry through the contact form.',
    ];

    public function __construct(private ?ProductService $products = null, private ?BlogService $blog = null) {}

    private function catalogue(): ProductService
    {
        return $this->products ??= new ProductService();
    }

    private function articlesSource(): BlogService
    {
        return $this->blog ??= new BlogService();
    }

    private static function checkPage(string $query, int $offset, int $limit): void
    {
        if (!mb_check_encoding($query, 'UTF-8') || mb_strlen($query) > 120
            || $offset < 0 || $offset > 2147483647 || $limit < 1 || $limit > 20) {
            throw new \InvalidArgumentException('Use a query up to 120 characters, offset 0..2147483647 and limit 1..20.');
        }
    }

    public static function articleInputSchema(): array
    {
        return [
            'type'=>'object', 'additionalProperties'=>false,
            'properties'=>[
                'query'=>['type'=>'string','maxLength'=>120],
                'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>2147483647],
                'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>20],
            ],
        ];
    }

    public static function articleOutputSchema(): array
    {
        $fields=[
            'title'=>['type'=>'string','minLength'=>1],
            'path'=>['type'=>'string','pattern'=>'^/blog/[a-z0-9-]+$'],
            'category'=>['type'=>'string'],
            'score'=>['type'=>'integer','minimum'=>1],
        ];
        return [
            'type'=>'object', 'additionalProperties'=>false,
            'properties'=>[
                'articles'=>[
                    'type'=>'array','maxItems'=>20,
                    'items'=>['type'=>'object','additionalProperties'=>false,
                        'properties'=>$fields,'required'=>array_keys($fields)],
                ],
                'total'=>['type'=>'integer','minimum'=>0],
                'nextOffset'=>['type'=>['integer','null'],'minimum'=>0],
            ],
            'required'=>['articles','total','nextOffset'],
        ];
    }

    public static function productInputSchema(): array
    {
        return self::articleInputSchema();
    }

    public static function productOutputSchema(): array
    {
        $fields = [
            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
            'path' => ['type' => 'string', 'pattern' => '^/product/[A-Za-z0-9%_.-]+$'],
            'priceInr' => ['type' => 'number', 'minimum' => 0],
            'available' => ['type' => 'boolean'],
            'description' => ['type' => 'string', 'maxLength' => 280],
            'imagePath' => ['type' => 'string', 'maxLength' => 512,
                'pattern' => '^/assets/images/[A-Za-z0-9%_./-]+$'],
        ];
        return [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => [
                'products' => [
                    'type' => 'array', 'maxItems' => 20,
                    'items' => ['type' => 'object', 'additionalProperties' => false,
                        'properties' => $fields,
                        'required' => ['name', 'path', 'priceInr', 'available', 'description']]
                ],
                'total' => ['type' => 'integer', 'minimum' => 0],
                'nextOffset' => ['type' => ['integer', 'null'], 'minimum' => 0],
            ],
            'required' => ['products', 'total', 'nextOffset'],
        ];
    }

    public static function contactInputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false];
    }

    public static function contactOutputSchema(): array
    {
        $fields = [
            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
            'path' => ['type' => 'string', 'const' => '/contact'],
            'phones' => ['type' => 'array', 'maxItems' => 2, 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'label' => ['type' => 'string', 'maxLength' => 32],
                    'number' => ['type' => 'string', 'pattern' => '^\\+[0-9]{7,15}$'],
                ], 'required' => ['label', 'number'],
            ]],
            'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254],
            'hours' => ['type' => 'array', 'maxItems' => 2, 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'days' => ['type' => 'string', 'maxLength' => 40],
                    'hours' => ['type' => 'string', 'maxLength' => 40],
                ], 'required' => ['days', 'hours'],
            ]],
            'servicePath' => ['type' => 'string', 'const' => '/contact#contact-form'],
        ];
        return ['type' => 'object', 'additionalProperties' => false,
            'properties' => $fields, 'required' => array_keys($fields)];
    }

    public static function serviceInputSchema(): array
    {
        return self::articleInputSchema();
    }

    public static function serviceOutputSchema(): array
    {
        $fields = [
            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
            'description' => ['type' => 'string', 'maxLength' => 280],
            'path' => ['type' => 'string', 'enum' => array_map(
                static fn(string $subject): string => '/contact?subject=' . $subject . '#contact-form',
                array_keys(self::CONTACT_SUBJECTS)
            )],
        ];
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'services' => ['type' => 'array', 'maxItems' => 4, 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => $fields, 'required' => array_keys($fields),
            ]],
            'total' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 4],
            'nextOffset' => ['type' => ['integer', 'null'], 'minimum' => 0],
        ], 'required' => ['services', 'total', 'nextOffset']];
    }

    /** Public business facts are explicitly selected; never expose a settings row. */
    public function contact(): array
    {
        return [
            'name' => 'Sri Panchami Spiritual',
            'path' => '/contact',
            'phones' => [
                ['label' => '+91 97894 44037', 'number' => '+919789444037'],
                ['label' => '+91 97894 44038', 'number' => '+919789444038'],
            ],
            'email' => 'support@sripanchamispiritual.com',
            'hours' => [
                ['days' => 'Mon–Sat', 'hours' => '9 AM–7 PM'],
                ['days' => 'Sun', 'hours' => '10 AM–5 PM'],
            ],
            'servicePath' => '/contact#contact-form',
        ];
    }

    public static function contactSubjects(): array
    {
        return self::CONTACT_SUBJECTS;
    }

    /** Existing enquiry offerings remain public even when shop/blog are disabled. */
    public function services(string $query = '', int $offset = 0, int $limit = 20): array
    {
        self::checkPage($query, $offset, $limit);
        $words = preg_split('/\\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $matches = [];
        foreach (self::CONTACT_SUBJECTS as $subject => $name) {
            $description = self::SERVICE_DESCRIPTIONS[$subject];
            $text = mb_strtolower($name . ' ' . $description);
            foreach ($words as $word) {
                if (mb_strpos($text, $word) === false) continue 2;
            }
            $matches[] = ['name' => $name, 'description' => $description,
                'path' => '/contact?subject=' . $subject . '#contact-form'];
        }
        $total = count($matches);
        $end = min($total, $offset + $limit);
        return ['services' => array_slice($matches, $offset, $limit),
            'total' => $total, 'nextOffset' => $end < $total ? $end : null];
    }

    private static function plainText(string $text, int $limit): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('~<(script|style)\\b[^>]*>.*?</\\1\\s*>~is', '', $text) ?? '';
        $text = strip_tags(preg_replace('~</?(?:p|br|div|li|h[1-6])\\b[^>]*>~i', ' ', $text) ?? '');
        $text = preg_replace('/[\\s\\x00-\\x1F\\x7F]+/u', ' ', $text) ?? '';
        return mb_substr(trim($text), 0, $limit);
    }

    /** Encode a single stored slug once; separators and encoded traversal are never routes. */
    private static function productPath(string $slug): ?string
    {
        if (!mb_check_encoding($slug, 'UTF-8')
            || !preg_match('/^[\\p{L}\\p{N}_][\\p{L}\\p{N}._ -]*$/uD', $slug)) return null;
        return '/product/' . rawurlencode($slug);
    }

    /** Only stored local raster media may cross into public presentation cards. */
    private static function imagePath(mixed $image): ?string
    {
        if (!is_string($image) || $image === '' || strlen($image) > 2048
            || str_contains($image, '\\') || preg_match('/[\\x00-\\x20\\x7F]/', $image)) return null;
        $parts = parse_url($image);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
            || isset($parts['fragment'])) return null;
        if (isset($parts['host']) || isset($parts['scheme'])) {
            $configured = $_SERVER['APP_URL'] ?? $_ENV['APP_URL'] ?? getenv('APP_URL');
            $origin = parse_url(is_string($configured) && $configured !== ''
                ? $configured : 'https://sripanchamispiritual.com');
            if (!is_array($origin) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || strtolower($parts['host'] ?? '') !== strtolower($origin['host'] ?? '')
                || ($parts['scheme'] ?? '') !== ($origin['scheme'] ?? '')
                || ($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80))
                    !== ($origin['port'] ?? (($origin['scheme'] ?? '') === 'https' ? 443 : 80))) return null;
        }
        $path = $parts['path'] ?? '';
        if (!str_starts_with($path, '/assets/images/')) return null;
        $segments = explode('/', substr($path, strlen('/assets/images/')));
        foreach ($segments as &$segment) {
            $segment = rawurldecode($segment);
            if (!mb_check_encoding($segment, 'UTF-8')
                || !preg_match('/^[\\p{L}\\p{N}_][\\p{L}\\p{N}._ -]*$/uD', $segment)) return null;
            $segment = rawurlencode($segment);
        }
        unset($segment);
        $path = '/assets/images/' . implode('/', $segments);
        return strlen($path) <= 512 && preg_match('/\\.(?:avif|gif|jpe?g|png|webp)$/iD', $path) ? $path : null;
    }

    /** Canonical catalogue projection: visibility and current offer price come from ProductService. */
    public function products(string $query='', int $offset=0, int $limit=20): array
    {
        self::checkPage($query,$offset,$limit);
        if (!(new SettingsService())->modules(requireReadable:true)['shop']) {
            return ['products'=>[], 'total'=>0,'nextOffset'=>null];
        }
        $service=$this->catalogue();
        $matches=$service->discover($service->visible(),trim($query),'');
        // Page over valid public destinations, so a rejected legacy row cannot produce an empty page.
        $matches=array_values(array_filter($matches, static fn(array $item): bool =>
            self::productPath((string)($item['slug']??'')) !== null
            && self::plainText((string)($item['name']??''),160) !== ''));
        $out=[];
        foreach(array_slice($matches,$offset,$limit) as $item){
            $name=self::plainText((string)($item['name']??''),160);
            $row=[
                'name'=>$name,
                'path'=>self::productPath((string)$item['slug']),
                'priceInr'=>$service->priceOf($item),
                'available'=>in_array($item['stock_status']??'in_stock',['in_stock','active'],true),
                'description'=>self::plainText((string)($item['description']??''),280),
            ];
            $imagePath = self::imagePath($item['image_url'] ?? null);
            if ($imagePath !== null) $row['imagePath'] = $imagePath;
            $out[] = $row;
        }
        $end=min(count($matches),$offset+$limit);
        return ['products'=>$out,'total'=>count($matches),'nextOffset'=>$end<count($matches)?$end:null];
    }

    /**
     * Public blogs include customer help guides only when BlogService marks them public.
     * Draft, future, disabled-module, retired and symlinked Markdown never escapes.
     */
    public function articles(string $query='', int $offset=0, int $limit=5): array
    {
        self::checkPage($query,$offset,$limit);
        if (!(new SettingsService())->modules(requireReadable:true)['blog']) {
            return ['articles'=>[], 'total'=>0, 'nextOffset'=>null];
        }
        $words=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower(trim($query)),-1,PREG_SPLIT_NO_EMPTY)?:[];
        $words=array_values(array_filter($words,static fn(string $w):bool=>mb_strlen($w)>=3));
        if(trim($query)!=='' && $words===[])return ['articles'=>[],'total'=>0,'nextOffset'=>null];
        $scored=[];
        foreach($this->articlesSource()->all(false) as $post){
            $slug=(string)($post['slug']??'');
            $title=trim((string)($post['title']??''));
            if(!preg_match('/^[a-z0-9-]{1,120}$/D',$slug)||$title==='')continue;
            $summary=mb_strtolower((string)($post['excerpt']??$post['summary']??''));
            $lowerTitle=mb_strtolower($title);
            $score=$words===[]?1:0;
            foreach($words as $word){
                if(mb_strpos($lowerTitle,$word)!==false)$score+=2;
                if(mb_strpos($summary,$word)!==false)$score++;
            }
            if($score===0)continue;
            $scored[]=[
                'title'=>$title,
                'path'=>'/blog/'.$slug,
                'category'=>(string)($post['category']??''),
                'score'=>$score,
            ];
        }
        usort($scored,static fn(array $a,array $b):int=>$b['score']<=>$a['score'] ?: strcmp($a['path'],$b['path']));
        $total=count($scored);
        $end=min($total,$offset+$limit);
        return ['articles'=>array_slice($scored,$offset,$limit),'total'=>$total,'nextOffset'=>$end<$total?$end:null];
    }

    /** Preserve the existing Enterprise agent tool response while reusing one retriever. */
    public function articlesForAgent(string $query): array
    {
        if(trim($query)==='')return ['error'=>'Give something to search for.'];
        $words=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($query),-1,PREG_SPLIT_NO_EMPTY)?:[];
        if(!array_filter($words,static fn(string $w):bool=>mb_strlen($w)>=3)){
            return ['found'=>0,'message'=>'Nothing specific enough to search for.'];
        }
        $result=$this->articles(mb_substr($query,0,120));
        if(!$result['articles'])return ['found'=>0,'message'=>'No published article matched.'];
        return ['found'=>$result['total'],'articles'=>$result['articles']];
    }
}
