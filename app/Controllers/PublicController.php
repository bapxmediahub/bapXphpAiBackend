<?php
namespace App\Controllers;
use App\Services\{ProductService,AstrologerService,TempleService,CategoryService,AvailabilityService,AppointmentService,CartService,SecretService,SettingsService};
final class PublicController extends BaseController {
    public function home(): void { $this->render('public/home', ['products'=>(new ProductService())->all(),'categories'=>(new CategoryService())->all(),'astrologers'=>(new AstrologerService())->all(),'temples'=>(new TempleService())->all()]); }
    public function about(): void { $this->render('public/about'); }
    public function spiritual(): void { $this->render('public/spiritual'); }
    public function astrologers(): void { $this->render('public/astrologers', ['items'=>(new AstrologerService())->all()]); }
    public function astrologer(string $slug): void { $astrologer=(new AstrologerService())->findBySlug($slug); $date=$_GET['date']??date('Y-m-d'); $slots=$astrologer?(new AvailabilityService())->slotsForDate($astrologer,$date,(new AppointmentService())->all()):[]; $this->render('public/astrologer', compact('slug','astrologer','date','slots')); }
    public function temples(): void { $this->render('public/temples', ['items'=>(new TempleService())->all()]); }
    public function temple(string $slug): void { $this->render('public/temple', ['slug'=>$slug]); }
    public function shop(): void { $category=$_GET['category']??''; $categories=(new CategoryService())->all(); $items=(new ProductService())->all(); if($category){$items=array_values(array_filter($items, fn($item)=>($item['category']??'')===$category));} $this->render('public/shop', compact('items','categories','category')); }
    public function product(string $slug): void { $categories=(new CategoryService())->all(); $categoryNames=array_column($categories,'name','slug'); $this->render('public/product', ['product'=>(new ProductService())->findBySlug($slug),'categoryNames'=>$categoryNames]); }
    public function cart(): void { $products=(new ProductService())->all(); $items=[]; foreach((new CartService())->items() as $line){foreach($products as $p){if(($p['slug']??'')===($line['slug']??''))$items[]=['product'=>$p,'qty'=>$line['qty']];}} $this->render('public/cart', compact('items')); }
    public function checkout(): void { $products=(new ProductService())->all(); $items=[]; $total=0; foreach((new CartService())->items() as $line){foreach($products as $p){if(($p['slug']??'')===($line['slug']??'')){ $price=(int)($p['offer_price']?:$p['price']?:0); $items[]=['product'=>$p,'qty'=>$line['qty'],'line_total'=>$price*$line['qty']]; $total += $price*$line['qty']; }}} $secrets=(new SecretService())->all(); $this->render('public/checkout', compact('items','total','secrets')); }
    public function contact(): void { $this->render('public/contact', ['settings'=>(new SettingsService())->public()]); }
    public function login(): void { $this->render('public/login'); }
}
