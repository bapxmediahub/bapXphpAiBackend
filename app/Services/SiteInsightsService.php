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

    /** User and support operation counts from the authoritative application records. */
    public static function operations(array $users, array $tickets, string $from, string $to): array {
        $newUsers = 0;
        foreach ($users as $user) {
            $created = substr((string)($user['created_at']??''),0,10);
            if ($created >= $from && $created <= $to) $newUsers++;
        }
        $openedTickets = 0;
        $unresolved = 0;
        $byStatus = [];
        foreach ($tickets as $ticket) {
            $status = strtolower(trim((string)($ticket['status']??'unknown')));
            $byStatus[$status] = ($byStatus[$status]??0) + 1;
            if ($status==='open') $unresolved++;
            $created = substr((string)($ticket['created_at']??''),0,10);
            if ($created >= $from && $created <= $to) $openedTickets++;
        }
        return [
            'total_users'=>count($users),
            'new_users'=>$newUsers,
            'opened_tickets'=>$openedTickets,
            'open_tickets'=>$unresolved,
            'ticket_statuses'=>$byStatus,
        ];
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
