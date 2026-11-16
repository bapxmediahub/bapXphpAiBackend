<?php
namespace App\Services;
use App\Services\MarkdownRenderer;
final class BlogService {
    private string $postsDir;
    private string $categoriesFile;
    private ?\DateTimeImmutable $now;
    private const MAX_POST_BYTES=1048576;
    public function __construct(?string $postsDir=null, ?\DateTimeImmutable $now=null) {
        $this->postsDir = $postsDir ?? app_path('content/blog/posts');
        $this->now=$now;
        $this->categoriesFile = app_path('content/blog/categories.yaml');
        if (!is_dir($this->postsDir)) mkdir($this->postsDir, 0775, true);
    }
    /** Categories that disappear with their module, so their posts disappear too. */
    private const MODULE_CATEGORIES = [
        'shop' => ['shop', 'products', 'orders'],
    ];
    // These records remain available in the owner editor for audit and an explicit
    // future decision. They must not keep attracting a product-led storefront into
    // astrology search results or public category navigation.
    private const RETIRED_PUBLIC_CATEGORIES = ['astrology'];

    /**
     * @param bool $all true for the admin, which must see hidden posts to unhide them.
     *
     * A post whose category belongs to a switched-off module is withheld from the site.
     */
    public function all(bool $all = false): array {
        $posts = [];
        foreach (glob($this->postsDir . '/*.md') ?: [] as $file) {
            $post = $this->parseFile($file);
            if (!$post) continue;
            if (!$all && !$this->isPublic($post)) continue;
            $posts[] = $post;
        }
        usort($posts, fn($a, $b) => strcmp($b['published_at'] ?? '', $a['published_at'] ?? ''));
        return $posts;
    }

    /**
     * A few legacy post frontmatter entries point at images never shipped with the
     * site. Resolve local media against actual files, including persistent uploads,
     * before showing a thumbnail or publishing an og:image URL. Keep the original
     * frontmatter intact so adding the intended art later restores it automatically.
     */
    public static function resolvedImageUrl(array $post): string {
        $fallback = '/assets/images/hero-temple-bg.webp';
        $source = trim((string)($post['image'] ?? $post['og_image'] ?? ''));
        if ($source === '') return $fallback;
        $parts = parse_url($source);
        if (!is_array($parts)) return $fallback;
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme !== '' && $scheme !== 'https') return $fallback;
        $host = strtolower((string)($parts['host'] ?? ''));
        $siteHost = strtolower((string)(parse_url((string)(getenv('APP_URL') ?: 'https://sripanchamispiritual.com'), PHP_URL_HOST) ?: ''));
        if ($host !== '' && !in_array($host, array_filter([$siteHost, 'www.' . $siteHost]), true)) {
            return $scheme === 'https' ? $source : $fallback;
        }
        $path = (string)($parts['path'] ?? '');
        if (!preg_match('~^/assets/images/[a-z0-9_/.-]+\.(?:webp|png|jpe?g|gif)$~i', $path)
            || str_contains($path, '..')) return $fallback;
        $asset = app_path(ltrim($path, '/'));
        $persistent = MediaService::storedPathForUrl($path);
        return (is_file($asset) && is_readable($asset)) || $persistent !== null ? $path : $fallback;
    }

    /** Search within the already filtered public posts without changing their order. */
    public function search(array $posts, string $query): array {
        $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return $posts;

        return array_values(array_filter($posts, static function (array $post) use ($words): bool {
            $text = implode(' ', [
                $post['title'] ?? '',
                $post['excerpt'] ?? '',
                $post['keywords'] ?? '',
                html_entity_decode(strip_tags($post['html'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ]);
            foreach ($words as $word) {
                if (mb_stripos($text, $word) === false) return false;
            }
            return true;
        }));
    }

    public function categoryIsDisabled(string $category): bool {
        $category = strtolower(trim($category));
        if ($category === '') return false;
        foreach (self::MODULE_CATEGORIES as $module => $categories) {
            if (in_array($category, $categories, true) && !module_on($module)) return true;
        }
        return false;
    }
    public function categoryIsRetiredPublic(string $category): bool {
        return in_array(strtolower(trim($category)), self::RETIRED_PUBLIC_CATEGORIES, true);
    }
    public function find(string $slug): ?array {
        if(!self::validSlug($slug))return null;
        $file=$this->postsDir.'/'.$slug.'.md';
        return !is_link($this->postsDir)&&!is_link($file)&&is_file($file)?$this->parseFile($file):null;
    }
    public function isPublic(?array $post): bool {
        return $post!==null && $this->publicationState($post)==='published';
    }
    public function publicationState(array $post): string {
        if(!filter_var($post['published']??false,FILTER_VALIDATE_BOOLEAN))return 'hidden';
        if($this->categoryIsDisabled((string)($post['category']??''))||$this->categoryIsRetiredPublic((string)($post['category']??'')))return 'withheld';
        try{$date=self::publicationDate((string)($post['published_at']??''));}
        catch(\InvalidArgumentException){return 'invalid_date';}
        return $date!==null && $date>($this->now??new \DateTimeImmutable('now'))?'scheduled':'published';
    }
    private static function publicationDate(string $value): ?\DateTimeImmutable {
        $value=trim($value);if($value==='')return null;
        $format=preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)?'!Y-m-d':
            (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',$value)?'Y-m-d\TH:i:sP':null);
        if($format===null)throw new \InvalidArgumentException('Use a valid publication date.');
        $date=\DateTimeImmutable::createFromFormat($format,$value,new \DateTimeZone('Asia/Kolkata'));
        $errors=\DateTimeImmutable::getLastErrors();
        if($date===false||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0)))throw new \InvalidArgumentException('Use a valid publication date.');
        return $date;
    }
    private static function validSlug(string $slug): bool {
        return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,159}$/D',$slug);
    }

    public function categories(bool $includeRetired = true): array {
        if (!is_file($this->categoriesFile)) return [];
        $yaml = @file_get_contents($this->categoriesFile);
        if ($yaml === false || $yaml === '') return [];
        $categories = $this->parseYamlList($yaml);
        if ($includeRetired) return $categories;
        return array_values(array_filter($categories, fn(array $category): bool => !$this->categoryIsRetiredPublic((string)($category['slug'] ?? ''))));
    }
    /** Compare-and-swap is shared by editor, CLI and future adapters; LOCK_EX alone is not stale-write protection. */
    public function save(array $data): array {
        $slug=is_string($data['slug']??null)?trim($data['slug']):'';
        if($slug==='')$slug=$this->slugify((string)($data['title']??'untitled'));
        if(!self::validSlug($slug))throw new \InvalidArgumentException('Use a valid article slug.');
        if(isset($data['original_slug'])&&$data['original_slug']!==''&&$data['original_slug']!==$slug)throw new BlogConflictException('The existing article URL cannot be changed by this save.');
        $content=$data['content']??'';
        if(!is_string($content)||strlen($content)>self::MAX_POST_BYTES||str_contains($content,"\0")||!mb_check_encoding($content,'UTF-8'))throw new \InvalidArgumentException('Article content is invalid or too large.');
        self::publicationDate((string)($data['published_at']??''));
        $data['slug']=$slug;$data['published']=filter_var($data['published']??true,FILTER_VALIDATE_BOOLEAN);
        $expected=$data['expected_revision']??$data['_revision']??'new';
        if(!is_string($expected)||($expected!=='new'&&!preg_match('/^[a-f0-9]{64}$/D',$expected)))throw new BlogConflictException('Reload the article before saving.');
        $frontmatter='';
        foreach(['title','slug','type','category','published','published_at','updated_at','summary','order','excerpt','author','seo_title','seo_description','keywords','og_image','image_alt','source_url','template'] as $key){
            if(!isset($data[$key])||$data[$key]==='')continue;$value=$data[$key];
            if(!is_scalar($value)||strlen((string)$value)>16000)throw new \InvalidArgumentException('Invalid article metadata.');
            $encoded=is_bool($value)?($value?'true':'false'):json_encode((string)$value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $frontmatter.=$key.': '.$encoded."\n";
        }
        $md="---\n".$frontmatter."---\n\n".$content;
        if(strlen($md)>self::MAX_POST_BYTES)throw new \InvalidArgumentException('Article exceeds the storage limit.');
        return $this->withWriteLock(function()use($slug,$expected,$md):array{
            $file=$this->postsDir.'/'.$slug.'.md';$this->assertRevision($file,$expected);
            $temporary=$this->postsDir.'/.post-'.bin2hex(random_bytes(12)).'.tmp';
            try{
                $handle=@fopen($temporary,'x');if(!$handle)throw new \RuntimeException('Unable to prepare article save.');
                try{if(fwrite($handle,$md)!==strlen($md)||!fflush($handle))throw new \RuntimeException('Article write incomplete.');}
                finally{fclose($handle);}
                @chmod($temporary,0644);
                if(!rename($temporary,$file))throw new \RuntimeException('Unable to publish the saved article file.');
                clearstatcache(true,$file);
                return $this->parseFile($file)??throw new \RuntimeException('Saved article could not be read back.');
            }finally{if(is_file($temporary))unlink($temporary);}
        });
    }
    public function delete(string $slug,?string $expectedRevision=null): void {
        if(!self::validSlug($slug))throw new \InvalidArgumentException('Invalid article slug.');
        $this->withWriteLock(function()use($slug,$expectedRevision):array{
            $file=$this->postsDir.'/'.$slug.'.md';$this->assertRevision($file,$expectedRevision??'');
            if(!is_file($file)||!unlink($file))throw new \RuntimeException('Article could not be deleted.');return [];
        });
    }
    private function assertRevision(string $file,string $expected): void {
        if(is_link($file))throw new \RuntimeException('Linked article paths are not supported.');
        clearstatcache(true,$file);
        if(is_file($file)){
            $current=hash_file('sha256',$file);
            if($expected==='new'||$current===false||!hash_equals($current,$expected))throw new BlogConflictException('This article changed after you opened it. Your edit was not saved.');
        }elseif($expected!=='new')throw new BlogConflictException('This article was removed or replaced after you opened it.');
    }
    private function withWriteLock(callable $operation): array {
        if(is_link($this->postsDir)||!is_dir($this->postsDir))throw new \RuntimeException('Article storage unavailable.');
        $path=$this->postsDir.'/.blog-write.lock';if(is_link($path))throw new \RuntimeException('Invalid article lock path.');
        $lock=@fopen($path,'c');if(!$lock)throw new \RuntimeException('Article write lock unavailable.');
        try{@chmod($path,0600);if(!flock($lock,LOCK_EX))throw new \RuntimeException('Article write lock unavailable.');return $operation();}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function saveCategory(array $data): void {
        $cats = $this->categories();
        $found = false;
        foreach ($cats as &$c) {
            if (($c['slug'] ?? '') === ($data['slug'] ?? '')) { $c = $data; $found = true; break; }
        }
        if (!$found) $cats[] = $data;
        $yaml = "categories:\n";
        foreach ($cats as $c) {
            $yaml .= "  - slug: " . ($c['slug'] ?? '') . "\n";
            $yaml .= "    name: " . ($c['name'] ?? '') . "\n";
            $yaml .= "    description: " . ($c['description'] ?? '') . "\n";
        }
        file_put_contents($this->categoriesFile, $yaml, LOCK_EX);
    }
    public function deleteCategory(string $slug): void {
        $cats = array_values(array_filter($this->categories(), fn($c) => ($c['slug'] ?? '') !== $slug));
        $yaml = "categories:\n";
        foreach ($cats as $c) {
            $yaml .= "  - slug: " . ($c['slug'] ?? '') . "\n";
            $yaml .= "    name: " . ($c['name'] ?? '') . "\n";
            $yaml .= "    description: " . ($c['description'] ?? '') . "\n";
        }
        file_put_contents($this->categoriesFile, $yaml, LOCK_EX);
    }
    private function parseFile(string $file): ?array {
        if(is_link($file)||!is_file($file)||filesize($file)>self::MAX_POST_BYTES)return null;
        $raw=@file_get_contents($file);if(!is_string($raw)||$raw===''||!mb_check_encoding($raw,'UTF-8')||str_contains($raw,"\0"))return null;
        $meta=[];$content=$raw;
        if(preg_match('/\A---\R(.*?)\R---(?:\R|\z)(.*)\z/s',$raw,$parts)){
            $content=ltrim($parts[2],"\r\n");
            foreach(explode("\n",$parts[1]) as $line){
                if(!preg_match('/^([a-z_]+):\s*(.*)$/D',rtrim($line,"\r"),$match))continue;
                $value=trim($match[2]);
                if($value==='true')$value=true;elseif($value==='false')$value=false;
                elseif(str_starts_with($value,'"')){try{$decoded=json_decode($value,true,512,JSON_THROW_ON_ERROR);$value=is_scalar($decoded)?$decoded:$value;}catch(\JsonException){$value=trim($value,'"');}}
                elseif(str_starts_with($value,"'"))$value=trim($value,"'");
                elseif(is_numeric($value))$value=(float)$value;
                $meta[$match[1]]=$value;
            }
        }
        // Filename is the canonical identity, never a frontmatter path selector.
        $meta['slug']=pathinfo($file,PATHINFO_FILENAME);$meta['content']=$content;
        $meta['published']=filter_var($meta['published']??true,FILTER_VALIDATE_BOOLEAN);
        $meta['_revision']=hash('sha256',$raw);
        $meta['_publication_state']=$this->publicationState($meta);
        $meta['html']=(new MarkdownRenderer())->render($content);return $meta;
    }
    private function parseYamlList(string $yaml): array {
        $items = [];
        $current = null;
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^\s*-\s*slug:\s*(.+)$/', $line, $m)) {
                if ($current) $items[] = $current;
                $current = ['slug' => trim($m[1])];
            } elseif ($current && preg_match('/^\s+(name|description):\s*(.+)$/', $line, $m)) {
                $current[$m[1]] = trim($m[2]);
            }
        }
        if ($current) $items[] = $current;
        return $items;
    }
    private function slugify(string $text): string {
        return strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', preg_replace('/[^\w\s-]/u', '', $text)), '-'));
    }
}
