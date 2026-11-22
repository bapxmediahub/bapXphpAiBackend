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
    private const ARTICLE_WORDS = '/\b(blog|articles?|guides?|pooja|puja|ritual|worship)\b/i';

    public static function inputSchema(): array
    {
        return ['type'=>'object','additionalProperties'=>false,
            'properties'=>[
                'view'=>['type'=>'string','enum'=>['products','articles']],
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
                                'components'=>['type'=>'array','minItems'=>2,'maxItems'=>30],
                                'dataModel'=>['type'=>'object'],
                            ], 'required'=>['surfaceId','catalogId','components','dataModel']],
                    ],'required'=>['version','createSurface'],
                ]]],
            'required'=>['messages']];
    }

    public function present(string $view, string $query = '', int $limit = self::MAX_ITEMS): array
    {
        if (!in_array($view, ['products','articles'], true)
            || mb_strlen($query)>120 || $limit<1 || $limit>self::MAX_ITEMS) {
            throw new \InvalidArgumentException('Use products or articles, a short query and limit 1..4.');
        }
        $source = new PublicKnowledgeService();
        $result = $view==='products' ? $source->products($query,0,$limit)
                                     : $source->articles($query,0,$limit);
        $items=[];
        foreach ($result[$view] ?? [] as $row) {
            if ($view==='products') {
                $title=trim((string)($row['name']??''));
                $path=(string)($row['path']??'');
                $amount=$row['priceInr']??null;
                if ($title==='' || mb_strlen($title)>160 ||
                    !preg_match('~^/product/[A-Za-z0-9%_.-]{1,200}$~D',$path) ||
                    !is_numeric($amount) || (float)$amount<0) continue;
                $items[]=['title'=>$title,'detail'=>'₹'.number_format((float)$amount,2)
                    .(!empty($row['available'])?' · Available':' · Unavailable'),
                    'path'=>$path];
            } else {
                $title=trim((string)($row['title']??''));
                $path=(string)($row['path']??'');
                if ($title==='' || mb_strlen($title)>200 ||
                    !preg_match('~^/blog/[a-z0-9-]{1,120}$~D',$path))continue;
                $category=trim((string)($row['category']??''));
                $items[]=['title'=>$title,'detail'=>mb_substr($category,0,80),
                    'path'=>$path];
            }
        }
        $heading=$view==='products' ? 'Published products' : 'Published articles and guides';
        $components=[['id'=>'root','component'=>'Column','children'=>['heading']]];
        $components[]=['id'=>'heading','component'=>'Text','text'=>['@path'=>'/heading']];
        $children=['heading'];
        foreach($items as $i=>$item){
            $card="card_$i";$col="body_$i";$title="title_$i";$desc="detail_$i";
            $button="open_$i";$caption="open_label_$i";
            $children[]=$card;
            $components[]=['id'=>$card,'component'=>'Card','child'=>$col];
            $components[]=['id'=>$col,'component'=>'Column','children'=>[$title,$desc,$button]];
            $components[]=['id'=>$title,'component'=>'Text','text'=>['@path'=>"/items/$i/title"]];
            $components[]=['id'=>$desc,'component'=>'Text','variant'=>'caption','text'=>['@path'=>"/items/$i/detail"]];
            $components[]=['id'=>$caption,'component'=>'Text','text'=>'View '.$view];
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

    /** Only add a public presentation to a successful text answer when relevant. */
    public function forQuestion(string $question): ?array
    {
        $view=null;
        if (preg_match(self::PRODUCT_WORDS,$question)) $view='products';
        elseif (preg_match(self::ARTICLE_WORDS,$question)) $view='articles';
        if ($view===null) return null;
        // General browsing gets a predictable public sample. Explicit names can
        // be refined later without making model prose an executable UI schema.
        try {
            return $this->present($view,'',3);
        } catch (\Throwable $error) {
            error_log('A2UI public presentation unavailable ('.get_class($error).')');
            return null;
        }
    }
}
