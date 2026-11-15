<?php
namespace App\Services;

/** Curated operator instructions, not a repository/document browser or runtime-agent prompt source. */
final class AdminHelpService
{
    public const CATEGORIES=['start'=>'Getting started','commerce'=>'Commerce','agents'=>'Support & agents','content'=>'Content & media','operations'=>'Operations'];
    private const GUIDES=[
        'getting-started'=>['title'=>'Find your way around','category'=>'start','summary'=>'Use the owner workspace, choose the right task and know what needs checking.','links'=>[['label'=>'Open dashboard','url'=>'/admin']], 'sources'=>['app/Controllers/AdminController.php','views/layouts/admin.php','views/admin/dashboard.php']],
        'catalogue'=>['title'=>'Products, collections & offers','category'=>'commerce','summary'=>'Maintain product details, organise collections and check an offer before sharing it.','links'=>[['label'=>'Products','url'=>'/admin/products'],['label'=>'Collections','url'=>'/admin/collections'],['label'=>'Coupons','url'=>'/admin/coupons']], 'sources'=>['app/Services/ProductService.php','app/Services/SeoService.php','app/Services/CategoryService.php','app/Services/CouponService.php','views/admin/product-form.php','views/admin/resource.php']],
        'orders-shipping'=>['title'=>'Orders, delivery & refunds','category'=>'commerce','summary'=>'Check verified payments, review holds and update delivery without duplicating a transaction.','links'=>[['label'=>'Open orders','url'=>'/admin/orders'],['label'=>'Shipping settings','url'=>'/admin/shipping']], 'sources'=>['app/Services/OrderService.php','app/Controllers/AdminController.php','views/admin/detail.php','views/admin/settings.php']],
        'reports'=>['title'=>'Read commerce & GST reports','category'=>'commerce','summary'=>'Choose dates and filters, distinguish paid sales from all orders and inspect invoices.','links'=>[['label'=>'Commerce reports','url'=>'/admin/tax-report']], 'sources'=>['app/Services/StoreStatsService.php','app/Services/GstRegisterService.php','app/Services/TaxService.php','views/admin/tax-report.php','views/admin/_commerce-overview.php']],
        'support-tickets'=>['title'=>'Handle customer support','category'=>'agents','summary'=>'Claim a case, save a reply, manage waiting states and reopen when help is still needed.','links'=>[['label'=>'AI Support Tickets','url'=>'/admin/support-tickets'],['label'=>'Contacts','url'=>'/admin/contact-submissions']], 'sources'=>['app/Services/SupportTicketService.php','app/Controllers/AdminController.php','views/admin/list.php']],
        'enterprise-agent'=>['title'=>'Ask the Enterprise Agent','category'=>'agents','summary'=>'Ask scoped business questions, reopen your chats and review drafts before making changes.','links'=>[['label'=>'Open Enterprise chat','url'=>'/admin/agent']], 'sources'=>['app/Controllers/AdminController.php','app/Services/AgentToolRegistry.php','views/admin/agent.php']],
        'agent-monitoring'=>['title'=>'Inspect agent quality & evidence','category'=>'agents','summary'=>'Separate response activity, customer confirmation and regression checks; inspect unfinished work safely.','links'=>[['label'=>'Agent Monitoring','url'=>'/admin/agent/monitoring'],['label'=>'Support queue','url'=>'/admin/support-tickets']], 'sources'=>['app/Services/AgentEvaluationService.php','app/Services/AgentConversationService.php','app/Services/AuditLogService.php','views/admin/agent-monitoring.php']],
        'blog-publishing'=>['title'=>'Write and publish a guide','category'=>'content','summary'=>'Draft, preview and explicitly save useful articles with accurate images and publication settings.','links'=>[['label'=>'Blog editor','url'=>'/admin/blog'],['label'=>'Media Library','url'=>'/admin/media']], 'sources'=>['app/Services/BlogService.php','app/Services/BlogDraftService.php','views/admin/blog.php']],
        'media'=>['title'=>'Manage images safely','category'=>'content','summary'=>'Upload reusable images, inspect usage and delete only unused uploads after confirmation.','links'=>[['label'=>'Media Library','url'=>'/admin/media'],['label'=>'Logo & Favicon','url'=>'/admin/appearance']], 'sources'=>['app/Services/MediaService.php','views/admin/media.php']],
        'customers'=>['title'=>'Find customer accounts','category'=>'operations','summary'=>'Filter registered accounts and interpret order matches without changing customer identities.','links'=>[['label'=>'Users directory','url'=>'/admin/users']], 'sources'=>['app/Controllers/AdminController.php','views/admin/users.php','app/Services/AuthService.php']],
        'email'=>['title'=>'Understand email delivery','category'=>'operations','summary'=>'Read queue health and distinguish SMTP acceptance, failed attempts and an actual inbox receipt.','links'=>[['label'=>'Email Outbox','url'=>'/admin/email-outbox'],['label'=>'Email Inbox','url'=>'/admin/email-inbox']], 'sources'=>['app/Services/MailQueueService.php','app/Services/SmtpMailer.php','views/admin/mailbox.php']],
        'settings'=>['title'=>'Change site settings carefully','category'=>'operations','summary'=>'Know the difference between site content, provider credentials and changes that can affect checkout.','links'=>[['label'=>'Site Settings','url'=>'/admin/settings'],['label'=>'Integrations','url'=>'/admin/integrations'],['label'=>'Temples','url'=>'/admin/temples']], 'sources'=>['app/Services/SettingsService.php','app/Services/SecretService.php','views/admin/settings.php','views/admin/integrations.php','views/admin/resource.php']],
    ];
    public static function references(): array {
        $out=[];foreach(self::GUIDES as $slug=>$guide)$out[]=['slug'=>$slug,'file'=>'content/help/admin/'.$slug.'.md']+$guide;return $out;
    }
    /** Exact public help sources for source-review and link verification, not dynamic file selectors. */
    public static function publicReferences(): array {
        return [
            ['slug'=>'create-account','file'=>'content/blog/posts/create-account.md','sources'=>['app/Controllers/AuthController.php','app/Services/AuthService.php','views/public/register.php','views/public/login.php']],
            ['slug'=>'order-products','file'=>'content/blog/posts/order-products.md','sources'=>['app/Controllers/CommerceController.php','app/Services/CartService.php','views/public/product.php','views/public/cart.php','views/public/checkout.php']],
            ['slug'=>'payments-and-orders','file'=>'content/blog/posts/payments-and-orders.md','sources'=>['app/Services/OrderService.php','app/Controllers/CommerceController.php','views/public/checkout.php','views/account/order.php']],
            ['slug'=>'manage-account','file'=>'content/blog/posts/manage-account.md','sources'=>['app/Controllers/AccountController.php','app/Services/AddressService.php','views/account/_nav.php','views/account/profile.php','views/account/addresses.php']],
            ['slug'=>'get-support','file'=>'content/blog/posts/get-support.md','sources'=>['app/Controllers/SupportController.php','app/Services/SupportBotService.php','app/Services/SupportTicketService.php','views/layouts/app.php','views/public/contact.php']],
        ];
    }

    private static function requireAdmin(?array $actor): void {
        if(($actor['role']??null)!=='admin'&&($actor['is_admin']??null)!==true)throw new \RuntimeException('Admin access required for operator help.');
    }
    private function read(string $slug): string {
        if(!isset(self::GUIDES[$slug]))throw new \InvalidArgumentException('Unknown guide');
        $root=app_path();$path=$root;
        foreach(['content','help','admin',$slug.'.md'] as $part){$path.='/'.$part;if(is_link($path))throw new \RuntimeException('Linked help source is not permitted.');}
        $real=realpath($path);$base=realpath(app_path('content/help/admin'));
        if($real===false||$base===false||!str_starts_with($real,$base.'/')||!is_file($real))throw new \RuntimeException('Operator guide unavailable.');
        $text=@file_get_contents($real,false,null,0,24577);
        if(!is_string($text)||trim($text)===''||strlen($text)>24576||str_contains($text,"\0")||!mb_check_encoding($text,'UTF-8'))throw new \RuntimeException('Invalid operator guide.');
        return $text;
    }
    public function catalog(?array $actor,string $query='',string $category=''): array {
        self::requireAdmin($actor);$query=mb_substr(trim($query),0,80);
        if($category!==''&&!isset(self::CATEGORIES[$category]))return [];
        $words=preg_split('/\s+/u',mb_strtolower($query),-1,PREG_SPLIT_NO_EMPTY)?:[];$out=[];
        foreach(self::GUIDES as $slug=>$meta){
            if($category!==''&&$meta['category']!==$category)continue;
            $text=$this->read($slug);$haystack=mb_strtolower($meta['title'].' '.$meta['summary'].' '.$text);
            foreach($words as $word)if(!str_contains($haystack,$word))continue 2;
            $out[]=['slug'=>$slug,'reading_minutes'=>max(1,(int)ceil(str_word_count($text)/180))]+$meta;
        }
        return $out;
    }
    public function guide(string $slug,?array $actor): ?array {
        self::requireAdmin($actor);if(!isset(self::GUIDES[$slug]))return null;
        $text=$this->read($slug);$html=(new MarkdownRenderer())->render($text);$toc=[];
        preg_match_all('/<h2 id="([a-z0-9-]+)">(.*?)<\/h2>/s',$html,$matches,PREG_SET_ORDER);
        foreach($matches as $m)$toc[]=['id'=>$m[1],'label'=>html_entity_decode(strip_tags($m[2]),ENT_QUOTES|ENT_HTML5,'UTF-8')];
        return ['slug'=>$slug,'html'=>$html,'toc'=>$toc,'reading_minutes'=>max(1,(int)ceil(str_word_count($text)/180))]+self::GUIDES[$slug];
    }
    private static function onPath(string $path,string $base): bool {return $path===$base||str_starts_with($path,$base.'/');}
    /** Metadata only, used inside the already authenticated layout. */
    public static function adminContext(string $uri): array {
        $path=(string)(parse_url($uri,PHP_URL_PATH)?:'/admin');$slug='getting-started';
        foreach([
            '/admin/agent/monitoring'=>'agent-monitoring','/admin/agent'=>'enterprise-agent',
            '/admin/support-tickets'=>'support-tickets','/admin/contact-submissions'=>'support-tickets',
            '/admin/orders'=>'orders-shipping','/admin/shipping'=>'orders-shipping','/admin/tax-report'=>'reports',
            '/admin/products'=>'catalogue','/admin/categories'=>'catalogue','/admin/collections'=>'catalogue','/admin/coupons'=>'catalogue',
            '/admin/blog'=>'blog-publishing','/admin/media'=>'media','/admin/appearance'=>'media','/admin/users'=>'customers',
            '/admin/email-inbox'=>'email','/admin/email-outbox'=>'email','/admin/integrations'=>'settings','/admin/settings'=>'settings','/admin/temples'=>'settings'
        ] as $prefix=>$target)if(self::onPath($path,$prefix)){$slug=$target;break;}
        return ['slug'=>$slug,'url'=>'/admin/docs/'.$slug]+self::GUIDES[$slug];
    }
    /** Public links refer only to customer-facing guides, never operator files. */
    public static function publicContext(string $uri): ?array {
        $path=(string)(parse_url($uri,PHP_URL_PATH)?:'/');
        foreach([
            '/checkout'=>['payments-and-orders','Before you pay','Review the amount, delivery details and what to do if confirmation is delayed.'],
            '/cart'=>['order-products','Using your cart','Check quantities and delivery details before checkout.'],
            '/shop'=>['order-products','Shopping help','Learn how to choose products, manage quantities and track your order.'],
            '/product'=>['order-products','Product and ordering help','Read the ordering guide before adding or paying for an item.'],
            '/account/dashboard'=>['manage-account','Account help','Find your orders, profile and saved delivery addresses.'],
            '/forgot-password'=>['create-account','Resetting your password','Use the supported recovery flow without sharing your password.'],
            '/reset-password'=>['create-account','Resetting your password','Check the account guide for a safe reset and sign-in.'],
            '/login'=>['create-account','Signing in or registering?','Use the account guide to choose the right sign-in or recovery option.'],
            '/register'=>['create-account','Creating an account','Understand account details and how to keep your access safe.'],
            '/contact'=>['get-support','Getting support','Know which details help the team investigate your question.']
        ] as $prefix=>$help)if(self::onPath($path,$prefix))return ['url'=>'/blog/'.$help[0],'title'=>$help[1],'summary'=>$help[2]];
        return null;
    }
}
