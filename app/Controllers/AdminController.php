<?php
namespace App\Controllers;
use App\Services\ProjectMapService;
final class AdminController extends BaseController {
    public function dashboard(): void{$this->render('admin/dashboard');}
    public function products(): void{$this->list('Products');}
    public function categories(): void{$this->list('Categories');}
    public function coupons(): void{$this->list('Coupons');}
    public function orders(): void{$this->list('Orders');}
    public function order(string $id): void{$this->render('admin/detail',['title'=>'Order '.$id]);}
    public function shipping(): void{$this->render('admin/settings',['title'=>'Shipping']);}
    public function astrologers(): void{$this->list('Astrologers');}
    public function appointments(): void{$this->list('Appointments');}
    public function temples(): void{$this->list('Temples');}
    public function settings(): void{$this->render('admin/settings',['title'=>'Site Settings']);}
    public function integrations(): void{$this->render('admin/settings',['title'=>'Integrations']);}
    public function backups(): void{$this->list('Backups');}
    public function audit(): void{$this->list('Audit Log');}
    public function projectMap(): void{$this->render('admin/project-map',['map'=>ProjectMapService::registry(),'validation'=>ProjectMapService::validate(ProjectMapService::registry())]);}
    private function list(string $title): void{$this->render('admin/list',['title'=>$title]);}
}
