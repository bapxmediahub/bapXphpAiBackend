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

    /** Canonical catalogue projection: visibility and current offer price come from ProductService. */
    public function products(string $query='', int $offset=0, int $limit=20): array
    {
        self::checkPage($query,$offset,$limit);
        if (!(new SettingsService())->modules(requireReadable:true)['shop']) {
            return ['products'=>[], 'total'=>0,'nextOffset'=>null];
        }
        $service=$this->catalogue();
        $matches=$service->discover($service->visible(),trim($query),'');
        $out=[];
        foreach(array_slice($matches,$offset,$limit) as $item){
            $slug=(string)($item['slug']??'');
            $name=trim((string)($item['name']??''));
            if($slug===''||$name==='')continue;
            $out[]=[
                'name'=>$name,
                'path'=>'/product/'.rawurlencode($slug),
                'priceInr'=>$service->priceOf($item),
                'available'=>in_array($item['stock_status']??'in_stock',['in_stock','active'],true),
            ];
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
