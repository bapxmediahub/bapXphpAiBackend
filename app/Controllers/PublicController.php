<?php
namespace App\Controllers;
use App\Services\{ProductService,AstrologerService,TempleService};
final class PublicController extends BaseController {
    public function home(): void { $this->render('public/home', ['products'=>(new ProductService())->all(),'astrologers'=>(new AstrologerService())->all(),'temples'=>(new TempleService())->all()]); }
    public function about(): void { $this->render('public/about'); }
    public function spiritual(): void { $this->render('public/spiritual'); }
    public function astrologers(): void { $this->render('public/astrologers', ['items'=>(new AstrologerService())->all()]); }
    public function astrologer(string $slug): void { $this->render('public/astrologer', ['slug'=>$slug]); }
    public function temples(): void { $this->render('public/temples', ['items'=>(new TempleService())->all()]); }
    public function temple(string $slug): void { $this->render('public/temple', ['slug'=>$slug]); }
    public function shop(): void { $this->render('public/shop', ['items'=>(new ProductService())->all()]); }
    public function product(string $slug): void { $this->render('public/product', ['slug'=>$slug]); }
    public function cart(): void { $this->render('public/cart'); }
    public function checkout(): void { $this->render('public/checkout'); }
    public function contact(): void { $this->render('public/contact'); }
    public function login(): void { $this->render('public/login'); }
}
