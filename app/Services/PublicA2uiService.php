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
    private const LAYOUTS = ['auto','grid','list','detail','compare'];
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
                'layout'=>['type'=>'string','enum'=>self::LAYOUTS],
                'slugs'=>['type'=>'array','maxItems'=>3,'uniqueItems'=>true,
                    'items'=>['type'=>'string','minLength'=>1,'maxLength'=>2039]],
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
                                'components'=>['type'=>'array','minItems'=>2,'maxItems'=>96],
                                'dataModel'=>['type'=>'object'],
                            ], 'required'=>['surfaceId','catalogId','components','dataModel']],
                    ],'required'=>['version','createSurface'],
                ]]],
            'required'=>['messages']];
    }

    public function present(string $view, string $query = '', int $limit = self::MAX_ITEMS, string $layout = 'auto', array $slugs = []): array
    {
        if (!in_array($view, ['products','articles','contact','services'], true)
            || !mb_check_encoding($query,'UTF-8') || mb_strlen($query)>120 || $limit<1 || $limit>self::MAX_ITEMS
            || !in_array($layout,self::LAYOUTS,true) || ($view!=='products'&&($layout!=='auto'||$slugs!==[]))
            || !array_is_list($slugs) || count($slugs)>3 || count($slugs)>$limit
            || ($slugs!==[]&&$query!=='') || ($layout==='compare'&&count($slugs)<2)
            || ($layout==='detail'&&count($slugs)>1)) {
            throw new \InvalidArgumentException('Use products, articles, contact or services, a short query and limit 1..4.');
        }
        $seen=[];
        foreach($slugs as $slug) {
            if(!is_string($slug)||!self::safePublicPath('/product/'.rawurlencode($slug))||isset($seen[$slug])) {
                throw new \InvalidArgumentException('Choose up to three distinct published product slugs.');
            }
            $seen[$slug]=true;
        }
        $source = $this->knowledge ?? new PublicKnowledgeService();
        $result = match ($view) {
            'products' => $source->products($query,0,$limit,$slugs),
            'articles' => $source->articles($query,0,$limit),
            'services' => $source->services($query,0,$limit),
            'contact' => $source->contact(),
        };
        return $this->fromResult($view,$result,$limit,$layout,count($slugs));
    }

    /** Build cards from the exact public tool result, including its requested page. */
    public function fromResult(string $view,array $result,int $limit=self::MAX_ITEMS,string $layout='auto',int $requestedCount=0): array
    {
        if (!in_array($view,['products','articles','contact','services'],true) || $limit<1 || $limit>self::MAX_ITEMS
            || !in_array($layout,self::LAYOUTS,true) || ($view!=='products'&&($layout!=='auto'||$requestedCount!==0))
            || $requestedCount<0 || $requestedCount>3 || $requestedCount>$limit) {
            throw new \InvalidArgumentException('Unknown public view or presentation limit.');
        }
        // Contact is one panel; its requested card limit must not hide email or hours.
        $itemLimit=$view==='contact'?self::MAX_ITEMS:$limit;
        $rows=$view==='contact' ? self::contactItems($result,$itemLimit) : ($result[$view]??[]);
        $items=[];
        foreach (array_slice(is_array($rows)?$rows:[],0,$itemLimit) as $row) {
            if(!is_array($row))continue;
            if ($view==='products') {
                $title=trim((string)($row['name']??''));
                $path=(string)($row['path']??'');
                $amount=$row['priceInr']??null;
                if ($title==='' || mb_strlen($title)>200 ||
                    !str_starts_with($path,'/product/') || !self::safePublicPath($path) ||
                    !is_numeric($amount) || !is_finite((float)$amount) || (float)$amount<0 || (float)$amount>1e15) continue;
                if(in_array($path,array_column($items,'path'),true))continue;
                if(!is_bool($row['available']??null))continue;
                $price='₹'.number_format((float)$amount,2);$availability=$row['available']?'Available':'Unavailable';
                $items[]=['title'=>$title,'detail'=>$price.' · '.$availability,
                    'priceInr'=>(float)$amount,'priceLabel'=>$price,'available'=>$row['available'],
                    'availability'=>$availability,'path'=>$path];
                $index=array_key_last($items);
                $items[$index]['description']=mb_substr(trim(strip_tags((string)($row['description']??''))),0,240);
                if (self::safeImagePath((string)($row['imagePath']??''))) $items[$index]['imagePath']=$row['imagePath'];
                $images=isset($items[$index]['imagePath'])?[$items[$index]['imagePath']]:[];
                foreach(array_slice((array)($row['imagePaths']??[]),0,3) as $image) {
                    if(is_string($image)&&self::safeImagePath($image)&&!in_array($image,$images,true))$images[]=$image;
                }
                $items[$index]['imagePaths']=array_slice($images,0,3);
                if($images!==[])$items[$index]['imagePath']=$images[0];
                $items[$index]['highlights']=[];
                foreach(array_slice((array)($row['highlights']??[]),0,4) as $highlight) {
                    if(is_string($highlight)&&trim($highlight)!=='')$items[$index]['highlights'][]=mb_substr(trim(strip_tags($highlight)),0,160);
                }
                $items[$index]['specifications']=[];
                foreach(array_slice((array)($row['specifications']??[]),0,6) as $spec) {
                    if(!is_array($spec)||!is_string($spec['label']??null)||!is_string($spec['value']??null))continue;
                    $label=mb_substr(trim(strip_tags($spec['label'])),0,80);$value=mb_substr(trim(strip_tags($spec['value'])),0,240);
                    if($label!==''&&$value!=='')$items[$index]['specifications'][]=['label'=>$label,'value'=>$value];
                }
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
                if ($title==='' || !preg_match('~^/contact\?subject=(?:product|temple|order|general)#contact-form$~D',$path)) continue;
                $items[]=['title'=>mb_substr($title,0,200),
                    'detail'=>mb_substr(trim((string)($row['description']??'')),0,240),'path'=>$path];
            } else {
                $items[]=$row;
            }
        }
        if(($layout==='detail'&&count($items)>1)||($layout==='compare'&&count($items)>3)
            ||($requestedCount>0&&count($items)>$requestedCount)) {
            throw new \InvalidArgumentException('Choose one product for detail or two to three for comparison.');
        }
        return self::buildSurface($view,$items,$layout,$requestedCount);
    }

    /** Layout is selected by the server from a fixed family, never supplied as CSS. */
    private static function buildSurface(string $view,array $items,string $layout='auto',int $requestedCount=0): array
    {
        $count=count($items);
        $presentation=match($view) {
            'products'=>match(true) {
                $layout==='compare'&&$count>=2&&$count<=3=>'product_compare',
                $layout==='list'=>'product_list',
                $layout==='grid'=>'product_grid',
                $count===1=>'product_detail',
                default=>'product_grid',
            },
            'articles'=>'article_list','contact'=>'contact_panel','services'=>'service_tiles',
        };
        if($presentation==='product_compare') {
            // Repeat the same real fields on every selected product. An absent
            // field is explicitly unavailable, never inferred from another item.
            $labels=[];
            foreach($items as $item)foreach($item['specifications']??[] as $spec) {
                if(!in_array($spec['label'],$labels,true)&&count($labels)<6)$labels[]=$spec['label'];
            }
            foreach($items as &$item) {
                $values=array_column($item['specifications']??[],'value','label');
                $item['specifications']=array_map(static fn(string $label):array=>[
                    'label'=>$label,'value'=>$values[$label]??'Not listed',
                ],$labels);
            }
            unset($item);
        }
        $heading=match($view) {
            'products'=>$presentation==='product_compare'?'Compare selected products':($presentation==='product_detail'?'Product details':'Products'),
            'articles'=>'Articles and guides','contact'=>'Contact Sri Panchami Spiritual','services'=>'How we can help',
        };
        $components=[['id'=>'root','component'=>'Column','children'=>['heading','items']],
            ['id'=>'heading','component'=>'Text','text'=>['@path'=>'/heading']],
            ['id'=>'items','component'=>'Column','children'=>[]]];
        $notice=$layout==='compare'&&$presentation!=='product_compare'
            ?'A comparison needs two or three matching published products.':'';
        if($requestedCount>$count) {
            $notice='Showing '.$count.' of '.$requestedCount.' selected products. Some selections have no matching published product.';
            if($layout==='compare'&&$count<2)$notice.=' A comparison is unavailable.';
        }
        if($notice!=='') {
            $components[0]['children']=['heading','notice','items'];
            self::text($components,'notice','/notice',true);
        }
        if($view==='contact'&&$items!==[]) {
            $components[2]['children']=['contact_card'];
            $components[]=['id'=>'contact_card','component'=>'Card','child'=>'contact_body'];
            $children=[];
            foreach($items as $i=>$item) {
                $id='contact_'.$i;$children[]=$id;
                self::pair($components,$id,"/items/$i/title","/items/$i/detail");
            }
            $children[]='contact_open';
            self::link($components,'contact_open','Open enquiry form','/contact#contact-form');
            $components[]=['id'=>'contact_body','component'=>'Column','children'=>$children];
        } else foreach($items as $i=>$item) {
            $card="card_$i";$body="body_$i";$children=[];
            $components[2]['children'][]=$card;
            $components[]=['id'=>$card,'component'=>'Card','child'=>$body];
            if(!empty($item['imagePath'])) {
                $children[]="image_$i";
                $components[]=['id'=>"image_$i",'component'=>'Image','url'=>['@path'=>"/items/$i/imagePath"],
                    'fit'=>'contain','description'=>['@path'=>"/items/$i/title"]];
            }
            if($presentation==='product_detail'&&count($item['imagePaths']??[])>1) {
                $extra=[];
                foreach(array_slice($item['imagePaths'],1,2,true) as $j=>$image) {
                    $extra[]="extra_image_{$i}_$j";
                    $components[]=['id'=>"extra_image_{$i}_$j",'component'=>'Image',
                        'url'=>['@path'=>"/items/$i/imagePaths/$j"],'fit'=>'contain',
                        'description'=>['@path'=>"/items/$i/title"]];
                }
                $children[]="extra_images_$i";
                $components[]=['id'=>"extra_images_$i",'component'=>'Column','children'=>$extra];
            }
            $children[]="title_$i";self::text($components,"title_$i","/items/$i/title");
            if($view==='products') {
                $children[]="price_$i";self::text($components,"price_$i","/items/$i/priceLabel");
                $children[]="availability_$i";self::text($components,"availability_$i","/items/$i/availability",true);
            } else {
                $children[]="detail_$i";self::text($components,"detail_$i","/items/$i/detail",true);
            }
            if(!empty($item['description'])) {
                $children[]="description_$i";self::text($components,"description_$i","/items/$i/description",true);
            }
            if($presentation==='product_detail'&&!empty($item['highlights'])) {
                $highlights=[];
                foreach($item['highlights'] as $j=>$highlight) {
                    $highlights[]="highlight_{$i}_$j";self::text($components,"highlight_{$i}_$j","/items/$i/highlights/$j");
                }
                $children[]="highlights_$i";
                $components[]=['id'=>"highlights_$i",'component'=>'Column','children'=>$highlights];
            }
            if(in_array($presentation,['product_detail','product_compare'],true)&&!empty($item['specifications'])) {
                $specs=[];
                foreach($item['specifications'] as $j=>$spec) {
                    $id="spec_{$i}_$j";$specs[]=$id;
                    self::pair($components,$id,"/items/$i/specifications/$j/label","/items/$i/specifications/$j/value");
                }
                $children[]="specifications_$i";
                $components[]=['id'=>"specifications_$i",'component'=>'Column','children'=>$specs];
            }
            $children[]="open_$i";
            $label=match($view){'products'=>'View product','articles'=>'Read article','services'=>'Ask about '.$item['title']};
            self::link($components,"open_$i",mb_substr($label,0,80),['@path'=>"/items/$i/path"]);
            $components[]=['id'=>$body,'component'=>'Column','children'=>$children];
        }
        if(!$items) {
            $components[2]['children']=['empty'];
            $components[]=['id'=>'empty','component'=>'Text','text'=>'No matching published '.$view.'.'];
        }
        return ['messages'=>[['version'=>self::VERSION,'createSurface'=>[
            'surfaceId'=>'bapx_'.$view.'_'.bin2hex(random_bytes(8)),'catalogId'=>self::CATALOG,
            'components'=>$components,'dataModel'=>['heading'=>$heading,'presentation'=>$presentation,'notice'=>$notice,'items'=>$items],
        ]]]];
    }

    private static function text(array &$components,string $id,string $path,bool $caption=false): void
    {
        $component=['id'=>$id,'component'=>'Text','text'=>['@path'=>$path]];
        if($caption)$component['variant']='caption';
        $components[]=$component;
    }

    private static function pair(array &$components,string $id,string $label,string $value): void
    {
        $components[]=['id'=>$id,'component'=>'Row','children'=>[$id.'_label',$id.'_value']];
        self::text($components,$id.'_label',$label);self::text($components,$id.'_value',$value);
    }

    private static function link(array &$components,string $id,string $label,string|array $path): void
    {
        $components[]=['id'=>$id.'_label','component'=>'Text','text'=>$label];
        $components[]=['id'=>$id,'component'=>'Button','child'=>$id.'_label','variant'=>'borderless',
            'action'=>['functionCall'=>['@call'=>'openUrl','args'=>['url'=>$path]]]];
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
            $label=trim((string)($phone['label']??'Phone'));
            if(preg_match('/^[+()\d\s-]+$/D',$label))$label='Phone '.(count($items)+1);
            $items[]=['title'=>mb_substr($label,0,200),
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
        $aggregate=SupportBotService::aggregateToolForQuestion($question);
        if($aggregate==='public_catalogue_summary'||($aggregate==='public_best_sellers'&&empty($knowledge['public_product_slugs'])))return null;
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
            $slugs=array_slice(array_values(array_unique(array_filter((array)($knowledge['public_product_slugs']??[]),'is_string'))),0,3);
            $comparison=$view==='products'&&AgentContextService::isProductComparison($question);
            if($comparison) {
                // The same canonical name resolver supplies native Support's
                // facts before generation and the public card selection here.
                $slugs=($this->knowledge??new PublicKnowledgeService())->comparisonSlugs($question);
            }
            if($view==='products' && $slugs!==[]) {
                // Exact published selections enable detail/comparison without an
                // all-token "A versus B" search or unrelated catalogue samples.
                $slugs=array_values(array_filter($slugs,static fn(string $slug):bool=>self::safePublicPath('/product/'.rawurlencode($slug))));
                if($slugs===[])return null;
                $layout=count($slugs)>=2&&$comparison?'compare':'auto';
                return $this->present('products','',max(1,count($slugs)),$layout,$slugs);
            }
            if($view==='products' && preg_match_all('~(/product/[A-Za-z0-9%_.-]{1,2039}(?![A-Za-z0-9%_.-]))~',$answer,$matches)) {
                return $this->forQuestion($question,['public_product_slugs'=>array_map(
                    static fn(string $path):string=>rawurldecode(substr($path,9)),array_slice(array_unique($matches[1]),0,3))]);
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
