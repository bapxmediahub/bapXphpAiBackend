<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Read-only A2UI v1.0 Basic-catalog subset for both in-site agents and WebMCP.
 * The server chooses components and data from published public knowledge. No
 * model-supplied HTML/JS, private admin guide or actor-owned conversation enters
 * this surface. The view remains advisory; existing text answers are authoritative.
 */
final class PublicA2uiService
{
    public const VERSION = '1.0';
    public const CATALOG = 'https://a2ui.org/specification/v1_0/catalogs/basic/catalog.json';
    private const MAX_ITEMS = 4;
    private const PRODUCT_WORDS = '/\b(products?|shop|catalog(?:ue)?|buy|price|rings?|rudraksha|pendant|gemstone|stock)\b/i';
    private const ARTICLE_WORDS = '/\b(blogs?|articles?|guides?|pooja|puja|ritual|worship)\b/i';

    public function __construct(private ?PublicKnowledgeService $knowledge = null) {}

    public static function inputSchema(): array
    {
        return ['type'=>'object','additionalProperties'=>false,
            'properties'=>[
                'view'=>['type'=>'string','enum'=>['products','articles','contact','services']],
                'query'=>['type'=>'string','maxLength'=>120],
                'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>self::MAX_ITEMS],
            ], 'required'=>['view']];
    }

    public static function outputSchema(): array
    {
        // Exact nested component identity and trust checks are enforced by the
        // renderer, not delegated to arbitrary model/provider schema claims.
        return ['type'=>'object','additionalProperties'=>false,
            'properties'=>['messages'=>['type'=>'array','minItems'=>1,'maxItems'=>1,
                'items'=>['type'=>'object','additionalProperties'=>false,
                    'properties'=>[
                        'version'=>['type'=>'string','const'=>self::VERSION],
                        'createSurface'=>['type'=>'object',
                            'properties'=>[
                                'surfaceId'=>['type'=>'string','minLength'=>8,'maxLength'=>80],
                                'catalogId'=>['type'=>'string','const'=>self::CATALOG],
                                'components'=>['type'=>'array','minItems'=>2,'maxItems'=>40],
                                'dataModel'=>['type'=>'object'],
                            ], 'required'=>['surfaceId','catalogId','components','dataModel']],
                    ],'required'=>['version','createSurface'],
                ]]],
            'required'=>['messages']];
    }

    public function present(string $view, string $query = '', int $limit = self::MAX_ITEMS): array
    {
        if (!in_array($view, ['products','articles','contact','services'], true)
            || !mb_check_encoding($query,'UTF-8') || mb_strlen($query)>120 || $limit<1 || $limit>self::MAX_ITEMS) {
            throw new \InvalidArgumentException('Use products, articles, contact or services, a short query and limit 1..4.');
        }
        $source = $this->knowledge ?? new PublicKnowledgeService();
        $result = match ($view) {
            'products' => $source->products($query,0,$limit),
            'articles' => $source->articles($query,0,$limit),
            'services' => $source->services($query,0,$limit),
            'contact' => $source->contact(),
        };
        return $this->fromResult($view,$result,$limit);
    }

    /** Build cards from the exact public tool result, including its requested page. */
    public function fromResult(string $view,array $result,int $limit=self::MAX_ITEMS): array
    {
        if (!in_array($view,['products','articles','contact','services'],true) || $limit<1 || $limit>self::MAX_ITEMS) {
            throw new \InvalidArgumentException('Unknown public view or presentation limit.');
        }
        $rows=$view==='contact' ? self::contactItems($result,$limit) : ($result[$view]??[]);
        $items=[];
        foreach (array_slice(is_array($rows)?$rows:[],0,$limit) as $row) {
            if(!is_array($row))continue;
            if ($view==='products') {
                $title=trim((string)($row['name']??''));
                $path=(string)($row['path']??'');
                $amount=$row['priceInr']??null;
                if ($title==='' || mb_strlen($title)>200 ||
                    !str_starts_with($path,'/product/') || !self::safePublicPath($path) ||
                    !is_numeric($amount) || !is_finite((float)$amount) || (float)$amount<0) continue;
                $items[]=['title'=>$title,'detail'=>'₹'.number_format((float)$amount,2)
                    .(!empty($row['available'])?' · Available':' · Unavailable'),
                    'path'=>$path];
                $index=array_key_last($items);
                $items[$index]['description']=mb_substr(trim(strip_tags((string)($row['description']??''))),0,240);
                if (self::safeImagePath((string)($row['imagePath']??''))) $items[$index]['imagePath']=$row['imagePath'];
            } elseif ($view==='articles') {
                $title=trim((string)($row['title']??''));
                $path=(string)($row['path']??'');
                if ($title==='' || mb_strlen($title)>200 ||
                    !str_starts_with($path,'/blog/') || !self::safePublicPath($path))continue;
                $category=trim((string)($row['category']??''));
                $items[]=['title'=>$title,'detail'=>mb_substr($category,0,80),
                    'path'=>$path];
            } elseif ($view==='services') {
                $title=trim((string)($row['name']??''));$path=(string)($row['path']??'');
                if ($title==='' || !self::safePublicPath($path)) continue;
                $items[]=['title'=>mb_substr($title,0,200),
                    'detail'=>mb_substr(trim((string)($row['description']??'')),0,240),'path'=>$path];
            } else {
                $items[]=$row;
            }
        }
        return self::buildSurface($view,$items);
    }

    private static function buildSurface(string $view,array $items): array
    {
        $heading=match($view) {
            'products'=>'Products', 'articles'=>'Articles and guides',
            'contact'=>'Contact Sri Panchami Spiritual', 'services'=>'How we can help',
        };
        $components=[['id'=>'root','component'=>'Column','children'=>['heading']]];
        $components[]=['id'=>'heading','component'=>'Text','text'=>['@path'=>'/heading']];
        $children=['heading'];
        foreach($items as $i=>$item){
            $card="card_$i";$col="body_$i";$title="title_$i";$desc="detail_$i";
            $button="open_$i";$caption="open_label_$i";
            $children[]=$card;
            $components[]=['id'=>$card,'component'=>'Card','child'=>$col];
            $bodyChildren=[];
            if(!empty($item['imagePath'])) {
                $image="image_$i";$bodyChildren[]=$image;
                $components[]=['id'=>$image,'component'=>'Image','url'=>['@path'=>"/items/$i/imagePath"],
                    'fit'=>'contain','description'=>['@path'=>"/items/$i/title"]];
            }
            array_push($bodyChildren,$title,$desc);
            if(!empty($item['description'])) {
                $description="description_$i";$bodyChildren[]=$description;
                $components[]=['id'=>$description,'component'=>'Text','variant'=>'caption',
                    'text'=>['@path'=>"/items/$i/description"]];
            }
            $bodyChildren[]=$button;
            $components[]=['id'=>$col,'component'=>'Column','children'=>$bodyChildren];
            $components[]=['id'=>$title,'component'=>'Text','text'=>['@path'=>"/items/$i/title"]];
            $components[]=['id'=>$desc,'component'=>'Text','variant'=>'caption','text'=>['@path'=>"/items/$i/detail"]];
            $components[]=['id'=>$caption,'component'=>'Text','text'=>match($view){'products'=>'View product','articles'=>'Read article','services'=>'Find out more','contact'=>'Contact page'}];
            $components[]=['id'=>$button,'component'=>'Button','child'=>$caption,
                'variant'=>'borderless',
                'action'=>['functionCall'=>['@call'=>'openUrl','args'=>['url'=>['@path'=>"/items/$i/path"]]]]];
        }
        if (!$items) {
            $children[]='empty';
            $components[]=['id'=>'empty','component'=>'Text','text'=>'No matching published '.$view.'.'];
        }
        $components[0]['children']=$children;
        return ['messages'=>[['version'=>self::VERSION,'createSurface'=>[
            'surfaceId'=>'bapx_'.$view.'_'.bin2hex(random_bytes(8)),
            'catalogId'=>self::CATALOG,
            'components'=>$components,
            'dataModel'=>['heading'=>$heading,'items'=>$items],
        ]]]];
    }

    /** Canonical same-site navigation only; public UI can never name owner routes. */
    public static function safePublicPath(string $path): bool
    {
        if(str_starts_with($path,'/product/')) {
            return strlen($path)<=2048&&self::safeEncodedSegment(substr($path,9));
        }
        return (bool)preg_match('~^/(?:blog/[a-z0-9-]{1,120}|shop|blog|temples|contact(?:\?subject=(?:product|temple|order|general))?(?:#contact-form)?|about|support)$~D',$path);
    }

    /** Match the canonical knowledge projection, including Unicode combining marks. */
    private static function safeEncodedSegment(string $segment): bool
    {
        if(!preg_match('/^[A-Za-z0-9%_.-]+$/D',$segment))return false;
        $decoded=rawurldecode($segment);
        return mb_check_encoding($decoded,'UTF-8')
            && (bool)preg_match('/^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/uD',$decoded);
    }

    public static function safeImagePath(string $path): bool
    {
        if(strlen($path)>512||!str_starts_with($path,'/assets/images/')
            ||!preg_match('/\.(?:avif|gif|jpe?g|png|webp)$/iD',$path))return false;
        foreach(explode('/',substr($path,15)) as $segment)if(!self::safeEncodedSegment($segment))return false;
        return true;
    }

    private static function contactItems(array $contact, int $limit): array
    {
        $items=[];
        foreach(array_slice((array)($contact['phones']??[]),0,2) as $phone) {
            if(!is_array($phone))continue;
            $items[]=['title'=>mb_substr((string)($phone['label']??'Phone'),0,200),
                'detail'=>mb_substr((string)($phone['number']??''),0,240),'path'=>'/contact'];
        }
        if(!empty($contact['email']))$items[]=['title'=>'Email','detail'=>mb_substr((string)$contact['email'],0,240),'path'=>'/contact#contact-form'];
        $hours=[];
        foreach((array)($contact['hours']??[]) as $row)if(is_array($row))$hours[]=trim((string)($row['days']??'').' '.(string)($row['hours']??''));
        if($hours)$items[]=['title'=>'Hours','detail'=>mb_substr(implode(' · ',$hours),0,240),'path'=>'/contact'];
        return array_slice($items,0,$limit);
    }

    /** Human phrasing is reduced to a bounded search, never replaced by an unrelated sample. */
    public static function questionQuery(string $question, bool $serviceSearch=false): string
    {
        $query=mb_strtolower(trim($question));
        $catalogueWords=$serviceSearch?'':'|products?|items?|blogs?|articles?|guides?';
        $query=preg_replace('/\b(?:please|can|could|would|you|your|me|us|tell|show|find|list|give|what|which|where|how|are|is|do|does|have|has|the|a|an|any|some|all|about|available|availability|current|price|prices|cost|buy|purchase|sell|offer|offers|provide|provides|carry|services?|there|and|of|for|on|with|help|more|need|want|kinds?|types?|options?'.$catalogueWords.')\b/iu',' ',$query)??'';
        if($serviceSearch)$query=preg_replace('/\b(product|order|temple)s\b/u','$1',$query)??$query;
        $query=preg_replace('/\brings\b/u','ring',$query)??$query;
        $query=preg_replace('/\bpendants\b/u','pendant',$query)??$query;
        $query=trim((string)preg_replace('/[^\p{L}\p{M}\p{N}]+/u',' ',$query));
        return mb_substr((string)preg_replace('/\s+/u',' ',$query),0,120);
    }

    /** Personal contact changes and past purchases are not public business enquiries. */
    private static function personalContactOrServiceQuestion(string $question): bool
    {
        return preg_match('/\b(?:my|our|his|her|their|customer(?:[\'’]s)?|recipient(?:[\'’]s)?)\s+(?:(?:account|profile|order|registered|saved|billing|shipping|personal)\s+){0,3}(?:e-?mail|phone|mobile|telephone|contact|address)\b/iu',$question)===1
            || preg_match('/\b(?:change|update|edit|correct|replace|remove|delete|verify|reset)\b[^?!.]{0,100}\b(?:e-?mail|phone|mobile|telephone|contact|address)\b/iu',$question)===1
            || preg_match('/\b(?:e-?mail|phone|mobile|telephone|contact)\b[^?!.]{0,60}\b(?:on|in|for|from|associated with)\s+(?:my|our|the customer(?:[\'’]s)?)\s+(?:account|profile|order)\b/iu',$question)===1
            || preg_match('/\b(?:e-?mail|phone|mobile|services?)\b[^?!.]{0,40}\b(?:did|have|had|do)\s+(?:i|we)\s+(?:use|used|give|provide|register|save|order|ordered|book|booked|buy|purchase|purchased)\b/iu',$question)===1
            || preg_match('/\b(?:my|our|his|her|their)\s+(?:(?:ordered|booked|purchased|current)\s+)?services?\b/iu',$question)===1
            || preg_match('/\b(?:email|call|phone|contact)\s+(?:me|us|him|her|them)\b/iu',$question)===1
            || preg_match('/(?:என்|எனது|என்னுடைய)\s+(?:மின்னஞ்சல்|தொலைபேசி|மொபைல்|தொடர்பு|சேவை)/u',$question)===1;
    }

    /** Only public references for the actual question or products shown in its answer. */
    public function forQuestion(string $question, array $knowledge = [], string $answer = ''): ?array
    {
        if(self::personalContactOrServiceQuestion($question))return null;
        // This hint is set by the owner controller only after checking completed public-tool evidence.
        $view=in_array($knowledge['public_business_view']??null,['contact','services'],true)
            ?$knowledge['public_business_view']:null;
        if($view===null) {
            if(preg_match('/\b(contact|e-?mail|phone|mobile|telephone|opening hours|call you)\b|தொடர்பு/iu',$question))$view='contact';
            elseif(preg_match('/\bservices?\b|சேவை/iu',$question))$view='services';
            elseif(preg_match('/\b(blogs?|articles?|guides?)\b|வலைப்பதிவு/iu',$question))$view='articles';
            elseif(preg_match(self::PRODUCT_WORDS,$question)||!empty($knowledge['public_product_slugs']))$view='products';
            elseif(preg_match(self::ARTICLE_WORDS,$question))$view='articles';
        }
        if($view===null&&preg_match('~/product/[A-Za-z0-9%_.-]{1,2039}(?![A-Za-z0-9%_.-])~',$answer))$view='products';
        if($view===null&&preg_match('~/blog/[a-z0-9-]{1,120}~',$answer))$view='articles';
        if($view===null)return null;
        try {
            $slugs=array_slice(array_values(array_filter((array)($knowledge['public_product_slugs']??[]),'is_string')),0,4);
            if($view==='products' && $slugs!==[]) {
                // Re-read only already-grounded public products and require an exact path.
                $items=[];
                foreach($slugs as $slug) {
                    if(!self::safePublicPath('/product/'.rawurlencode($slug)))continue;
                    $surface=$this->present('products',mb_substr(str_replace(['-','_'], ' ',$slug),0,120),4);
                    foreach($surface['messages'][0]['createSurface']['dataModel']['items'] as $item) {
                        if($item['path']==='/product/'.rawurlencode($slug))$items[$item['path']]=$item;
                    }
                }
                // Keep a single bounded, coherent surface, not one repeated result per lookup.
                return self::buildSurface('products',array_values($items));
            }
            if($view==='products' && preg_match_all('~(/product/[A-Za-z0-9%_.-]{1,2039}(?![A-Za-z0-9%_.-]))~',$answer,$matches)) {
                return $this->forQuestion($question,['public_product_slugs'=>array_map(
                    static fn(string $path):string=>rawurldecode(substr($path,9)),array_slice(array_unique($matches[1]),0,4))]);
            }
            if($view==='articles'&&preg_match_all('~(/blog/[a-z0-9-]{1,120})~',$answer,$matches)) {
                $items=[];
                foreach(array_slice(array_unique($matches[1]),0,4) as $path) {
                    $surface=$this->present('articles',str_replace('-',' ',substr($path,6)),4);
                    foreach($surface['messages'][0]['createSurface']['dataModel']['items'] as $item) {
                        if($item['path']===$path)$items[$path]=$item;
                    }
                }
                return self::buildSurface('articles',array_values($items));
            }
            return $this->present($view,$view==='contact'?'':self::questionQuery($question,$view==='services'),4);
        } catch (\Throwable $error) {
            error_log('A2UI public presentation unavailable ('.get_class($error).')');
            return null;
        }
    }

}
