<?php
namespace App\Services;
final class ProjectMapService {
    private const RUNTIME_JSON_STORES = [
        'test_race',
    ];

    public const SHARED_CONTROLLERS = ['BaseController'];
    public const SHARED_SERVICES = ['SeoService', 'SmtpMailer', 'ImageOptimizerService', 'DocsMapService', 'GitHubDocService', 'RateLimiter', 'KnowledgeGraphService', 'BrowserSession'];
    public const SHARED_VIEWS = ['account/_nav', 'layouts/admin', 'layouts/app', 'public/404', 'public/_consultation-pricing', 'admin/environment'];
    public const KNOWN_UNWIRED_COLLECTIONS = ['wallet_transactions', 'media_files'];

    public static function registry(): array {
        $routes = [
            ['method'=>'GET','path'=>'/','name'=>'home','page'=>'public/home','controller'=>'PublicController@home','services'=>['ProductService','AstrologerService','TempleService','CategoryService']],
            ['method'=>'GET','path'=>'/about','name'=>'about','page'=>'public/about','controller'=>'PublicController@about','services'=>[]],
            ['method'=>'GET','path'=>'/sri-panchami-spiritual','name'=>'spiritual','page'=>'public/spiritual','controller'=>'PublicController@spiritual','services'=>[]],
            ['method'=>'GET','path'=>'/spiritual','name'=>'spiritual.short','page'=>'public/spiritual','controller'=>'PublicController@spiritual','services'=>[]],
            ['method'=>'GET','path'=>'/terms','name'=>'terms','page'=>'public/terms','controller'=>'PublicController@terms','services'=>[]],
            ['method'=>'GET','path'=>'/privacy','name'=>'privacy','page'=>'public/privacy','controller'=>'PublicController@privacy','services'=>[]],

            ['method'=>'GET','path'=>'/consult','name'=>'consult','page'=>'public/consult','controller'=>'PublicController@consult','services'=>['AstrologerService']],
            ['method'=>'GET','path'=>'/consult/{slug}','name'=>'consult.show','page'=>'public/astrologer','controller'=>'PublicController@consultant','services'=>['AstrologerService']],
            ['method'=>'GET','path'=>'/temples','name'=>'temples','page'=>'public/temples','controller'=>'PublicController@temples','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/temples/{slug}','name'=>'temple.show','page'=>'public/temple','controller'=>'PublicController@temple','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/shop','name'=>'shop','page'=>'public/shop','controller'=>'PublicController@shop','services'=>['ProductService','CategoryService']],
            ['method'=>'GET','path'=>'/categories','name'=>'categories','page'=>'public/categories','controller'=>'PublicController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/product/{slug}','name'=>'product.show','page'=>'public/product','controller'=>'PublicController@product','services'=>['ProductService']],
            ['method'=>'GET','path'=>'/api','name'=>'api.index','page'=>'public/404','controller'=>'ApiController@index','services'=>[]],
            ['method'=>'GET','path'=>'/api/shop','name'=>'api.shop','page'=>'public/404','controller'=>'ApiController@shop','services'=>['ProductService','CategoryService']],
            ['method'=>'GET','path'=>'/api/categories','name'=>'api.categories','page'=>'public/404','controller'=>'ApiController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/api/product/{slug}','name'=>'api.product','page'=>'public/404','controller'=>'ApiController@product','services'=>['ProductService']],
            ['method'=>'GET','path'=>'/api/consult','name'=>'api.consult','page'=>'public/404','controller'=>'ApiController@consult','services'=>['ProductService']],
            ['method'=>'GET','path'=>'/api/temples','name'=>'api.temples','page'=>'public/404','controller'=>'ApiController@temples','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/cart','name'=>'cart','page'=>'public/cart','controller'=>'PublicController@cart','services'=>['CartService','ProductService']],
            ['method'=>'GET','path'=>'/checkout','name'=>'checkout','page'=>'public/checkout','controller'=>'PublicController@checkout','services'=>['CartService','ProductService','SecretService','AddressService','SettingsService']],
            ['method'=>'GET','path'=>'/sitemap.xml','name'=>'sitemap','page'=>'public/sitemap','controller'=>'PublicController@sitemap','services'=>['BlogService','ProductService']],
            ['method'=>'GET','path'=>'/contact','name'=>'contact','page'=>'public/contact','controller'=>'PublicController@contact','services'=>[]],
            ['method'=>'POST','path'=>'/contact','name'=>'contact.post','page'=>'public/contact','controller'=>'PublicController@contact','services'=>['ContactService']],
            ['method'=>'GET','path'=>'/login','name'=>'login','page'=>'public/login','controller'=>'PublicController@login','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/forgot-password','name'=>'forgot-password','page'=>'public/forgot-password','controller'=>'AuthController@forgotPassword','services'=>['PasswordResetService']],
            ['method'=>'POST','path'=>'/forgot-password','name'=>'forgot-password.post','page'=>'public/forgot-password','controller'=>'AuthController@forgotPasswordPost','services'=>['PasswordResetService']],
            ['method'=>'GET','path'=>'/reset-password','name'=>'reset-password','page'=>'public/reset-password','controller'=>'AuthController@resetPassword','services'=>['PasswordResetService']],
            ['method'=>'POST','path'=>'/reset-password','name'=>'reset-password.post','page'=>'public/reset-password','controller'=>'AuthController@resetPasswordPost','services'=>['PasswordResetService']],
            ['method'=>'GET','path'=>'/auth/google','name'=>'auth.google','page'=>'public/login','controller'=>'AuthController@googleRedirect','services'=>['SecretService']],
            ['method'=>'GET','path'=>'/auth/google/callback','name'=>'auth.google.callback','page'=>'public/login','controller'=>'AuthController@callback','services'=>['SecretService','DatabaseService']],
            ['method'=>'GET','path'=>'/register','name'=>'register','page'=>'public/register','controller'=>'AuthController@register','services'=>[]],
            ['method'=>'POST','path'=>'/register','name'=>'register.post','page'=>'public/register','controller'=>'AuthController@registerPost','services'=>['DatabaseService']],
            ['method'=>'POST','path'=>'/login','name'=>'login.post','page'=>'public/login','controller'=>'AuthController@loginPost','services'=>['DatabaseService']],
            ['method'=>'GET','path'=>'/logout','name'=>'logout','page'=>'public/login','controller'=>'AuthController@logout','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/dashboard','name'=>'account.dashboard','page'=>'account/orders','controller'=>'AccountController@dashboard','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/dashboard/orders','name'=>'account.dashboard.orders','page'=>'account/orders','controller'=>'AccountController@orders','services'=>['AuthService','OrderService','ReviewService']],
            ['method'=>'GET','path'=>'/account/dashboard/sessions','name'=>'account.dashboard.sessions','page'=>'account/bookings','controller'=>'AccountController@bookings','services'=>['AuthService','AppointmentService']],
            ['method'=>'GET','path'=>'/account/dashboard/install','name'=>'account.dashboard.install','page'=>'account/install','controller'=>'AccountController@install','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/orders','name'=>'account.orders.legacy','page'=>'account/orders','controller'=>'AccountController@legacyOrders','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/orders/{orderId}/invoice','name'=>'account.invoice','page'=>'account/invoice','controller'=>'AccountController@invoice','services'=>['AuthService','OrderService','SettingsService','TaxService']],
            ['method'=>'GET','path'=>'/account/bookings','name'=>'account.bookings.legacy','page'=>'account/bookings','controller'=>'AccountController@legacyBookings','services'=>['AuthService']],
            ['method'=>'POST','path'=>'/api/consultations/{id}/status','name'=>'api.consultation.status','page'=>'admin/list','controller'=>'ConsultationController@status','services'=>['AuthService','ConsultationService']],
            ['method'=>'GET','path'=>'/admin','name'=>'admin.dashboard','page'=>'admin/dashboard','controller'=>'AdminController@dashboard','services'=>['OrderService','AppointmentService']],
            ['method'=>'GET','path'=>'/admin/products','name'=>'admin.products','page'=>'admin/product-form','controller'=>'AdminController@products','services'=>['ProductService','SchemaService']],
            ['method'=>'GET','path'=>'/admin/categories','name'=>'admin.categories','page'=>'admin/resource','controller'=>'AdminController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/admin/coupons','name'=>'admin.coupons','page'=>'admin/resource','controller'=>'AdminController@coupons','services'=>['CouponService']],
            ['method'=>'GET','path'=>'/admin/orders','name'=>'admin.orders','page'=>'admin/list','controller'=>'AdminController@orders','services'=>['OrderService']],
            ['method'=>'GET','path'=>'/admin/orders/{id}','name'=>'admin.order.show','page'=>'admin/detail','controller'=>'AdminController@order','services'=>['OrderService','ShippingService']],
            ['method'=>'POST','path'=>'/admin/orders/{id}/status','name'=>'admin.order.status','page'=>'admin/detail','controller'=>'AdminController@saveOrderStatus','services'=>['OrderService','MailQueueService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/shipping','name'=>'admin.shipping','page'=>'admin/settings','controller'=>'AdminController@shipping','services'=>['ShippingService','SettingsService']],
            ['method'=>'GET','path'=>'/admin/astrologers','name'=>'admin.astrologers','page'=>'admin/astrologer-form','controller'=>'AdminController@astrologers','services'=>['AstrologerService','SchemaService']],
            ['method'=>'GET','path'=>'/admin/appointments','name'=>'admin.appointments','page'=>'admin/list','controller'=>'AdminController@appointments','services'=>['AppointmentService']],
            ['method'=>'GET','path'=>'/admin/consultation-analytics','name'=>'admin.consultation-analytics','page'=>'admin/consultation-analytics','controller'=>'AdminController@consultationAnalytics','services'=>['ConsultationService']],
            ['method'=>'GET','path'=>'/admin/tax-report','name'=>'admin.tax-report','page'=>'admin/tax-report','controller'=>'AdminController@taxReport','services'=>['OrderService','TaxService']],
            ['method'=>'GET','path'=>'/admin/temples','name'=>'admin.temples','page'=>'admin/resource','controller'=>'AdminController@temples','services'=>['TempleService','SchemaService']],
            ['method'=>'GET','path'=>'/admin/settings','name'=>'admin.settings','page'=>'admin/settings','controller'=>'AdminController@settings','services'=>['SettingsService']],
            ['method'=>'POST','path'=>'/admin/settings/save','name'=>'admin.settings.save','page'=>'admin/settings','controller'=>'AdminController@saveSettings','services'=>['SettingsService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/settings/admin-credentials','name'=>'admin.settings.admin-credentials','page'=>'admin/settings','controller'=>'AdminController@saveAdminCredentials','services'=>['EnvService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/integrations','name'=>'admin.integrations','page'=>'admin/integrations','controller'=>'AdminController@integrations','services'=>['SettingsService','PaymentService','SecretService']],
            ['method'=>'GET','path'=>'/admin/agent','name'=>'admin.agent','page'=>'admin/agent','controller'=>'AdminController@agent','services'=>['SecretService','DatabaseService']],
            ['method'=>'POST','path'=>'/admin/agent/ask','name'=>'admin.agent.ask','page'=>'admin/agent','controller'=>'AdminController@agentAsk','services'=>['SecretService','DatabaseService']],
            ['method'=>'GET','path'=>'/admin/backups','name'=>'admin.backups','page'=>'admin/list','controller'=>'AdminController@backups','services'=>['DatabaseService']],
            ['method'=>'GET','path'=>'/admin/audit-log','name'=>'admin.audit','page'=>'admin/list','controller'=>'AdminController@audit','services'=>['AuditLogService']],
            ['method'=>'GET','path'=>'/admin/contact-submissions','name'=>'admin.contact-submissions','page'=>'admin/resource','controller'=>'AdminController@contactSubmissions','services'=>['ContactService']],
            ['method'=>'POST','path'=>'/admin/contact_submissions/save','name'=>'admin.contact-submissions.save','page'=>'admin/resource','controller'=>'AdminController@saveContactSubmission','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/contact_submissions/delete','name'=>'admin.contact-submissions.delete','page'=>'admin/resource','controller'=>'AdminController@deleteContactSubmission','services'=>['ResourceService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/email-inbox','name'=>'admin.email-inbox','page'=>'admin/mailbox','controller'=>'AdminController@emailInbox','services'=>['MailStorageService']],
            ['method'=>'GET','path'=>'/admin/email-outbox','name'=>'admin.email-outbox','page'=>'admin/mailbox','controller'=>'AdminController@emailOutbox','services'=>['MailStorageService']],
            ['method'=>'GET','path'=>'/admin/support-tickets','name'=>'admin.support-tickets','page'=>'admin/list','controller'=>'AdminController@supportTickets','services'=>['DatabaseService','SupportTicketService']],
            ['method'=>'POST','path'=>'/admin/support-tickets/save','name'=>'admin.support-tickets.save','page'=>'admin/list','controller'=>'AdminController@saveSupportTicket','services'=>['ResourceService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/appearance','name'=>'admin.appearance','page'=>'admin/appearance','controller'=>'AdminController@appearance','services'=>['SettingsService']],
            ['method'=>'POST','path'=>'/admin/appearance/save','name'=>'admin.appearance.save','page'=>'admin/appearance','controller'=>'AdminController@saveAppearance','services'=>['SettingsService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/media','name'=>'admin.media','page'=>'admin/media','controller'=>'AdminController@media','services'=>['MediaService']],
            ['method'=>'POST','path'=>'/admin/media/upload','name'=>'admin.media.upload','page'=>'admin/media','controller'=>'AdminController@uploadMedia','services'=>['MediaService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/environment/fix-permissions','name'=>'admin.environment.fix-permissions','page'=>'admin/settings','controller'=>'AdminController@fixPermissions','services'=>['StoragePermissionService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/developer/project-map','name'=>'admin.project-map','page'=>'admin/project-map','controller'=>'AdminController@projectMap','services'=>['ProjectMapService']],
            ['method'=>'GET','path'=>'/admin/developer/workflow','name'=>'admin.workflow','page'=>'admin/workflow','controller'=>'AdminController@workflow','services'=>[]],
            ['method'=>'POST','path'=>'/admin/products/save','name'=>'admin.products.save','page'=>'admin/product-form','controller'=>'AdminController@saveProduct','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/products/delete','name'=>'admin.products.delete','page'=>'admin/product-form','controller'=>'AdminController@deleteProduct','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/categories/save','name'=>'admin.categories.save','page'=>'admin/resource','controller'=>'AdminController@saveCategory','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/categories/delete','name'=>'admin.categories.delete','page'=>'admin/resource','controller'=>'AdminController@deleteCategory','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/coupons/save','name'=>'admin.coupons.save','page'=>'admin/resource','controller'=>'AdminController@saveCoupon','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/coupons/delete','name'=>'admin.coupons.delete','page'=>'admin/resource','controller'=>'AdminController@deleteCoupon','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/astrologers/save','name'=>'admin.astrologers.save','page'=>'admin/astrologer-form','controller'=>'AdminController@saveAstrologer','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/astrologers/delete','name'=>'admin.astrologers.delete','page'=>'admin/astrologer-form','controller'=>'AdminController@deleteAstrologer','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/temples/save','name'=>'admin.temples.save','page'=>'admin/resource','controller'=>'AdminController@saveTemple','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/temples/delete','name'=>'admin.temples.delete','page'=>'admin/resource','controller'=>'AdminController@deleteTemple','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/integrations/save','name'=>'admin.integrations.save','page'=>'admin/integrations','controller'=>'AdminController@saveIntegrations','services'=>['SecretService','AuditLogService']],
            ['method'=>'POST','path'=>'/cart/add','name'=>'cart.add','page'=>'public/cart','controller'=>'CommerceController@addToCart','services'=>['CartService','ProductService']],
            ['method'=>'POST','path'=>'/cart/remove','name'=>'cart.remove','page'=>'public/cart','controller'=>'CommerceController@removeFromCart','services'=>['CartService']],
            ['method'=>'POST','path'=>'/cart/update','name'=>'cart.update','page'=>'public/cart','controller'=>'CommerceController@updateCart','services'=>['CartService']],
            ['method'=>'POST','path'=>'/checkout/create-order','name'=>'checkout.create-order','page'=>'public/checkout','controller'=>'CommerceController@createOrder','services'=>['SecretService','PaymentService']],
            ['method'=>'POST','path'=>'/payment/verify','name'=>'payment.verify','page'=>'public/checkout','controller'=>'CommerceController@verifyPayment','services'=>['SecretService','PaymentService','DatabaseService','TaxService','SettingsService']],
            ['method'=>'POST','path'=>'/create-order','name'=>'api.checkout.create-order','page'=>'public/checkout','controller'=>'CommerceController@createOrder','services'=>['SecretService','PaymentService']],
            ['method'=>'POST','path'=>'/verify-payment','name'=>'api.payment.verify','page'=>'public/checkout','controller'=>'CommerceController@verifyPayment','services'=>['SecretService','PaymentService','DatabaseService','TaxService','SettingsService']],
            ['method'=>'POST','path'=>'/consultation/initiate','name'=>'consultation.initiate','page'=>'public/astrologer','controller'=>'ConsultationController@initiate','services'=>['AuthService','AstrologerService','ResourceService','MailQueueService']],
            ['method'=>'POST','path'=>'/reviews/astrologer','name'=>'reviews.astrologer','page'=>'account/bookings','controller'=>'ReviewController@saveAstrologer','services'=>['ReviewService']],
            ['method'=>'POST','path'=>'/reviews/product','name'=>'reviews.product','page'=>'account/orders','controller'=>'ReviewController@saveProduct','services'=>['ReviewService']],
            ['method'=>'GET','path'=>'/support','name'=>'support.page','page'=>'public/support','controller'=>'SupportController@page','services'=>['SeoService']],
            ['method'=>'POST','path'=>'/support/ask','name'=>'support.ask','page'=>'public/support','controller'=>'SupportController@ask','services'=>['SupportBotService','AgentContextService','SupportTicketService']],

            ['method'=>'GET','path'=>'/docs','name'=>'docs.index','page'=>'public/blog','controller'=>'PublicController@docs','services'=>[]],
            ['method'=>'GET','path'=>'/help/{slug}','name'=>'help.show','page'=>'public/blog-post','controller'=>'PublicController@doc','services'=>[]],
            ['method'=>'POST','path'=>'/remoteDB','name'=>'api.remotedb','page'=>'public/404','controller'=>'RemoteDbController@__invoke','services'=>['DatabaseService','SecretService']],
            ['method'=>'GET','path'=>'/blog','name'=>'blog.index','page'=>'public/blog','controller'=>'BlogController@index','services'=>['BlogService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/blog/{slug}','name'=>'blog.show','page'=>'public/blog-post','controller'=>'BlogController@show','services'=>['BlogService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/blog/category/{slug}','name'=>'blog.category','page'=>'public/blog','controller'=>'BlogController@category','services'=>['BlogService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/admin/blog','name'=>'admin.blog','page'=>'admin/blog','controller'=>'AdminController@blog','services'=>['BlogService']],
            ['method'=>'POST','path'=>'/admin/blog/save','name'=>'admin.blog.save','page'=>'admin/blog','controller'=>'AdminController@saveBlog','services'=>['BlogService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/blog/delete','name'=>'admin.blog.delete','page'=>'admin/blog','controller'=>'AdminController@deleteBlog','services'=>['BlogService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/blog/preview','name'=>'admin.blog.preview','page'=>'admin/blog','controller'=>'AdminController@previewBlog','services'=>['BlogService','MarkdownRenderer']],
            ['method'=>'POST','path'=>'/admin/blog/ai-draft','name'=>'admin.blog.ai-draft','page'=>'admin/blog','controller'=>'AdminController@aiDraftBlog','services'=>['BlogService','BlogDraftService']],
            ['method'=>'POST','path'=>'/api/agent','name'=>'api.agent','page'=>'public/404','controller'=>'AgentController@ask','services'=>['SecretService','DatabaseService']],
            ['method'=>'POST','path'=>'/api/tts/tokenize','name'=>'api.tts.tokenize','page'=>'public/404','controller'=>'TtsController@tokenize','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/search','name'=>'api.browser.search','page'=>'public/404','controller'=>'BrowserAgentController@search','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/open','name'=>'api.browser.open','page'=>'public/404','controller'=>'BrowserAgentController@open','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/click','name'=>'api.browser.click','page'=>'public/404','controller'=>'BrowserAgentController@click','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/fill','name'=>'api.browser.fill','page'=>'public/404','controller'=>'BrowserAgentController@fill','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/snapshot','name'=>'api.browser.snapshot','page'=>'public/404','controller'=>'BrowserAgentController@snapshot','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/links','name'=>'api.browser.links','page'=>'public/404','controller'=>'BrowserAgentController@links','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/forms','name'=>'api.browser.forms','page'=>'public/404','controller'=>'BrowserAgentController@forms','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/captcha','name'=>'api.browser.captcha','page'=>'public/404','controller'=>'BrowserAgentController@captcha','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/smoke','name'=>'api.browser.smoke','page'=>'public/404','controller'=>'BrowserAgentController@smoke','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/cdp','name'=>'api.browser.cdp','page'=>'public/404','controller'=>'BrowserAgentController@cdp','services'=>[]],
            ['method'=>'POST','path'=>'/api/browser/cdp_launch','name'=>'api.browser.cdp_launch','page'=>'public/404','controller'=>'BrowserAgentController@cdpLaunch','services'=>[]],
            ['method'=>'GET','path'=>'/api/browser/status','name'=>'api.browser.status','page'=>'public/404','controller'=>'BrowserAgentController@status','services'=>[]],
            ['method'=>'GET','path'=>'/api/support/latest-message','name'=>'api.support.latest','page'=>'public/404','controller'=>'SupportController@latestMessage','services'=>['DatabaseService']],
        ];
        foreach ($routes as &$route) {
            if ((str_starts_with($route['path'], '/admin') || str_starts_with($route['path'], '/reviews')) && !in_array('AuthService', $route['services'], true) && !str_starts_with($route['path'], '/admin/sw.js') && !str_starts_with($route['path'], '/admin/manifest.json')) {
                $route['services'][] = 'AuthService';
            }
        }
        unset($route);
        return ['routes'=>$routes];
    }
    public static function validate(array $map): array {
        $services = $map['services'] ?? self::phpBasenames(app_path('app/Services'));
        $schema = require app_path('storage/schema/collections.php');
        $collections = $map['collections'] ?? array_keys($schema['collections'] ?? []);
        $missingRouteMappings = array_values(array_filter($map['routes'] ?? [], fn($r) => empty($r['controller']) || empty($r['page'])));
        $used = array_unique(array_merge(...array_map(fn($r) => $r['services'] ?? [], $map['routes'] ?? [])));
        $missingServices = array_values(array_diff($used, $services));
        return ['missing_route_mappings'=>$missingRouteMappings,'missing_services'=>$missingServices,'missing_collections'=>[]];
    }

    public static function scan(): array {
        $map = self::registry();
        $schema = require app_path('storage/schema/collections.php');
        $schemaCollections = array_keys($schema['collections'] ?? []);

        $controllers = self::phpBasenames(app_path('app/Controllers'));
        $services = self::phpBasenames(app_path('app/Services'));
        $views = self::viewNames(app_path('views'));
        $integrations = self::phpBasenames(app_path('integrations'));
        $tools = self::phpBasenames(app_path('cli'));
        if (is_file(app_path('cli/bapXphp'))) $tools[] = 'bapXphp';
        $storageFiles = self::jsonBasenames(app_path('storage/data'));

        $routeControllers = array_values(array_unique(array_map(
            fn($route) => explode('@', (string)($route['controller'] ?? ''))[0] ?? '',
            $map['routes']
        )));
        $routeControllers = array_values(array_filter($routeControllers));
        $routeServices = array_values(array_unique(array_merge(...array_map(fn($route) => $route['services'], $map['routes']))));
        $routeViews = array_values(array_unique(array_filter(array_map(fn($route) => $route['page'] ?? '', $map['routes']))));
        $navigation = self::navigationPaths();
        $getRoutePaths = array_values(array_unique(array_map(
            fn($route) => (string)$route['path'],
            array_filter($map['routes'], fn($route) => ($route['method'] ?? 'GET') === 'GET')
        )));

        $sharedControllers = self::SHARED_CONTROLLERS;
        $sharedServices = self::SHARED_SERVICES;
        $sharedViews = self::SHARED_VIEWS;
        $knownUnwiredCollections = self::KNOWN_UNWIRED_COLLECTIONS;

        $adminPostRoutes = array_values(array_filter($map['routes'], fn($r) => str_starts_with($r['path'] ?? '', '/admin') && ($r['method'] ?? 'GET') === 'POST' && !str_contains($r['path'] ?? '', 'sw.js') && !str_contains($r['path'] ?? '', 'manifest.json') && !str_contains($r['path'] ?? '', '/agent/ask') && !str_contains($r['path'] ?? '', '/blog/preview') && !str_contains($r['path'] ?? '', '/blog/ai-draft')));
        $allScCollections = array_values(array_unique(array_merge(...array_values(self::serviceCollections()))));

        $gaps = [
            'missing_route_mappings' => array_values(array_filter($map['routes'], fn($route) => empty($route['controller']) || empty($route['page']))),
            'missing_controller_files' => array_values(array_diff($routeControllers, $controllers)),
            'missing_service_files' => array_values(array_diff($routeServices, $services)),
            'missing_view_files' => array_values(array_diff($routeViews, $views)),
            'navigation_without_get_route' => array_values(array_diff($navigation, $getRoutePaths)),
            'unwired_controllers' => array_values(array_diff($controllers, $routeControllers, $sharedControllers)),
            'unwired_services' => array_values(array_diff($services, $routeServices, $sharedServices)),
            'unwired_views' => array_values(array_diff($views, $routeViews, $sharedViews)),
            'admin_mutations_without_audit' => array_values(array_filter($adminPostRoutes, fn($r) => !in_array('AuditLogService', $r['services'] ?? [], true))),
            'unwired_schema_collections' => array_values(array_diff($schemaCollections, $allScCollections, $knownUnwiredCollections)),
        ];

        return [
            'routes' => $map['routes'],
            'controllers' => $controllers,
            'services' => $services,
            'views' => $views,
            'navigation' => $navigation,
            'integrations' => $integrations,
            'schema_collections' => $schemaCollections,
            'storage_files' => $storageFiles,
            'tools' => $tools,
            'gaps' => $gaps,
            'summary' => [
                'routes' => count($map['routes']),
                'controllers' => count($controllers),
                'services' => count($services),
                'views' => count($views),
                'integrations' => count($integrations),
                'schema_collections' => count($schemaCollections),
                'storage_files' => count($storageFiles),
                'tools' => count($tools),
                'gaps' => array_sum(array_map('count', $gaps)),
            ],
        ];
    }

    private static function routeDesc(string $path, string $method = 'GET'): string {
        $methodPath = $path . '|' . $method;
        $descs = [
            '/docs'           => 'Documentation landing — blog categories overview',
            '/blog'           => 'Blog listing — all posts with category filters',
            '/blog/{slug}'    => 'Blog post — rendered from GitHub-sourced markdown',
            '/blog/category/{slug}' => 'Blog listing filtered by category',
            '/'                => 'Home page — hero, categories, featured products, astrologers',
            '/about'           => 'About SPS — story, values, CTA',
            '/sri-panchami-spiritual' => 'Sri Panchami Spiritual landing page',
            '/spiritual'        => 'Short spiritual landing redirect-equivalent page',
            '/terms'           => 'Terms of Service — 15 sections, legal',
            '/privacy'         => 'Privacy Policy — 14 sections, data handling',
            '/consult'         => 'Astrologer marketplace — browse and book',
            '/consult/{slug}'  => 'Consultant profile — request a scheduled appointment',
            '/temples'         => 'Temple listing page',
            '/temples/{slug}'  => 'Temple detail page',
            '/shop'            => 'Product shop — grid with pill actions',
            '/categories'      => 'Products filtered by category',
            '/product/{slug}'  => 'Product detail — gallery, add-to-cart, buy-now',
            '/contact'         => 'Contact form GET',
            '/contact|POST'    => 'Contact form POST submission',
            '/login'           => 'Login page — email/username + Google OAuth',
            '/logout'          => 'Logout — session destroy, redirect',
            '/register'        => 'Register page — identity, default delivery address, password, terms',
            '/register|POST'   => 'Register POST — create user and default address, accept terms',
            '/login|POST'      => 'Login POST — authenticate, session start',
            '/forgot-password' => 'Forgot password page',
            '/forgot-password|POST' => 'Forgot password POST — generate reset token',
            '/reset-password'  => 'Reset password page with token',
            '/reset-password|POST'  => 'Reset password POST — update hash',
            '/auth/google'     => 'Google OAuth redirect',
            '/auth/google/callback' => 'Google OAuth callback — upsert user',
            '/cart'            => 'Cart page — items, qty, totals',
            '/checkout'        => 'Checkout page — address, Razorpay payment',
            '/cart/add'        => 'Cart — add item',
            '/cart/remove'     => 'Cart — remove item',
            '/cart/update'     => 'Cart — update quantity',
            '/checkout/create-order' => 'Checkout — create Razorpay order',
            '/payment/verify'  => 'Checkout — verify payment, create order',
            '/create-order'    => 'API checkout — Razorpay order',
            '/verify-payment'  => 'API checkout — verify payment',
            '/account/dashboard' => 'Account dashboard entry — redirects to orders',
            '/account/dashboard/orders' => 'My Orders — product reviews',
            '/account/dashboard/sessions' => 'My Sessions — astrologer bookings',
            '/account/dashboard/install' => 'Install App — customer installation guidance',
            '/account/orders'  => 'Legacy account orders redirect',
            '/account/bookings' => 'Legacy account sessions redirect',
            '/api/consultations/{id}/status|POST' => 'API — update appointment status',
            '/consultation/initiate|POST' => 'Request a scheduled consultant appointment',
            '/reviews/astrologer|POST' => 'Submit astrologer review',
            '/reviews/product|POST'    => 'Submit product review',
            '/support/ask|POST' => 'Support bot — AI-powered Q&A',
            '/api/support/latest-message|GET' => 'Support — latest ticket message for TTS polling',
            '/api/tts/tokenize|POST' => 'TTS — tokenize text for KittenTTS ONNX model',
            '/admin'            => 'Admin dashboard — counts overview',
            '/admin/products'   => 'Admin — manage products',
            '/admin/categories' => 'Admin — manage categories',
            '/admin/coupons'    => 'Admin — manage coupons',
            '/admin/orders'     => 'Admin — order list',
            '/admin/orders/{id}' => 'Admin — order detail, status update',
            '/admin/orders/{id}/status|POST' => 'Admin — update order status + email',
            '/admin/shipping'   => 'Admin — shipping settings',
            '/admin/astrologers' => 'Admin — manage astrologers',
            '/admin/appointments' => 'Admin — session list',
            '/admin/consultation-analytics' => 'Admin — consultation metrics',
            '/admin/temples'    => 'Admin — manage temples',
            '/admin/settings'   => 'Admin — site settings',
            '/admin/settings/save|POST' => 'Admin — save settings',
            '/admin/settings/admin-credentials|POST' => 'Admin — save admin login',
            '/admin/integrations' => 'Admin — API keys, integrations',
            '/admin/backups'    => 'Admin — backup list',
            '/admin/audit-log'  => 'Admin — audit log',
            '/admin/contact-submissions' => 'Admin — contact form entries',
            '/admin/contact_submissions/save|POST' => 'Admin — save contact submission',
            '/admin/contact_submissions/delete|POST' => 'Admin — delete contact submission',
            '/admin/support-tickets' => 'Admin — support tickets',
            '/admin/appearance' => 'Admin — logo & favicon',
            '/admin/appearance/save|POST' => 'Admin — save logo/favicon',
            '/admin/media'      => 'Admin — media library',
            '/admin/media/upload|POST' => 'Admin — upload media',
            '/admin/environment/fix-permissions|POST' => 'Admin — fix storage permissions',
            '/admin/developer/project-map' => 'Admin — project map viewer',
            '/admin/developer/workflow' => 'Admin — agent workflow viewer',
            '/admin/products/save|POST' => 'Admin — create/update product',
            '/admin/products/delete|POST' => 'Admin — delete product',
            '/admin/categories/save|POST' => 'Admin — create/update category',
            '/admin/categories/delete|POST' => 'Admin — delete category',
            '/admin/coupons/save|POST' => 'Admin — create/update coupon',
            '/admin/coupons/delete|POST' => 'Admin — delete coupon',
            '/admin/astrologers/save|POST' => 'Admin — create/update astrologer',
            '/admin/astrologers/delete|POST' => 'Admin — delete astrologer',
            '/admin/temples/save|POST' => 'Admin — create/update temple',
            '/admin/temples/delete|POST' => 'Admin — delete temple',
            '/admin/integrations/save|POST' => 'Admin — save integration secrets',
            '/remoteDB|POST' => 'Remote DB query endpoint — proxies SQL to production MySQL',

        ];
        if (isset($descs[$methodPath])) return $descs[$methodPath];
        if (isset($descs[$path])) return $descs[$path];
        return '';
    }

    public static function renderSystematicMermaid(): string {
        $scan = self::scan();
        $lines = [
            'flowchart LR',
            '  classDef gap fill:#fee2e2,stroke:#b91c1c,color:#7f1d1d',
            '  classDef route fill:#e0f2fe,stroke:#0369a1,color:#0c4a6e',
            '  classDef code fill:#ecfdf5,stroke:#047857,color:#064e3b',
            '  classDef data fill:#fef3c7,stroke:#b45309,color:#78350f',
            '  classDef tool fill:#ede9fe,stroke:#6d28d9,color:#3b0764',
            '  classDef arch fill:#f1f5f9,stroke:#475569,color:#1e293b',
            '  classDef nav fill:#fff7ed,stroke:#c2410c,color:#7c2d12',
            '',
        ];

        $summary = $scan['summary'];
        $lines[] = '  subgraph ARCH["Architecture Overview — ' . $summary['routes'] . ' routes, ' . $summary['controllers'] . ' controllers, ' . $summary['services'] . ' services, ' . $summary['views'] . ' views, ' . $summary['storage_files'] . ' data files, ' . $summary['tools'] . ' tools"]';
        $lines[] = '    arch_routes["> Routes: ' . $summary['routes'] . ' (Public + Auth + Payment + Admin + Support)"]:::arch';
        $lines[] = '    arch_ctrl["> Controllers: ' . $summary['controllers'] . '"]:::arch';
        $lines[] = '    arch_svc["> Services: ' . $summary['services'] . '"]:::arch';
        $lines[] = '    arch_views["> Views: ' . $summary['views'] . '"]:::arch';
        $lines[] = '    arch_data["> Collections: ' . $summary['schema_collections'] . ' schema | ' . $summary['storage_files'] . ' files"]:::arch';
        $lines[] = '    arch_tools["> Tools: ' . $summary['tools'] . '"]:::arch';
        $lines[] = '    arch_gaps["> Gaps: ' . $summary['gaps'] . '"]:::arch';
        $lines[] = '    arch_routes -.-> arch_ctrl -.-> arch_svc -.-> arch_data';
        $lines[] = '    arch_ctrl -.-> arch_views';
        $lines[] = '  end';
        $lines[] = '';

        foreach (['PUBLIC', 'AUTH', 'PAYMENT', 'SUPPORT', 'ADMIN'] as $domain) {
            $routes = array_values(array_filter($scan['routes'], fn($route) => self::routeDomain($route) === $domain));
            $lines[] = '  subgraph ROUTES_' . $domain . '["' . $domain . ' Routes"]';
            foreach ($routes as $route) {
                $id = self::routeId($route);
                $mp = ($route['method'] ?? 'GET') . ' ' . ($route['path'] ?? '');
                $desc = self::routeDesc($route['path'] ?? '', $route['method'] ?? 'GET');
                $label = $desc ? $mp . ' — ' . $desc : $mp;
                $lines[] = '    ' . $id . '["' . self::label($label) . '"]:::route';
            }
            $lines[] = '  end';
            $lines[] = '';
        }

        $lines[] = '  subgraph NAVIGATION["Navigation Paths"]';
        foreach ($scan['navigation'] as $path) {
            $lines[] = '    ' . self::navId($path) . '["' . self::label($path) . '"]:::nav';
        }
        $lines[] = '  end';
        $lines[] = '';

        $groups = [
            'CONTROLLERS' => ['Controllers', $scan['controllers'], 'controllerId', 'code'],
            'SERVICES' => ['Services', $scan['services'], 'serviceId', 'code'],
            'VIEWS' => ['Views', $scan['views'], 'viewId', 'code'],
            'INTEGRATIONS' => ['Integrations', $scan['integrations'], 'integrationId', 'code'],
            'SCHEMA' => ['Schema Collections', $scan['schema_collections'], 'collectionId', 'data'],
            'STORAGE' => ['Storage Data Files', $scan['storage_files'], 'storageId', 'data'],
            'TOOLS' => ['Tools', $scan['tools'], 'toolId', 'tool'],
        ];

        foreach ($groups as $key => [$title, $items, $method, $class]) {
            if (empty($items)) continue;
            $lines[] = '  subgraph ' . $key . '["' . $title . '"]';
            foreach ($items as $item) {
                $lines[] = '    ' . self::{$method}($item) . '["' . self::label($item) . '"]:::' . $class;
            }
            $lines[] = '  end';
            $lines[] = '';
        }

        $gapNodes = [];
        $lines[] = '  subgraph GAPS["Gaps & Missing Links"]';
        foreach ($scan['gaps'] as $kind => $items) {
            foreach ($items as $index => $item) {
                if ($kind === 'admin_mutations_without_audit' && is_array($item)) {
                    $label = (($item['method'] ?? '') . ' ' . ($item['path'] ?? '') . ' — missing AuditLogService');
                } elseif (is_array($item)) {
                    $label = (($item['method'] ?? '') . ' ' . ($item['path'] ?? '') . ' missing mapping');
                } else {
                    $label = ($kind . ': ' . $item);
                }
                $id = 'gap_' . substr(md5($kind . $index . $label), 0, 10);
                $gapNodes[] = [$kind, $item, $id];
                $lines[] = '    ' . $id . '["' . self::label($label) . '"]:::gap';
            }
        }
        if ($gapNodes === []) {
            $lines[] = '    no_gaps["No detected gaps"]:::data';
        }
        $lines[] = '  end';
        $lines[] = '';

        foreach ($scan['routes'] as $route) {
            $routeId = self::routeId($route);
            $controller = (string)($route['controller'] ?? '');
            [$controllerClass] = array_pad(explode('@', $controller), 2, '');
            if ($controllerClass !== '') {
                $lines[] = '  ' . $routeId . ' --> ' . self::controllerId($controllerClass);
            }
            foreach ($route['services'] ?? [] as $service) {
                $lines[] = '  ' . self::controllerId($controllerClass) . ' --> ' . self::serviceId($service);
            }
            if (!empty($route['page'])) {
                $lines[] = '  ' . self::controllerId($controllerClass) . ' -. renders .-> ' . self::viewId((string)$route['page']);
            }
        }

        $routeByGetPath = [];
        foreach ($scan['routes'] as $route) {
            if (($route['method'] ?? 'GET') === 'GET') $routeByGetPath[(string)$route['path']] = $route;
        }
        foreach ($scan['navigation'] as $path) {
            if (isset($routeByGetPath[$path])) {
                $lines[] = '  ' . self::navId($path) . ' --> ' . self::routeId($routeByGetPath[$path]);
            }
        }

        foreach ($scan['controllers'] as $controller) {
            if ($controller !== 'BaseController') $lines[] = '  ' . self::controllerId($controller) . ' --> ' . self::controllerId('BaseController');
        }
        $lines[] = '  ' . self::controllerId('BaseController') . ' --> ' . self::serviceId('SeoService');
        $lines[] = '  ' . self::toolId('process-mail-queue') . ' --> ' . self::serviceId('SmtpMailer');
        if (in_array('import-product-images', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('import-product-images') . ' --> ' . self::serviceId('ImageOptimizerService');
            $lines[] = '  ' . self::toolId('import-product-images') . ' --> ' . self::serviceId('DatabaseService');
            $lines[] = '  ' . self::toolId('import-product-images') . ' --> ' . self::collectionId('products');
        }

        foreach (self::serviceCollections() as $service => $collections) {
            foreach ($collections as $collection) {
                $lines[] = '  ' . self::serviceId($service) . ' --> ' . self::collectionId($collection);
            }
        }
        foreach ($scan['schema_collections'] as $collection) {
            if (in_array($collection, $scan['storage_files'], true)) {
                $lines[] = '  ' . self::collectionId($collection) . ' --> ' . self::storageId($collection);
            }
        }
        foreach ($scan['routes'] as $route) {
            $controller = (string)($route['controller'] ?? '');
            [$controllerClass] = array_pad(explode('@', $controller), 2, '');
            $path = (string)($route['path'] ?? '');
            if (str_contains($path, 'auth/google')) {
                $lines[] = '  ' . self::controllerId($controllerClass) . ' --> ' . self::integrationId('GoogleOAuthClient');
            }
            if (str_contains($path, 'payment') || str_contains($path, 'checkout') || str_contains($path, 'recharge')) {
                $lines[] = '  ' . self::serviceId('PaymentService') . ' --> ' . self::integrationId('RazorpayClient');
                $lines[] = '  ' . self::serviceId('PaymentService') . ' --> ' . self::integrationId('StripeClient');
            }
        }
        $lines[] = '  ' . self::serviceId('SupportBotService') . ' --> ' . self::integrationId('GoogleSiteKitClient');
        if (in_array('generate-project-map', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('generate-project-map') . ' --> systematic_map["docs/systematic-map.mmd"]:::data';
        }
        if (in_array('validate-project-map', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('validate-project-map') . ' --> systematic_map';
        }
        if (in_array('smoke-local', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('smoke-local') . ' --> ROUTES_PUBLIC';
            $lines[] = '  ' . self::toolId('smoke-local') . ' --> ROUTES_ADMIN';
        }

        foreach ($gapNodes as [$kind, $item, $id]) {
            if (is_string($item)) {
                if (str_contains($kind, 'service')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::serviceId($item);
                } elseif (str_contains($kind, 'view')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::viewId($item);
                } elseif (str_contains($kind, 'controller')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::controllerId($item);
                } elseif (str_contains($kind, 'schema') || str_contains($kind, 'collection')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::collectionId($item);
                }
            } elseif (is_array($item) && !empty($item['controller'])) {
                [$ctrlClass] = array_pad(explode('@', (string)$item['controller']), 2, '');
                if ($ctrlClass !== '') {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::controllerId($ctrlClass);
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    public static function phpBasenames(string $dir): array {
        if (!is_dir($dir)) return [];
        $files = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)));
        $names = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') $names[] = $file->getBasename('.php');
        }
        sort($names);
        return $names;
    }

    private static function viewNames(string $dir): array {
        if (!is_dir($dir)) return [];
        $files = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)));
        $names = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $relative = str_replace($dir . '/', '', $file->getPathname());
            $names[] = substr($relative, 0, -4);
        }
        sort($names);
        return $names;
    }

    private static function jsonBasenames(string $dir): array {
        if (!is_dir($dir)) return [];
        $names = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $name = basename($file, '.json');
            if (in_array($name, self::RUNTIME_JSON_STORES, true)) continue;
            $names[] = $name;
        }
        sort($names);
        return $names;
    }

    public static function serviceCollections(): array {
        $mapping = [];
        static $collectionPattern = '/(\$this->store\s*->\s*(?:find|read|create|write|upsert|delete)\s*\(\s*)([\'"])([a-z_]+)\2\s*[\),]/';
        static $resourcePattern = '/new\s+ResourceService\s*\(\s*([\'"])([a-z_]+)\1\s*\)/';

        foreach (self::phpFiles(app_path('app/Services')) as $file) {
            $content = file_get_contents($file);
            $name = basename($file, '.php');
            $collections = [];

            preg_match_all($collectionPattern, $content, $direct, PREG_SET_ORDER);
            foreach ($direct as $m) {
                $collections[$m[3]] = true;
            }

            if ($collections) {
                $mapping[$name] = array_keys($collections);
            }
        }

        $resourceCollections = [];
        foreach (self::phpFiles(app_path('app/Controllers')) as $file) {
            $content = file_get_contents($file);
            preg_match_all($resourcePattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $resourceCollections[$m[2]] = true;
            }
        }
        if ($resourceCollections) {
            $mapping['ResourceService'] = array_keys($resourceCollections);
        }

        self::applyManualOverrides($mapping);

        return $mapping;
    }

    private static function phpFiles(string $dir): array {
        if (!is_dir($dir)) return [];
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->getExtension() === 'php') $files[] = $f->getPathname();
        }
        sort($files);
        return $files;
    }

    private static function applyManualOverrides(array &$mapping): void {
        $overrides = [
            'AgentContextService' => ['users', 'orders', 'appointments', 'support_tickets'],
            'DatabaseService' => ['users', 'addresses', 'products', 'orders', 'appointments', 'consultation_messages', 'consultation_signals', 'secrets', 'mail_inbox', 'mail_outbox', 'contact_submissions'],
        ];
        foreach ($overrides as $service => $cols) {
            $existing = $mapping[$service] ?? [];
            $mapping[$service] = array_values(array_unique(array_merge($existing, $cols)));
        }
    }

    private static function navigationPaths(): array {
        return [
            '/contact',
            '/account/dashboard',
            '/account/dashboard/orders',
            '/account/dashboard/sessions',
            '/account/dashboard/install',
        ];
    }

    private static function routeDomain(array $route): string {
        $path = (string)($route['path'] ?? '');
        if (str_starts_with($path, '/admin')) return 'ADMIN';
        if (str_starts_with($path, '/support')) return 'SUPPORT';
        if (str_starts_with($path, '/auth') || in_array($path, ['/login', '/logout', '/register', '/forgot-password', '/reset-password'], true)) return 'AUTH';
        if (str_starts_with($path, '/cart') || str_starts_with($path, '/checkout') || str_starts_with($path, '/payment') || str_starts_with($path, '/recharge') || in_array($path, ['/create-order', '/verify-payment'], true)) return 'PAYMENT';
        return 'PUBLIC';
    }

    private static function label(string $value): string {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }

    private static function nodeId(string $prefix, string $value): string {
        $stable = preg_replace('/[^a-zA-Z0-9]/', '_', $value);
        $stable = preg_replace('/_+/', '_', $stable);
        $stable = trim($stable, '_');
        return $prefix . '_' . strtolower(substr($stable, 0, 48));
    }

    private static function routeId(array $route): string { return self::nodeId('route', ($route['method'] ?? '') . ' ' . ($route['path'] ?? '')); }
    private static function controllerId(string $name): string { return self::nodeId('controller', $name); }
    private static function serviceId(string $name): string { return self::nodeId('service', $name); }
    private static function viewId(string $name): string { return self::nodeId('view', $name); }
    private static function integrationId(string $name): string { return self::nodeId('integration', $name); }
    private static function collectionId(string $name): string { return self::nodeId('collection', $name); }
    private static function storageId(string $name): string { return self::nodeId('storage', $name); }
    private static function toolId(string $name): string { return self::nodeId('tool', $name); }
    private static function navId(string $name): string { return self::nodeId('nav', $name); }
}
