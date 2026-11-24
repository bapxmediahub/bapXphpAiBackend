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

    public function __construct(
        private ?ProductService $products = null,
        private ?BlogService $blog = null,
        private ?string $publicUrl = null
    ) {}

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
        $schema=self::articleInputSchema();
        $schema['properties']['slugs']=[
            'type'=>'array','maxItems'=>3,'uniqueItems'=>true,
            'items'=>['type'=>'string','minLength'=>1,'maxLength'=>2039],
            'description'=>'Up to three exact raw product slugs decoded once from returned product paths, in the desired order. Use an empty query, offset 0 and a limit at least as large as this selection. Missing or unpublished products are omitted.',
        ];
        return $schema;
    }

    public static function productOutputSchema(): array
    {
        $fields = [
            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
            'path' => ['type' => 'string', 'maxLength' => 2048,
                'pattern' => '^/product/[A-Za-z0-9%_.-]+$'],
            'productUrl' => ['type'=>'string','format'=>'uri','maxLength'=>4096,
                'pattern'=>'^https://[^/?#]+/product/[A-Za-z0-9%_.-]+$',
                'description'=>'Absolute official product URL; the configured public site origin followed by path.'],
            'priceInr' => ['type' => 'number', 'minimum' => 0],
            'available' => ['type' => 'boolean'],
            'description' => ['type' => 'string', 'maxLength' => 280],
            'imagePath' => ['type' => 'string', 'maxLength' => 512,
                'pattern' => '^/assets/images/[A-Za-z0-9%_./-]+$'],
            'imageUrl' => ['type'=>'string','format'=>'uri','maxLength'=>2560,
                'pattern'=>'^https://[^/?#]+/assets/images/[A-Za-z0-9%_./-]+$',
                'description'=>'Absolute official image URL for imagePath. Absent when no approved product image exists.'],
            'imagePaths' => ['type'=>'array','maxItems'=>3,'uniqueItems'=>true,
                'items'=>['type'=>'string','maxLength'=>512,'pattern'=>'^/assets/images/[A-Za-z0-9%_./-]+$']],
            'imageUrls' => ['type'=>'array','maxItems'=>3,'uniqueItems'=>true,
                'items'=>['type'=>'string','format'=>'uri','maxLength'=>2560,
                    'pattern'=>'^https://[^/?#]+/assets/images/[A-Za-z0-9%_./-]+$'],
                'description'=>'Absolute official URLs corresponding to selected imagePaths in the same order.'],
            'highlights' => ['type'=>'array','maxItems'=>4,
                'items'=>['type'=>'string','minLength'=>1,'maxLength'=>160]],
            'specifications' => ['type'=>'array','maxItems'=>6,'items'=>[
                'type'=>'object','additionalProperties'=>false,
                'properties'=>[
                    'label'=>['type'=>'string','minLength'=>1,'maxLength'=>80],
                    'value'=>['type'=>'string','minLength'=>1,'maxLength'=>240],
                ],'required'=>['label','value'],
            ]],
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
            'properties' => $fields, 'required' => array_values(array_diff(array_keys($fields), ['email']))];
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
        return SiteProfileService::current(origin: $this->publicUrl)->contact();
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

    /** Shared public identity boundary: encode once; separators and encoded traversal are never routes. */
    public static function productPath(string $slug): ?string
    {
        if (strlen($slug) > 2039 || !mb_check_encoding($slug, 'UTF-8')
            || !preg_match('/^[\\p{L}\\p{N}_][\\p{L}\\p{N}\\p{M}._ -]*$/uD', $slug)) return null;
        $path = '/product/' . rawurlencode($slug);
        return strlen($path) <= 2048 ? $path : null;
    }

    /** Only stored local raster media may cross into public presentation cards. */
    private static function imagePath(mixed $image, ?string $publicOrigin = null): ?string
    {
        if (!is_string($image) || $image === '' || strlen($image) > 2048
            || str_contains($image, '\\') || preg_match('/[\\x00-\\x20\\x7F]/', $image)) return null;
        // Parse an ASCII URL so PHP on every host preserves UTF-8 path bytes.
        if(!mb_check_encoding($image,'UTF-8'))return null;
        $encoded=preg_replace_callback('/[^\x00-\x7F]+/u',static fn(array $m):string=>rawurlencode($m[0]),$image);
        if($encoded===null)return null;
        $parts=parse_url($encoded);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
            || isset($parts['fragment'])) return null;
        if (isset($parts['host']) || isset($parts['scheme'])) {
            $origin = parse_url($publicOrigin ?? PublicSiteUrlService::origin());
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
                || !preg_match('/^[\\p{L}\\p{N}_][\\p{L}\\p{N}\\p{M}._ -]*$/uD', $segment)) return null;
            $segment = rawurlencode($segment);
        }
        unset($segment);
        $path = '/assets/images/' . implode('/', $segments);
        return strlen($path) <= 512 && preg_match('/\\.(?:avif|gif|jpe?g|png|webp)$/iD', $path) ? $path : null;
    }

    /** Share the server's bounded named-comparison resolver, using this injected public catalogue. */
    public function comparisonSlugs(string $question): array
    {
        if(!(new SettingsService())->modules(requireReadable:true)['shop'])return [];
        return array_column(AgentContextService::namedComparisonProducts($this->catalogue()->visible(),$question),'slug');
    }

    /** Exact selectors are raw slugs, never URLs, path fragments or a second search query. */
    private static function checkProductSelection(array $slugs,string $query,int $offset,int $limit): void
    {
        if(!array_is_list($slugs)||count($slugs)>3)throw new \InvalidArgumentException('Select at most three product slugs.');
        foreach($slugs as $slug) {
            if(!is_string($slug)||self::productPath($slug)===null)throw new \InvalidArgumentException('Use exact raw product slugs.');
        }
        if(count(array_unique($slugs,SORT_STRING))!==count($slugs)
            || ($slugs!==[]&&(trim($query)!==''||$offset!==0||$limit<count($slugs)))) {
            throw new \InvalidArgumentException('Exact selections require unique slugs, an empty query, offset 0 and a limit covering the selection.');
        }
    }

    /** Extra detail is published product content, bounded independently of the underlying record. */
    private static function selectedProductDetails(array $item, string $publicOrigin): array
    {
        $images=[];
        $gallery=$item['image_urls']??[];
        if(is_string($gallery))$gallery=preg_split('/[\r\n,]+/',$gallery)?:[];
        if(!is_array($gallery))$gallery=[];
        foreach(array_merge([$item['image_url']??null],array_slice($gallery,0,30)) as $image) {
            $path=self::imagePath($image,$publicOrigin);
            if($path!==null&&!in_array($path,$images,true))$images[]=$path;
            if(count($images)===3)break;
        }
        $highlights=[];
        foreach(array_slice(is_array($item['highlights']??null)?$item['highlights']:[],0,20) as $value) {
            if(!is_string($value))continue;
            $value=self::plainText($value,160);
            if($value!=='')$highlights[]=$value;
            if(count($highlights)===4)break;
        }
        $specifications=[];
        foreach(array_slice(is_array($item['specifications']??null)?$item['specifications']:[],0,30,true) as $label=>$value) {
            if(!is_string($label)||(!is_string($value)&&!is_int($value)&&!is_float($value)))continue;
            $label=self::plainText($label,80);$value=self::plainText((string)$value,240);
            if($label!==''&&$value!=='')$specifications[]=['label'=>$label,'value'=>$value];
            if(count($specifications)===6)break;
        }
        $details=[];
        if($images!==[]) {
            $details['imagePaths']=$images;
            $details['imageUrls']=array_map(static fn(string $path):string=>$publicOrigin.$path,$images);
        }
        if($highlights!==[])$details['highlights']=$highlights;
        if($specifications!==[])$details['specifications']=$specifications;
        return $details;
    }

    /** Canonical catalogue projection: visibility and current offer price come from ProductService. */
    public function products(string $query='', int $offset=0, int $limit=20,array $slugs=[]): array
    {
        self::checkPage($query,$offset,$limit);
        self::checkProductSelection($slugs,$query,$offset,$limit);
        if (!(new SettingsService())->modules(requireReadable:true)['shop']) {
            return ['products'=>[], 'total'=>0,'nextOffset'=>null];
        }
        $origin=PublicSiteUrlService::origin($this->publicUrl);
        $service=$this->catalogue();
        $visible=$service->visible();
        if($slugs===[]) {
            $matches=$service->discover($visible,trim($query),'');
        } else {
            $matches=[];
            foreach($slugs as $slug) {
                $exact=array_values(array_filter($visible,static fn(array $item):bool=>($item['slug']??null)===$slug));
                if(count($exact)>1)throw new \RuntimeException('Public product selection is ambiguous.');
                if($exact!==[])$matches[]=$exact[0];
            }
        }
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
                'productUrl'=>$origin.self::productPath((string)$item['slug']),
                'priceInr'=>$service->priceOf($item),
                'available'=>in_array($item['stock_status']??'in_stock',['in_stock','active'],true),
                'description'=>self::plainText((string)($item['description']??''),280),
            ];
            $imagePath = self::imagePath($item['image_url'] ?? null,$origin);
            if ($imagePath !== null) {
                $row['imagePath'] = $imagePath;
                $row['imageUrl'] = $origin.$imagePath;
            }
            if($slugs!==[])$row+=self::selectedProductDetails($item,$origin);
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

    /** Both authenticated Enterprise and public Support use this exact declaration. */
    public static function agentArticleDeclaration(): array
    {
        return ['name'=>'search_articles',
            'description'=>'Search published blog and customer help articles by keyword. Returns real titles and paths; never private documents or article bodies.',
            'parameters'=>['type'=>'object','properties'=>['query'=>['type'=>'string','maxLength'=>120]],
                'required'=>['query'],'additionalProperties'=>false]];
    }

    public static function agentReadArticleDeclaration(): array {
        return ['name'=>'read_published_article','description'=>'Read a bounded text excerpt from an exact currently published blog/help article slug. Use after article discovery to answer content questions. No private files.',
            'parameters'=>['type'=>'object','properties'=>['slug'=>['type'=>'string','pattern'=>'^[a-z0-9-]{1,120}$']],
                'required'=>['slug'],'additionalProperties'=>false]];
    }

    public function readArticleForAgent(array $args): array {
        if(array_keys($args)!==['slug']||!is_string($args['slug'])||!preg_match('/^[a-z0-9-]{1,120}$/D',$args['slug']))
            return ['error'=>'Invalid article slug.'];
        if(!(new SettingsService())->modules(requireReadable:true)['blog'])return ['error'=>'Public blog unavailable.'];
        $source=$this->articlesSource();$post=$source->find($args['slug']);
        if(!$source->isPublic($post))return ['error'=>'No published article found.'];
        $body=trim(html_entity_decode(strip_tags((string)($post['html']??'')),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        if($body==='')return ['error'=>'Published article text unavailable.'];
        $text=mb_substr($body,0,6000);
        return ['found'=>1,'articles'=>[['title'=>self::plainText((string)($post['title']??''),160),
            'path'=>'/blog/'.$args['slug'],'text'=>$text,'truncated'=>mb_strlen($body)>6000,
            'content_sha256'=>hash('sha256',$text)]]];
    }

    public function searchArticlesForAgent(array $args): array
    {
        if (array_keys($args)!==['query'] || !is_string($args['query']) ||
            !mb_check_encoding($args['query'],'UTF-8') || mb_strlen($args['query'])>120)
            return ['error'=>'Invalid lookup parameters.'];
        return $this->articlesForAgent($args['query']);
    }

    /** Preserve the existing agent tool response while reusing one retriever. */
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
