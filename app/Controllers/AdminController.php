<?php
namespace App\Controllers;
use App\Services\{ResourceService,SecretService};
final class AdminController extends BaseController {
    protected string $layout = 'admin';
    public function dashboard(): void{
        $productCount = count((new ResourceService('products'))->all());
        $orderCount = count((new ResourceService('orders'))->all());
        $bookingCount = count((new ResourceService('appointments'))->all());
        $this->render('admin/dashboard', ['pageTitle' => 'Dashboard', 'productCount' => $productCount, 'orderCount' => $orderCount, 'bookingCount' => $bookingCount]);
    }
    public function products(): void{$this->resource('Products','products',['name','description','category','image_url','price','offer_price','stock_status']);}
    public function saveProduct(): void{$this->save('products');}
    public function deleteProduct(): void{$this->delete('products');}
    public function categories(): void{$this->resource('Categories','categories',['name','description']);}
    public function saveCategory(): void{$this->save('categories');}
    public function coupons(): void{$this->resource('Coupons','coupons',['code','discount_type','discount_value','active']);}
    public function saveCoupon(): void{$this->save('coupons');}
    public function deleteCoupon(): void{$this->delete('coupons');}
    public function orders(): void{$this->list('Orders');}
    public function order(string $id): void{$this->render('admin/detail',['pageTitle' => 'Order '.$id, 'title' => 'Order '.$id]);}
    public function shipping(): void{$this->render('admin/settings',['pageTitle' => 'Shipping', 'title' => 'Shipping']);}
    public function astrologers(): void{$this->resource('Astrologers','astrologers',['name','description','email','price','modes','working_days','start_time','end_time','slot_minutes','languages','experience_years','speciality','photo_url']);}
    public function saveAstrologer(): void{$this->save('astrologers');}
    public function deleteAstrologer(): void{$this->delete('astrologers');}
    public function appointments(): void{$this->list('Appointments');}
    public function temples(): void{$this->resource('Temples','temples',['name','description','address','map_url']);}
    public function saveTemple(): void{$this->save('temples');}
    public function deleteTemple(): void{$this->delete('temples');}
    public function settings(): void{$this->render('admin/settings',['pageTitle' => 'Settings', 'title' => 'Site Settings']);}
    public function integrations(): void{$this->render('admin/integrations',['pageTitle' => 'Integrations', 'secrets'=>(new SecretService())->all()]);}
    public function saveIntegrations(): void{(new SecretService())->save($_POST); $this->flash('Integration settings saved.'); $this->redirect('/admin/integrations');}
    private function list(string $title): void{$this->render('admin/list',['pageTitle' => $title, 'title' => $title]);}
    private function resource(string $title,string $collection,array $fields): void{$this->render('admin/resource',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'fields' => $fields, 'items'=>(new ResourceService($collection))->all()]);}
    private function save(string $collection): void{$data=array_filter($_POST,fn($v)=>$v!==''); if(isset($data['working_days']))$data['working_days']=array_map('trim',explode(',',$data['working_days'])); if(isset($data['modes']))$data['modes']=array_map('trim',explode(',',$data['modes'])); if(isset($data['languages']))$data['languages']=array_map('trim',explode(',',$data['languages'])); (new ResourceService($collection))->save($data); $this->flash('Saved.'); $this->redirect('/admin/'.$collection);}
    private function delete(string $collection): void{(new ResourceService($collection))->delete($_POST['id']??''); $this->flash('Deleted.'); $this->redirect('/admin/'.$collection);}
}
