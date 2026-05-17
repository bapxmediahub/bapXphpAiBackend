<?php
namespace App\Services;
final class ProjectMapService {
    public static function registry(): array {
        $routes = [
            ['method'=>'GET','path'=>'/','name'=>'home','page'=>'public/home','controller'=>'PublicController@home','services'=>['ProductService','AstrologerService','TempleService']],
            ['method'=>'GET','path'=>'/about','name'=>'about','page'=>'public/about','controller'=>'PublicController@about','services'=>[]],
            ['method'=>'GET','path'=>'/sri-panchami-spiritual','name'=>'spiritual','page'=>'public/spiritual','controller'=>'PublicController@spiritual','services'=>[]],
            ['method'=>'GET','path'=>'/astrologers','name'=>'astrologers','page'=>'public/astrologers','controller'=>'PublicController@astrologers','services'=>['AstrologerService']],
            ['method'=>'GET','path'=>'/astrologers/{slug}','name'=>'astrologer.show','page'=>'public/astrologer','controller'=>'PublicController@astrologer','services'=>['AstrologerService','AvailabilityService']],
            ['method'=>'GET','path'=>'/temples','name'=>'temples','page'=>'public/temples','controller'=>'PublicController@temples','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/temples/{slug}','name'=>'temple.show','page'=>'public/temple','controller'=>'PublicController@temple','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/shop','name'=>'shop','page'=>'public/shop','controller'=>'PublicController@shop','services'=>['ProductService','CategoryService']],
            ['method'=>'GET','path'=>'/product/{slug}','name'=>'product.show','page'=>'public/product','controller'=>'PublicController@product','services'=>['ProductService']],
            ['method'=>'GET','path'=>'/cart','name'=>'cart','page'=>'public/cart','controller'=>'PublicController@cart','services'=>['CartService']],
            ['method'=>'GET','path'=>'/checkout','name'=>'checkout','page'=>'public/checkout','controller'=>'PublicController@checkout','services'=>['AuthService','CartService','PaymentService']],
            ['method'=>'GET','path'=>'/contact','name'=>'contact','page'=>'public/contact','controller'=>'PublicController@contact','services'=>[]],
            ['method'=>'GET','path'=>'/login','name'=>'login','page'=>'public/login','controller'=>'PublicController@login','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/orders','name'=>'account.orders','page'=>'account/orders','controller'=>'AccountController@orders','services'=>['AuthService','OrderService']],
            ['method'=>'GET','path'=>'/account/bookings','name'=>'account.bookings','page'=>'account/bookings','controller'=>'AccountController@bookings','services'=>['AuthService','AppointmentService']],
            ['method'=>'GET','path'=>'/admin','name'=>'admin.dashboard','page'=>'admin/dashboard','controller'=>'AdminController@dashboard','services'=>['OrderService','AppointmentService']],
            ['method'=>'GET','path'=>'/admin/products','name'=>'admin.products','page'=>'admin/list','controller'=>'AdminController@products','services'=>['ProductService']],
            ['method'=>'GET','path'=>'/admin/categories','name'=>'admin.categories','page'=>'admin/list','controller'=>'AdminController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/admin/coupons','name'=>'admin.coupons','page'=>'admin/list','controller'=>'AdminController@coupons','services'=>['CouponService']],
            ['method'=>'GET','path'=>'/admin/orders','name'=>'admin.orders','page'=>'admin/list','controller'=>'AdminController@orders','services'=>['OrderService']],
            ['method'=>'GET','path'=>'/admin/orders/{id}','name'=>'admin.order.show','page'=>'admin/detail','controller'=>'AdminController@order','services'=>['OrderService','ShippingService']],
            ['method'=>'GET','path'=>'/admin/shipping','name'=>'admin.shipping','page'=>'admin/settings','controller'=>'AdminController@shipping','services'=>['ShippingService','SettingsService']],
            ['method'=>'GET','path'=>'/admin/astrologers','name'=>'admin.astrologers','page'=>'admin/list','controller'=>'AdminController@astrologers','services'=>['AstrologerService','AvailabilityService']],
            ['method'=>'GET','path'=>'/admin/appointments','name'=>'admin.appointments','page'=>'admin/list','controller'=>'AdminController@appointments','services'=>['AppointmentService','CalendarService']],
            ['method'=>'GET','path'=>'/admin/temples','name'=>'admin.temples','page'=>'admin/list','controller'=>'AdminController@temples','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/admin/settings','name'=>'admin.settings','page'=>'admin/settings','controller'=>'AdminController@settings','services'=>['SettingsService']],
            ['method'=>'GET','path'=>'/admin/integrations','name'=>'admin.integrations','page'=>'admin/settings','controller'=>'AdminController@integrations','services'=>['SettingsService','PaymentService','CalendarService']],
            ['method'=>'GET','path'=>'/admin/backups','name'=>'admin.backups','page'=>'admin/list','controller'=>'AdminController@backups','services'=>['JsonStoreService']],
            ['method'=>'GET','path'=>'/admin/audit-log','name'=>'admin.audit','page'=>'admin/list','controller'=>'AdminController@audit','services'=>['AuditLogService']],
            ['method'=>'GET','path'=>'/admin/developer/project-map','name'=>'admin.project-map','page'=>'admin/project-map','controller'=>'AdminController@projectMap','services'=>['ProjectMapService']],
        ];
        return [
            'routes'=>$routes,
            'services'=>['AuthService','ProductService','CategoryService','CouponService','CartService','OrderService','PaymentService','ShippingService','AstrologerService','AvailabilityService','AppointmentService','TempleService','SettingsService','CalendarService','ProjectMapService','JsonStoreService','AuditLogService'],
            'integrations'=>['GoogleOAuthClient','GoogleCalendarClient','RazorpayClient'],
            'collections'=>['users','products','categories','coupons','orders','astrologers','appointments','temples','settings','audit_events'],
        ];
    }
    public static function validate(array $map): array {
        $missingRouteMappings = array_values(array_filter($map['routes'], fn($r) => empty($r['controller']) || empty($r['page'])));
        $used = array_unique(array_merge(...array_map(fn($r) => $r['services'], $map['routes'])));
        $missingServices = array_values(array_diff($used, $map['services']));
        return ['missing_route_mappings'=>$missingRouteMappings,'missing_services'=>$missingServices,'missing_collections'=>array_values(array_diff(['users','products','categories','coupons','orders','astrologers','appointments','temples','settings','audit_events'], $map['collections']))];
    }
}
