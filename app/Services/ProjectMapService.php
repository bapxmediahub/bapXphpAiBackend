<?php
namespace App\Services;
final class ProjectMapService {
    private const PROJECT_MAP_EXCLUDED_STORAGE = [
        'adminsecrets',
        'settings.secrets',
        'test_race',
    ];
    private const NON_ROUTE_CONTROLLERS = ['BaseController'];
    private const NON_ROUTE_SERVICES = ['SmtpMailer'];
    private const SHARED_OR_ERROR_VIEWS = ['layouts/admin', 'layouts/app', 'public/404'];

    public static function registry(): array {
        $routes = [
            ['method'=>'GET','path'=>'/','name'=>'home','page'=>'public/home','controller'=>'PublicController@home','services'=>['ProductService','AstrologerService','TempleService','CategoryService']],
            ['method'=>'GET','path'=>'/about','name'=>'about','page'=>'public/about','controller'=>'PublicController@about','services'=>[]],
            ['method'=>'GET','path'=>'/sri-panchami-spiritual','name'=>'spiritual','page'=>'public/spiritual','controller'=>'PublicController@spiritual','services'=>[]],
            ['method'=>'GET','path'=>'/spiritual','name'=>'spiritual.alias','page'=>'public/spiritual','controller'=>'PublicController@spiritual','services'=>[]],
            ['method'=>'GET','path'=>'/consult','name'=>'consult','page'=>'public/consult','controller'=>'PublicController@consult','services'=>['AstrologerService']],
            ['method'=>'GET','path'=>'/consult/{slug}','name'=>'consult.show','page'=>'public/astrologer','controller'=>'PublicController@consultant','services'=>['AstrologerService']],
            ['method'=>'GET','path'=>'/temples','name'=>'temples','page'=>'public/temples','controller'=>'PublicController@temples','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/temples/{slug}','name'=>'temple.show','page'=>'public/temple','controller'=>'PublicController@temple','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/shop','name'=>'shop','page'=>'public/shop','controller'=>'PublicController@shop','services'=>['ProductService','CategoryService']],
            ['method'=>'GET','path'=>'/categories','name'=>'categories','page'=>'public/shop','controller'=>'PublicController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/product/{slug}','name'=>'product.show','page'=>'public/product','controller'=>'PublicController@product','services'=>['ProductService']],
            ['method'=>'GET','path'=>'/cart','name'=>'cart','page'=>'public/cart','controller'=>'PublicController@cart','services'=>['CartService','ProductService']],
            ['method'=>'GET','path'=>'/checkout','name'=>'checkout','page'=>'public/checkout','controller'=>'PublicController@checkout','services'=>['CartService','ProductService','SecretService']],
            ['method'=>'GET','path'=>'/contact','name'=>'contact','page'=>'public/contact','controller'=>'PublicController@contact','services'=>[]],
            ['method'=>'POST','path'=>'/contact','name'=>'contact.post','page'=>'public/contact','controller'=>'PublicController@contact','services'=>['ContactService']],
            ['method'=>'GET','path'=>'/login','name'=>'login','page'=>'public/login','controller'=>'PublicController@login','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/forgot-password','name'=>'forgot-password','page'=>'public/forgot-password','controller'=>'AuthController@forgotPassword','services'=>['PasswordResetService']],
            ['method'=>'POST','path'=>'/forgot-password','name'=>'forgot-password.post','page'=>'public/forgot-password','controller'=>'AuthController@forgotPasswordPost','services'=>['PasswordResetService']],
            ['method'=>'GET','path'=>'/reset-password','name'=>'reset-password','page'=>'public/reset-password','controller'=>'AuthController@resetPassword','services'=>['PasswordResetService']],
            ['method'=>'POST','path'=>'/reset-password','name'=>'reset-password.post','page'=>'public/reset-password','controller'=>'AuthController@resetPasswordPost','services'=>['PasswordResetService']],
            ['method'=>'GET','path'=>'/auth/google','name'=>'auth.google','page'=>'public/login','controller'=>'AuthController@googleRedirect','services'=>['SecretService']],
            ['method'=>'GET','path'=>'/auth/google/callback','name'=>'auth.google.callback','page'=>'public/login','controller'=>'AuthController@callback','services'=>['SecretService','JsonStoreService']],
            ['method'=>'GET','path'=>'/register','name'=>'register','page'=>'public/register','controller'=>'AuthController@register','services'=>[]],
            ['method'=>'POST','path'=>'/register','name'=>'register.post','page'=>'public/register','controller'=>'AuthController@registerPost','services'=>['JsonStoreService']],
            ['method'=>'POST','path'=>'/login','name'=>'login.post','page'=>'public/login','controller'=>'AuthController@loginPost','services'=>['JsonStoreService']],
            ['method'=>'GET','path'=>'/logout','name'=>'logout','page'=>'public/login','controller'=>'AuthController@logout','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/orders','name'=>'account.orders','page'=>'account/orders','controller'=>'AccountController@orders','services'=>['AuthService','OrderService']],
            ['method'=>'GET','path'=>'/account/bookings','name'=>'account.bookings','page'=>'account/bookings','controller'=>'AccountController@bookings','services'=>['AuthService','AppointmentService']],
            ['method'=>'GET','path'=>'/account/wallet','name'=>'account.wallet','page'=>'account/wallet','controller'=>'AccountController@wallet','services'=>['AuthService','WalletService']],
            ['method'=>'GET','path'=>'/consultation/{id}','name'=>'consultation.room','page'=>'account/consultation','controller'=>'ConsultationController@room','services'=>['AuthService','ConsultationService']],
            ['method'=>'GET','path'=>'/astrologer','name'=>'astrologer.dashboard','page'=>'astrologer/dashboard','controller'=>'AstrologerController@dashboard','services'=>['AuthService','AstrologerService','ConsultationService']],
            ['method'=>'GET','path'=>'/astrologer/change-password','name'=>'astrologer.password','page'=>'astrologer/change-password','controller'=>'AstrologerController@changePassword','services'=>['AuthService']],
            ['method'=>'POST','path'=>'/astrologer/change-password','name'=>'astrologer.password.save','page'=>'astrologer/change-password','controller'=>'AstrologerController@savePassword','services'=>['AuthService','JsonStoreService']],
            ['method'=>'GET','path'=>'/api/consultations/{id}/messages','name'=>'api.consultation.messages','page'=>'account/consultation','controller'=>'ConsultationController@messages','services'=>['AuthService','ConsultationService']],
            ['method'=>'POST','path'=>'/api/consultations/{id}/messages','name'=>'api.consultation.messages.send','page'=>'account/consultation','controller'=>'ConsultationController@sendMessage','services'=>['AuthService','ConsultationService']],
            ['method'=>'GET','path'=>'/api/consultations/{id}/signals','name'=>'api.consultation.signals','page'=>'account/consultation','controller'=>'ConsultationController@signals','services'=>['AuthService','ConsultationService']],
            ['method'=>'POST','path'=>'/api/consultations/{id}/signals','name'=>'api.consultation.signals.send','page'=>'account/consultation','controller'=>'ConsultationController@sendSignal','services'=>['AuthService','ConsultationService']],
            ['method'=>'POST','path'=>'/api/consultations/{id}/status','name'=>'api.consultation.status','page'=>'account/consultation','controller'=>'ConsultationController@status','services'=>['AuthService','ConsultationService']],
            ['method'=>'GET','path'=>'/recharge','name'=>'wallet.recharge','page'=>'account/wallet','controller'=>'WalletController@show','services'=>['AuthService','WalletService','SecretService']],
            ['method'=>'POST','path'=>'/recharge/create-order','name'=>'wallet.recharge.create-order','page'=>'account/wallet','controller'=>'WalletController@createOrder','services'=>['AuthService','WalletService','SecretService','PaymentService']],
            ['method'=>'POST','path'=>'/recharge/verify','name'=>'wallet.recharge.verify','page'=>'account/wallet','controller'=>'WalletController@verify','services'=>['AuthService','WalletService','SecretService','PaymentService']],
            ['method'=>'GET','path'=>'/admin','name'=>'admin.dashboard','page'=>'admin/dashboard','controller'=>'AdminController@dashboard','services'=>['OrderService','AppointmentService']],
            ['method'=>'GET','path'=>'/admin/products','name'=>'admin.products','page'=>'admin/resource','controller'=>'AdminController@products','services'=>['ProductService','SchemaService']],
            ['method'=>'GET','path'=>'/admin/categories','name'=>'admin.categories','page'=>'admin/resource','controller'=>'AdminController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/admin/coupons','name'=>'admin.coupons','page'=>'admin/resource','controller'=>'AdminController@coupons','services'=>['CouponService']],
            ['method'=>'GET','path'=>'/admin/orders','name'=>'admin.orders','page'=>'admin/list','controller'=>'AdminController@orders','services'=>['OrderService']],
            ['method'=>'GET','path'=>'/admin/orders/{id}','name'=>'admin.order.show','page'=>'admin/detail','controller'=>'AdminController@order','services'=>['OrderService','ShippingService']],
            ['method'=>'POST','path'=>'/admin/orders/{id}/status','name'=>'admin.order.status','page'=>'admin/detail','controller'=>'AdminController@saveOrderStatus','services'=>['OrderService','MailQueueService']],
            ['method'=>'GET','path'=>'/admin/shipping','name'=>'admin.shipping','page'=>'admin/settings','controller'=>'AdminController@shipping','services'=>['ShippingService','SettingsService']],
            ['method'=>'GET','path'=>'/admin/astrologers','name'=>'admin.astrologers','page'=>'admin/resource','controller'=>'AdminController@astrologers','services'=>['AstrologerService','SchemaService']],
            ['method'=>'GET','path'=>'/admin/appointments','name'=>'admin.appointments','page'=>'admin/list','controller'=>'AdminController@appointments','services'=>['AppointmentService']],
            ['method'=>'GET','path'=>'/admin/astrologer-credentials','name'=>'admin.astrologer-credentials','page'=>'admin/astrologer-credentials','controller'=>'AdminController@astrologerCredentials','services'=>['AstrologerService','JsonStoreService']],
            ['method'=>'GET','path'=>'/admin/consultation-analytics','name'=>'admin.consultation-analytics','page'=>'admin/consultation-analytics','controller'=>'AdminController@consultationAnalytics','services'=>['ConsultationService']],
            ['method'=>'GET','path'=>'/admin/temples','name'=>'admin.temples','page'=>'admin/resource','controller'=>'AdminController@temples','services'=>['TempleService','SchemaService']],
            ['method'=>'GET','path'=>'/admin/settings','name'=>'admin.settings','page'=>'admin/settings','controller'=>'AdminController@settings','services'=>['SettingsService']],
            ['method'=>'POST','path'=>'/admin/settings/save','name'=>'admin.settings.save','page'=>'admin/settings','controller'=>'AdminController@saveSettings','services'=>['SettingsService']],
            ['method'=>'POST','path'=>'/admin/settings/admin-credentials','name'=>'admin.settings.admin-credentials','page'=>'admin/settings','controller'=>'AdminController@saveAdminCredentials','services'=>['EnvService']],
            ['method'=>'GET','path'=>'/admin/integrations','name'=>'admin.integrations','page'=>'admin/integrations','controller'=>'AdminController@integrations','services'=>['SettingsService','PaymentService','SecretService']],
            ['method'=>'GET','path'=>'/admin/backups','name'=>'admin.backups','page'=>'admin/list','controller'=>'AdminController@backups','services'=>['JsonStoreService']],
            ['method'=>'GET','path'=>'/admin/audit-log','name'=>'admin.audit','page'=>'admin/list','controller'=>'AdminController@audit','services'=>['AuditLogService']],
            ['method'=>'GET','path'=>'/admin/contact-submissions','name'=>'admin.contact-submissions','page'=>'admin/resource','controller'=>'AdminController@contactSubmissions','services'=>['ContactService']],
            ['method'=>'GET','path'=>'/admin/support-tickets','name'=>'admin.support-tickets','page'=>'admin/list','controller'=>'AdminController@supportTickets','services'=>['JsonStoreService']],
            ['method'=>'GET','path'=>'/admin/media','name'=>'admin.media','page'=>'admin/media','controller'=>'AdminController@media','services'=>['MediaService']],
            ['method'=>'POST','path'=>'/admin/media/upload','name'=>'admin.media.upload','page'=>'admin/media','controller'=>'AdminController@uploadMedia','services'=>['MediaService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/environment','name'=>'admin.environment','page'=>'admin/environment','controller'=>'AdminController@environment','services'=>['EnvService','StoragePermissionService']],
            ['method'=>'POST','path'=>'/admin/environment/save','name'=>'admin.environment.save','page'=>'admin/environment','controller'=>'AdminController@saveEnvironment','services'=>['EnvService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/environment/fix-permissions','name'=>'admin.environment.fix-permissions','page'=>'admin/environment','controller'=>'AdminController@fixPermissions','services'=>['StoragePermissionService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/developer/project-map','name'=>'admin.project-map','page'=>'admin/project-map','controller'=>'AdminController@projectMap','services'=>['ProjectMapService']],
            ['method'=>'POST','path'=>'/admin/products/save','name'=>'admin.products.save','page'=>'admin/resource','controller'=>'AdminController@saveProduct','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/products/delete','name'=>'admin.products.delete','page'=>'admin/resource','controller'=>'AdminController@deleteProduct','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/categories/save','name'=>'admin.categories.save','page'=>'admin/resource','controller'=>'AdminController@saveCategory','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/coupons/save','name'=>'admin.coupons.save','page'=>'admin/resource','controller'=>'AdminController@saveCoupon','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/coupons/delete','name'=>'admin.coupons.delete','page'=>'admin/resource','controller'=>'AdminController@deleteCoupon','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/astrologers/save','name'=>'admin.astrologers.save','page'=>'admin/resource','controller'=>'AdminController@saveAstrologer','services'=>['ResourceService','AstrologerAccountService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/astrologers/delete','name'=>'admin.astrologers.delete','page'=>'admin/resource','controller'=>'AdminController@deleteAstrologer','services'=>['ResourceService','AstrologerAccountService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/temples/save','name'=>'admin.temples.save','page'=>'admin/resource','controller'=>'AdminController@saveTemple','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/temples/delete','name'=>'admin.temples.delete','page'=>'admin/resource','controller'=>'AdminController@deleteTemple','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/integrations/save','name'=>'admin.integrations.save','page'=>'admin/integrations','controller'=>'AdminController@saveIntegrations','services'=>['SecretService']],
            ['method'=>'POST','path'=>'/cart/add','name'=>'cart.add','page'=>'public/cart','controller'=>'CommerceController@addToCart','services'=>['CartService','ProductService']],
            ['method'=>'POST','path'=>'/cart/remove','name'=>'cart.remove','page'=>'public/cart','controller'=>'CommerceController@removeFromCart','services'=>['CartService']],
            ['method'=>'POST','path'=>'/cart/update','name'=>'cart.update','page'=>'public/cart','controller'=>'CommerceController@updateCart','services'=>['CartService']],
            ['method'=>'POST','path'=>'/checkout/create-order','name'=>'checkout.create-order','page'=>'public/checkout','controller'=>'CommerceController@createOrder','services'=>['SecretService','PaymentService']],
            ['method'=>'POST','path'=>'/payment/verify','name'=>'payment.verify','page'=>'public/checkout','controller'=>'CommerceController@verifyPayment','services'=>['SecretService','PaymentService','JsonStoreService']],
            ['method'=>'POST','path'=>'/appointments/book','name'=>'appointments.book','page'=>'public/astrologer','controller'=>'BookingController@book','services'=>['AuthService','ResourceService','AstrologerService']],
            ['method'=>'POST','path'=>'/reviews/astrologer','name'=>'reviews.astrologer','page'=>'account/bookings','controller'=>'ReviewController@saveAstrologer','services'=>['ReviewService']],
            ['method'=>'POST','path'=>'/reviews/product','name'=>'reviews.product','page'=>'account/orders','controller'=>'ReviewController@saveProduct','services'=>['ReviewService']],
            ['method'=>'POST','path'=>'/support/ask','name'=>'support.ask','page'=>null,'response'=>'json','controller'=>'SupportController@ask','services'=>['SupportBotService','AgentContextService']],
        ];
        foreach ($routes as &$route) {
            if ((str_starts_with($route['path'], '/admin') || str_starts_with($route['path'], '/reviews')) && !in_array('AuthService', $route['services'], true)) {
                $route['services'][] = 'AuthService';
            }
        }
        unset($route);
        return [
            'routes'=>$routes,
            'services'=>['AuthService','ProductService','CategoryService','CouponService','CartService','OrderService','PaymentService','ShippingService','AstrologerService','AstrologerAccountService','AppointmentService','ConsultationService','TempleService','SettingsService','ProjectMapService','JsonStoreService','AuditLogService','ResourceService','SecretService','EnvService','ContactService','ReviewService','PasswordResetService','MailQueueService','WalletService','SupportBotService','MediaService','StoragePermissionService','SchemaService','AgentContextService'],
            'integrations'=>['GoogleOAuthClient','RazorpayClient'],
            'collections'=>['users','products','categories','coupons','orders','astrologers','appointments','consultation_messages','consultation_signals','temples','settings','audit_events','reviews','mail_queue','wallet_transactions','support_tickets','media_files','contact_submissions'],
        ];
    }
    public static function validate(array $map): array {
        $missingRouteMappings = array_values(array_filter($map['routes'], fn($r) => empty($r['controller']) || (empty($r['page']) && empty($r['response']))));
        $used = array_unique(array_merge(...array_map(fn($r) => $r['services'], $map['routes'])));
        $missingServices = array_values(array_diff($used, $map['services']));
        return ['missing_route_mappings'=>$missingRouteMappings,'missing_services'=>$missingServices,'missing_collections'=>array_values(array_diff(['users','products','categories','coupons','orders','astrologers','appointments','consultation_messages','consultation_signals','temples','settings','audit_events','reviews','mail_queue','wallet_transactions','support_tickets','media_files','contact_submissions'], $map['collections']))];
    }

    public static function scan(): array {
        $map = self::registry();
        $schema = json_decode((string)file_get_contents(app_path('storage/schema/collections.json')), true);
        $schemaCollections = array_keys($schema['collections'] ?? []);

        $controllers = self::phpBasenames(app_path('app/Controllers'));
        $services = self::phpBasenames(app_path('app/Services'));
        $views = self::viewNames(app_path('views'));
        $integrations = self::phpBasenames(app_path('integrations'));
        $tools = self::phpBasenames(app_path('tools'));
        $storageFiles = self::jsonBasenames(app_path('storage/data'));
        $navigation = self::internalNavigationPaths(app_path('views/layouts/app.php'));
        $getRoutePaths = array_values(array_unique(array_map(
            fn($route) => (string)$route['path'],
            array_filter($map['routes'], fn($route) => ($route['method'] ?? '') === 'GET')
        )));

        $routeControllers = array_values(array_unique(array_map(
            fn($route) => explode('@', (string)($route['controller'] ?? ''))[0] ?? '',
            $map['routes']
        )));
        $routeControllers = array_values(array_filter($routeControllers));
        $routeServices = array_values(array_unique(array_merge(...array_map(fn($route) => $route['services'], $map['routes']))));
        $routeViews = array_values(array_unique(array_filter(array_map(fn($route) => $route['page'] ?? '', $map['routes']))));

        $gaps = [
            'missing_route_mappings' => array_values(array_filter($map['routes'], fn($route) => empty($route['controller']) || (empty($route['page']) && empty($route['response'])))),
            'navigation_without_get_route' => array_values(array_diff($navigation, $getRoutePaths)),
            'missing_controller_files' => array_values(array_diff($routeControllers, $controllers)),
            'missing_service_files' => array_values(array_diff($routeServices, $services)),
            'missing_view_files' => array_values(array_diff($routeViews, $views)),
            'unwired_controllers' => array_values(array_diff($controllers, $routeControllers, self::NON_ROUTE_CONTROLLERS)),
            'unwired_services' => array_values(array_diff($services, $routeServices, self::NON_ROUTE_SERVICES)),
            'unwired_views' => array_values(array_diff($views, $routeViews, self::SHARED_OR_ERROR_VIEWS)),
            'file_without_schema' => array_values(array_diff($storageFiles, $schemaCollections)),
        ];

        return [
            'routes' => $map['routes'],
            'controllers' => $controllers,
            'services' => $services,
            'views' => $views,
            'integrations' => $integrations,
            'schema_collections' => $schemaCollections,
            'storage_files' => $storageFiles,
            'tools' => $tools,
            'navigation' => $navigation,
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
                'navigation' => count($navigation),
                'gaps' => array_sum(array_map('count', $gaps)),
            ],
        ];
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
            '  classDef nav fill:#fce7f3,stroke:#be185d,color:#831843',
            '',
        ];

        foreach (['PUBLIC', 'AUTH', 'PAYMENT', 'SUPPORT', 'ADMIN'] as $domain) {
            $routes = array_values(array_filter($scan['routes'], fn($route) => self::routeDomain($route) === $domain));
            $lines[] = '  subgraph ROUTES_' . $domain . '["' . $domain . ' Routes"]';
            foreach ($routes as $route) {
                $id = self::routeId($route);
                $lines[] = '    ' . $id . '["' . self::label(($route['method'] ?? 'GET') . ' ' . ($route['path'] ?? '')) . '"]:::route';
            }
            $lines[] = '  end';
            $lines[] = '';
        }

        $groups = [
            'CONTROLLERS' => ['Controllers', $scan['controllers'], 'controllerId', 'code'],
            'SERVICES' => ['Services', $scan['services'], 'serviceId', 'code'],
            'VIEWS' => ['Views', $scan['views'], 'viewId', 'code'],
            'INTEGRATIONS' => ['Integrations', $scan['integrations'], 'integrationId', 'code'],
            'SCHEMA' => ['Schema Collections', $scan['schema_collections'], 'collectionId', 'data'],
            'STORAGE' => ['Storage Data Files', $scan['storage_files'], 'storageId', 'data'],
            'TOOLS' => ['Tools', $scan['tools'], 'toolId', 'tool'],
            'NAVIGATION' => ['Navigation Paths', $scan['navigation'], 'navigationId', 'nav'],
        ];

        foreach ($groups as $key => [$title, $items, $method, $class]) {
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
                $label = is_array($item) ? (($item['method'] ?? '') . ' ' . ($item['path'] ?? '') . ' missing mapping') : ($kind . ': ' . $item);
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

        foreach ($scan['navigation'] as $path) {
            foreach ($scan['routes'] as $route) {
                if (($route['method'] ?? '') === 'GET' && ($route['path'] ?? '') === $path) {
                    $lines[] = '  ' . self::navigationId($path) . ' --> ' . self::routeId($route);
                    break;
                }
            }
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
            }
        }
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
        if (in_array('process-mail-queue', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('process-mail-queue') . ' --> ' . self::serviceId('SmtpMailer');
        }

        foreach ($gapNodes as [$kind, $item, $id]) {
            if (is_string($item)) {
                if (str_contains($kind, 'service')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::serviceId($item);
                } elseif (str_contains($kind, 'view')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::viewId($item);
                } elseif (str_contains($kind, 'controller')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::controllerId($item);
                } elseif ($kind === 'file_without_schema') {
                    $lines[] = '  ' . self::storageId($item) . ' -. missing schema .-> ' . $id;
                } elseif ($kind === 'navigation_without_get_route') {
                    $lines[] = '  ' . self::navigationId($item) . ' -. missing route .-> ' . $id;
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private static function phpBasenames(string $dir): array {
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
            if (in_array($name, self::PROJECT_MAP_EXCLUDED_STORAGE, true)) continue;
            $names[] = $name;
        }
        sort($names);
        return $names;
    }

    private static function internalNavigationPaths(string $layout): array {
        if (!is_file($layout)) return [];
        preg_match_all('/href="(\/[a-zA-Z0-9_\/-]*)"/', (string)file_get_contents($layout), $matches);
        $paths = array_values(array_unique($matches[1] ?? []));
        sort($paths);
        return $paths;
    }

    private static function serviceCollections(): array {
        return [
            'AgentContextService' => ['users', 'orders', 'appointments', 'wallet_transactions', 'support_tickets'],
            'AppointmentService' => ['appointments'],
            'AstrologerService' => ['astrologers'],
            'AstrologerAccountService' => ['users', 'astrologers'],
            'ConsultationService' => ['appointments', 'consultation_messages', 'consultation_signals'],
            'AuditLogService' => ['audit_events'],
            'CategoryService' => ['categories'],
            'ContactService' => ['contact_submissions'],
            'CouponService' => ['coupons'],
            'JsonStoreService' => ['users', 'products', 'orders', 'appointments', 'consultation_messages', 'consultation_signals'],
            'MailQueueService' => ['mail_queue'],
            'MediaService' => ['media_files'],
            'OrderService' => ['orders'],
            'ProductService' => ['products'],
            'ResourceService' => ['products', 'categories', 'coupons', 'astrologers', 'temples'],
            'ReviewService' => ['reviews'],
            'SettingsService' => ['settings'],
            'SupportBotService' => ['support_tickets'],
            'TempleService' => ['temples'],
            'WalletService' => ['wallet_transactions', 'users'],
        ];
    }

    private static function routeDomain(array $route): string {
        $path = (string)($route['path'] ?? '');
        if (str_starts_with($path, '/admin')) return 'ADMIN';
        if (str_starts_with($path, '/support')) return 'SUPPORT';
        if (str_starts_with($path, '/auth') || in_array($path, ['/login', '/logout', '/register', '/forgot-password', '/reset-password'], true)) return 'AUTH';
        if (str_starts_with($path, '/cart') || str_starts_with($path, '/checkout') || str_starts_with($path, '/payment') || str_starts_with($path, '/recharge')) return 'PAYMENT';
        return 'PUBLIC';
    }

    private static function label(string $value): string {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }

    private static function nodeId(string $prefix, string $value): string {
        return $prefix . '_' . substr(md5($value), 0, 12);
    }

    private static function routeId(array $route): string { return self::nodeId('route', ($route['method'] ?? '') . ' ' . ($route['path'] ?? '')); }
    private static function controllerId(string $name): string { return self::nodeId('controller', $name); }
    private static function serviceId(string $name): string { return self::nodeId('service', $name); }
    private static function viewId(string $name): string { return self::nodeId('view', $name); }
    private static function integrationId(string $name): string { return self::nodeId('integration', $name); }
    private static function collectionId(string $name): string { return self::nodeId('collection', $name); }
    private static function storageId(string $name): string { return self::nodeId('storage', $name); }
    private static function toolId(string $name): string { return self::nodeId('tool', $name); }
    private static function navigationId(string $path): string { return self::nodeId('navigation', $path); }
}
