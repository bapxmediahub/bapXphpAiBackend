<?php
namespace App\Services;

/** Local, deterministic quality insights; no paid analytics or evaluator API. */
final class SiteInsightsService {
    public static function catalogSeo(array $products, array $articles): array {
        $issues = []; $checked = 0;
        foreach ($products as $product) {
            if (!empty($product['is_hidden'])) continue;
            $checked++;
            $name = trim((string)($product['name']??''));
            $slug = trim((string)($product['slug']??''));
            $description = trim(strip_tags((string)($product['description']??'')));
            if ($name === '' || $slug === '') $issues[] = ['type'=>'product','item'=>$name?:($slug?:'Untitled'),'issue'=>'Missing title or slug'];
            if (mb_strlen($description)<60) $issues[] = ['type'=>'product','item'=>$name?:$slug,'issue'=>'Short or missing description'];
            if (empty($product['image_url']) && empty($product['image_urls'])) $issues[] = ['type'=>'product','item'=>$name?:$slug,'issue'=>'Missing product image'];
        }
        foreach ($articles as $article) {
            $title = trim((string)($article['title']??''));
            $slug = trim((string)($article['slug']??''));
            $summary = trim((string)($article['excerpt']??$article['summary']??''));
            if ($title==='' || $slug==='') $issues[] = ['type'=>'article','item'=>$title?:($slug?:'Untitled'),'issue'=>'Missing title or slug'];
            if (mb_strlen($summary)<50) $issues[] = ['type'=>'article','item'=>$title?:$slug,'issue'=>'Short or missing excerpt'];
        }
        return ['checked_products'=>$checked,'checked_articles'=>count($articles),
            'issues'=>array_slice($issues,0,75),'total_issues'=>count($issues)];
    }

    public static function commerce(array $orders, string $from, string $to): array {
        $sales = StoreStatsService::sales($orders,$from,$to);
        return [
            'orders'=>array_sum(array_column($sales,'orders')),
            'units'=>array_sum(array_column($sales,'units')),
            'revenue'=>round(array_sum(array_column($sales,'net')),2),
            'refunded'=>round(array_sum(array_column($sales,'refunded')),2),
            'top_products'=>StoreStatsService::topProducts($orders,$from,$to),
        ];
    }
}
