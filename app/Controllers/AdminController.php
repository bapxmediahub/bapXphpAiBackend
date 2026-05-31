<?php
namespace App\Controllers;
use App\Services\{AuditLogService,AuthService,EnvService,OrderService,ResourceService,SecretService,SettingsService};
final class AdminController extends BaseController {
    protected string $layout = 'admin';
    public function __construct() {
        (new AuthService())->requireAdmin();
    }

    public function dashboard(): void{
        $productCount = count((new ResourceService('products'))->all());
        $orderCount = count((new ResourceService('orders'))->all());
        $bookingCount = count((new ResourceService('appointments'))->all());
        $this->render('admin/dashboard', ['pageTitle' => 'Dashboard', 'productCount' => $productCount, 'orderCount' => $orderCount, 'bookingCount' => $bookingCount]);
    }
    public function products(): void{$this->resource('Products','products',['slug','name','description','category','image_url','image_urls','price','offer_price','stock_status']);}
    public function saveProduct(): void{$this->saveProductRecord();}
    public function deleteProduct(): void{$this->delete('products');}
    public function categories(): void{$this->resource('Categories','categories',['name','description']);}
    public function saveCategory(): void{$this->save('categories');}
    public function coupons(): void{$this->resource('Coupons','coupons',['code','discount_type','discount_value','active']);}
    public function saveCoupon(): void{$this->save('coupons');}
    public function deleteCoupon(): void{$this->delete('coupons');}
    public function orders(): void{$this->list('Orders','orders');}
    public function order(string $id): void{
        $orders = (new ResourceService('orders'))->all();
        $order = null;
        foreach ($orders as $item) {
            if (($item['id'] ?? '') === $id) { $order = $item; break; }
        }
        $this->render('admin/detail',['pageTitle' => 'Order '.$id, 'title' => 'Order '.$id, 'order' => $order]);
    }
    public function saveOrderStatus(string $id): void{try{(new OrderService())->updateStatus($id, $_POST['status'] ?? 'confirmed'); $this->flash('Order status updated.');}catch(\Throwable){$this->flash('Unable to update order status.');} $this->redirect('/admin/orders/'.$id);}
    public function shipping(): void{$this->render('admin/settings',['pageTitle' => 'Shipping', 'title' => 'Shipping']);}
    public function astrologers(): void{$this->resource('Astrologers','astrologers',['slug','name','description','email','message_credit_cost','call_credit_per_second','credit_to_rupee_rate','text_session_prm','call_session_prm','payout_percentage','modes','working_days','start_time','end_time','slot_minutes','languages','experience_years','speciality','photo_url']);}
    public function saveAstrologer(): void{$this->save('astrologers');}
    public function deleteAstrologer(): void{$this->delete('astrologers');}
    public function appointments(): void{$this->list('Sessions','appointments');}
    public function temples(): void{$this->resource('Temples','temples',['name','description','address','map_url']);}
    public function saveTemple(): void{$this->save('temples');}
    public function deleteTemple(): void{$this->delete('temples');}
    public function settings(): void{$this->render('admin/settings',['pageTitle' => 'Settings', 'title' => 'Site Settings', 'settings'=>(new SettingsService())->public(), 'adminCredentials'=>(new EnvService())->adminCredentials()]);}
    public function saveSettings(): void{(new SettingsService())->savePublic(['shipping_mode'=>$_POST['shipping_mode'] ?? 'free','flat_rate'=>max(0,(float)($_POST['flat_rate'] ?? 0)),'currency'=>$_POST['currency'] ?? 'INR','timezone'=>$_POST['timezone'] ?? 'Asia/Kolkata']); $this->flash('Settings saved.'); $this->redirect('/admin/settings');}
    public function saveAdminCredentials(): void{(new EnvService())->saveAdminCredentials($_POST); $this->flash('Admin credentials saved to .env.'); $this->redirect('/admin/settings');}
    public function integrations(): void{$this->render('admin/integrations',['pageTitle' => 'Integrations', 'secrets'=>(new SecretService())->all()]);}
    public function saveIntegrations(): void{(new SecretService())->save($_POST); $this->flash('Integration settings saved.'); $this->redirect('/admin/integrations');}
    public function backups(): void{$this->list('Backups','settings');}
    public function audit(): void{$this->list('Audit Log','audit_events');}
    public function contactSubmissions(): void{$this->resource('Contact Submissions','contact_submissions',['name','email','phone','subject','message','status']);}
    public function supportTickets(): void{$this->list('Support Tickets','support_tickets');}
    public function projectMap(): void{$this->render('admin/project-map',['pageTitle' => 'Project Map', 'map'=>\App\Services\ProjectMapService::registry(),'validation'=>\App\Services\ProjectMapService::validate(\App\Services\ProjectMapService::registry())]);}
    private function list(string $title, ?string $collection = null): void{$this->render('admin/list',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'items'=>$collection ? (new ResourceService($collection))->all() : []]);}
    private function resource(string $title,string $collection,array $fields): void{$this->render('admin/resource',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'fields' => $fields, 'items'=>(new ResourceService($collection))->all()]);}
    private function save(string $collection): void{
        $data=$this->cleanPost();
        if(isset($data['working_days']))$data['working_days']=$this->splitList($data['working_days']);
        if(isset($data['modes']))$data['modes']=$this->splitList($data['modes']);
        if(isset($data['languages']))$data['languages']=$this->splitList($data['languages']);
        $record=(new ResourceService($collection))->save($data);
        (new AuditLogService())->record('save',$collection,(string)($record['id'] ?? ''),['fields'=>array_keys($data)]);
        $this->flash('Saved.');
        $this->redirect('/admin/'.$collection);
    }
    private function saveProductRecord(): void{
        $data=$this->cleanPost();
        $data=$this->mergeExistingRecord('products', $data);
        $images=$this->splitList((string)($data['image_urls'] ?? ''));
        if (!empty($data['image_url'])) array_unshift($images, (string)$data['image_url']);
        $uploaded=$this->uploadProductImages((string)($data['slug'] ?? $data['name'] ?? 'product'));
        $images=array_values(array_unique(array_filter(array_merge($images, $uploaded))));
        if (!empty($images)) {
            $data['image_url']=$images[0];
            $data['image_urls']=$images;
        }
        $record=(new ResourceService('products'))->save($data);
        (new AuditLogService())->record('save','products',(string)($record['id'] ?? ''),['fields'=>array_keys($data),'uploaded_images'=>count($uploaded)]);
        $this->flash('Product saved.');
        $this->redirect('/admin/products');
    }
    private function delete(string $collection): void{
        $id=(string)($_POST['id']??'');
        (new ResourceService($collection))->delete($id);
        (new AuditLogService())->record('delete',$collection,$id);
        $this->flash('Deleted.');
        $this->redirect('/admin/'.$collection);
    }
    private function cleanPost(): array {
        return array_filter($_POST, fn($v) => $v !== '' && $v !== null);
    }
    private function mergeExistingRecord(string $collection, array $data): array {
        $id=(string)($data['id'] ?? '');
        if ($id === '') return $data;
        foreach ((new ResourceService($collection))->all() as $item) {
            if ((string)($item['id'] ?? '') === $id) return array_merge($item, $data);
        }
        return $data;
    }
    private function splitList(string $value): array {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])));
    }
    private function uploadProductImages(string $name): array {
        $files=$_FILES['product_images'] ?? null;
        if (!$files || empty($files['name']) || !is_array($files['name'])) return [];
        $dir=app_path('assets/images/products');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $name), '-')) ?: 'product';
        $allowed=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
        $uploaded=[];
        foreach ($files['name'] as $i=>$original) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $ext=strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
            if (!isset($allowed[$ext])) continue;
            $tmp=(string)($files['tmp_name'][$i] ?? '');
            $mime=is_file($tmp) ? (mime_content_type($tmp) ?: '') : '';
            if ($mime !== '' && $mime !== $allowed[$ext]) continue;
            $filename=$slug.'-'.bin2hex(random_bytes(4)).'.'.$ext;
            if (move_uploaded_file($tmp, $dir.'/'.$filename)) {
                $uploaded[]='/assets/images/products/'.$filename;
            }
        }
        return $uploaded;
    }
}
