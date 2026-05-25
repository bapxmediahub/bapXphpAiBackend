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
            ['method'=>'GET','path'=>'/auth/google','name'=>'auth.google','page'=>'public/login','controller'=>'AuthController@redirect','services'=>['SecretService']],
            ['method'=>'GET','path'=>'/auth/google/callback','name'=>'auth.google.callback','page'=>'public/login','controller'=>'AuthController@callback','services'=>['SecretService','JsonStoreService']],
            ['method'=>'GET','path'=>'/logout','name'=>'logout','page'=>'public/login','controller'=>'AuthController@logout','services'=>['AuthService']],
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
            ['method'=>'GET','path'=>'/admin/integrations','name'=>'admin.integrations','page'=>'admin/integrations','controller'=>'AdminController@integrations','services'=>['SettingsService','PaymentService','CalendarService']],
            ['method'=>'GET','path'=>'/admin/backups','name'=>'admin.backups','page'=>'admin/list','controller'=>'AdminController@backups','services'=>['JsonStoreService']],
            ['method'=>'GET','path'=>'/admin/audit-log','name'=>'admin.audit','page'=>'admin/list','controller'=>'AdminController@audit','services'=>['AuditLogService']],
            ['method'=>'GET','path'=>'/admin/developer/project-map','name'=>'admin.project-map','page'=>'admin/project-map','controller'=>'AdminController@projectMap','services'=>['ProjectMapService']],
            ['method'=>'POST','path'=>'/admin/products/save','name'=>'admin.products.save','page'=>'admin/resource','controller'=>'AdminController@saveProduct','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/products/delete','name'=>'admin.products.delete','page'=>'admin/resource','controller'=>'AdminController@deleteProduct','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/categories/save','name'=>'admin.categories.save','page'=>'admin/resource','controller'=>'AdminController@saveCategory','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/coupons/save','name'=>'admin.coupons.save','page'=>'admin/resource','controller'=>'AdminController@saveCoupon','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/coupons/delete','name'=>'admin.coupons.delete','page'=>'admin/resource','controller'=>'AdminController@deleteCoupon','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/astrologers/save','name'=>'admin.astrologers.save','page'=>'admin/resource','controller'=>'AdminController@saveAstrologer','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/astrologers/delete','name'=>'admin.astrologers.delete','page'=>'admin/resource','controller'=>'AdminController@deleteAstrologer','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/temples/save','name'=>'admin.temples.save','page'=>'admin/resource','controller'=>'AdminController@saveTemple','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/temples/delete','name'=>'admin.temples.delete','page'=>'admin/resource','controller'=>'AdminController@deleteTemple','services'=>['ResourceService']],
            ['method'=>'POST','path'=>'/admin/integrations/save','name'=>'admin.integrations.save','page'=>'admin/integrations','controller'=>'AdminController@saveIntegrations','services'=>['SecretService']],
            ['method'=>'POST','path'=>'/cart/add','name'=>'cart.add','page'=>'public/cart','controller'=>'CommerceController@addToCart','services'=>['CartService']],
            ['method'=>'POST','path'=>'/checkout/create-order','name'=>'checkout.create-order','page'=>'public/checkout','controller'=>'CommerceController@createOrder','services'=>['SecretService','PaymentService']],
            ['method'=>'POST','path'=>'/payment/verify','name'=>'payment.verify','page'=>'public/checkout','controller'=>'CommerceController@verifyPayment','services'=>['SecretService','PaymentService']],
            ['method'=>'POST','path'=>'/appointments/book','name'=>'appointments.book','page'=>'public/astrologer','controller'=>'BookingController@book','services'=>['ResourceService','AvailabilityService','CalendarService']],
        ];
        return [
            'routes'=>$routes,
            'services'=>['AuthService','ProductService','CategoryService','CouponService','CartService','OrderService','PaymentService','ShippingService','AstrologerService','AvailabilityService','AppointmentService','TempleService','SettingsService','CalendarService','ProjectMapService','JsonStoreService','AuditLogService','ResourceService','SecretService'],
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
