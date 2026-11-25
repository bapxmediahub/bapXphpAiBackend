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
        if (!$items) $items[] = ['title'=>'Contact form','detail'=>'Send a message through the website.','path'=>'/contact#contact-form'];
        $hours=[];
        foreach((array)($contact['hours']??[]) as $row)if(is_array($row))$hours[]=trim((string)($row['days']??'').' '.(string)($row['hours']??''));
        if($hours)$items[]=['title'=>'Hours','detail'=>mb_substr(implode(' · ',$hours),0,240),'path'=>'/contact'];
        return array_slice($items,0,$limit);
    }

    /** Native chat presentation follows actual model-selected evidence, not question words. */
    public function forAgentEvidence(array $knowledge,array $tools,string $answer): ?array {
        $slugs=array_slice(array_values(array_unique(array_filter((array)($knowledge['public_product_slugs']??[]),'is_string'))),0,3);
        $articlePaths=[];$subjects=[];$contact=false;
        foreach($tools as $tool){
            if(($tool['status']??'')!=='complete')continue;
            $safe=AgentTraceService::safeTool($tool);$e=$safe['evidence'];
            if($safe['name']==='get_public_contact'&&$e['source']==='public_contact'&&$e['result_count']===1)$contact=true;
            if($safe['name']==='list_public_services'&&$e['source']==='public_services'&&is_int($e['result_count'])&&$e['result_count']>=1&&$e['result_count']<=4)
                $subjects=array_merge($subjects,$e['service_subjects']??[]);
            if($safe['name']==='product_status')foreach($e['public_products']??[] as $source){
                $slug=substr($source['id'],8);
                if(str_contains($answer,'/product/'.$slug))$slugs[]=rawurldecode($slug);
            }
            if(in_array($safe['name'],['search_articles','read_published_article'],true))foreach($e['sources']??[] as $source){
                $path='/blog/'.substr($source['id'],8);
                if(str_contains($answer,$path))$articlePaths[]=$path;
            }
        }
        try{
            $slugs=array_slice(array_values(array_unique($slugs)),0,3);
            if($slugs!==[])return $this->present('products','',count($slugs),'auto',$slugs);
            if($articlePaths!==[]){
                $items=[];
                foreach(array_slice(array_unique($articlePaths),0,4) as $path){
                    $surface=$this->present('articles',str_replace('-',' ',substr($path,6)),4);
                    foreach($surface['messages'][0]['createSurface']['dataModel']['items']??[] as $item)
                        if($item['path']===$path)$items[$path]=$item;
                }
                if($items!==[])return self::buildSurface('articles',array_values($items));
            }
            if($subjects!==[]){
                $surface=$this->present('services','',4);
                $paths=array_map(static fn(string $subject):string=>'/contact?subject='.$subject.'#contact-form',$subjects);
                $items=array_values(array_filter($surface['messages'][0]['createSurface']['dataModel']['items']??[],static fn(array $item):bool=>in_array($item['path'],$paths,true)));
                if($items!==[])return self::buildSurface('services',$items);
            }
            if($contact)return $this->present('contact','',4);
        }catch(\Throwable){return null;}
        return null;
    }
}
