<?php
namespace App\Controllers;
use App\Services\{AstrologerAccountService,AuditLogService,AuthService,ConsultationService,EnvService,JsonStoreService,MediaService,OrderService,ResourceService,SchemaService,SecretService,SettingsService,StoragePermissionService};
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
    public function products(): void{$this->resource('Products','products',$this->schemaFields('products',['slug','name','description','category','image_url','image_urls','price','offer_price','stock_status']));}
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
    public function astrologers(): void{$this->resource('Astrologers','astrologers',$this->schemaFields('astrologers',['slug','name','description','email','message_credit_cost','call_credit_per_second','credit_to_rupee_rate','text_session_prm','call_session_prm','payout_percentage','modes','working_days','start_time','end_time','slot_minutes','languages','experience_years','speciality','photo_url']));}
    public function saveAstrologer(): void{$this->save('astrologers');}
    public function deleteAstrologer(): void{
        $id=(string)($_POST['id']??''); $slug=''; foreach((new ResourceService('astrologers'))->all() as $row) if(($row['id']??'')===$id){$slug=(string)($row['slug']??'');break;}
        (new ResourceService('astrologers'))->delete($id); if($slug!=='')(new AstrologerAccountService())->deleteForSlug($slug); (new AuditLogService())->record('delete','astrologers',$id); $this->flash('Deleted.'); $this->redirect('/admin/astrologers');
    }
    public function appointments(): void{$this->list('Sessions','appointments');}
    public function astrologerCredentials(): void{
        $astrologers=(new ResourceService('astrologers'))->all(); $users=(new JsonStoreService())->read('users'); $bySlug=[];
        foreach($users as $user) if(($user['role']??'')==='astrologer') $bySlug[$user['astrologer_slug']??'']=$user;
        $rows=[]; foreach($astrologers as $astrologer){$user=$bySlug[$astrologer['slug']??'']??[];$rows[]=['name'=>$astrologer['name']??'','username'=>$user['username']??$astrologer['username']??'','temporary_password'=>!empty($user['must_change_password'])?AstrologerAccountService::INITIAL_PASSWORD:'','status'=>!empty($user['must_change_password'])?'Password change required':'Password changed'];}
        $this->render('admin/astrologer-credentials',['pageTitle'=>'Astrologer Credentials','rows'=>$rows]);
    }
    public function consultationAnalytics(): void{$this->render('admin/consultation-analytics',['pageTitle'=>'Consultation Analytics','metrics'=>(new ConsultationService())->analytics()]);}
    public function temples(): void{$this->resource('Temples','temples',$this->schemaFields('temples',['name','description','image_url','address','map_url']));}
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
    public function media(): void{$this->render('admin/media',['pageTitle'=>'Media Library','items'=>(new MediaService())->all()]);}
    public function uploadMedia(): void{$uploaded=(new MediaService())->upload($_FILES['media_files'] ?? [], $_POST['context'] ?? 'shared'); (new AuditLogService())->record('upload','media','',['count'=>count($uploaded),'context'=>$_POST['context'] ?? 'shared']); $this->flash(count($uploaded).' media file'.(count($uploaded) === 1 ? '' : 's').' uploaded.'); $this->redirect('/admin/media');}
    public function environment(): void{$this->render('admin/environment',['pageTitle'=>'Environment','envRaw'=>(new EnvService())->raw(),'permissions'=>(new StoragePermissionService())->status()]);}
    public function saveEnvironment(): void{(new EnvService())->saveRaw((string)($_POST['env_raw'] ?? '')); (new AuditLogService())->record('save','environment','.env',['keys'=>array_keys(EnvService::readFile(app_path('.env')))]); $this->flash('Environment saved.'); $this->redirect('/admin/environment');}
    public function fixPermissions(): void{(new StoragePermissionService())->fix(); (new AuditLogService())->record('fix','permissions','storage'); $this->flash('Storage permissions checked and updated where PHP is allowed.'); $this->redirect('/admin/environment');}
    public function projectMap(): void{$this->render('admin/project-map',['pageTitle' => 'Project Map', 'map'=>\App\Services\ProjectMapService::registry(),'validation'=>\App\Services\ProjectMapService::validate(\App\Services\ProjectMapService::registry())]);}
    private function list(string $title, ?string $collection = null): void{$this->render('admin/list',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'items'=>$collection ? (new ResourceService($collection))->all() : []]);}
    private function resource(string $title,string $collection,array $fields): void{$this->render('admin/resource',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'fields' => $fields, 'items'=>(new ResourceService($collection))->all(), 'mediaFiles'=>$this->mediaFor($collection)]);}
    private function save(string $collection): void{
        $data=$this->cleanPost();
        if(isset($data['working_days']))$data['working_days']=$this->splitList($data['working_days']);
        if(isset($data['modes']))$data['modes']=$this->splitList($data['modes']);
        if(isset($data['languages']))$data['languages']=$this->splitList($data['languages']);
        $uploaded=$this->uploadedMedia($collection);
        if ($collection === 'astrologers' && $uploaded && empty($data['photo_url'])) $data['photo_url']=$uploaded[0]['path'];
        if ($collection === 'temples' && $uploaded && empty($data['image_url'])) $data['image_url']=$uploaded[0]['path'];
        $record=(new ResourceService($collection))->save($data);
        if($collection==='astrologers')(new AstrologerAccountService())->sync($record);
        (new AuditLogService())->record('save',$collection,(string)($record['id'] ?? ''),['fields'=>array_keys($data),'uploaded_media'=>count($uploaded)]);
        $this->flash($collection==='astrologers'?'Astrologer profile and login saved.':'Saved.');
        $this->redirect('/admin/'.$collection);
    }
    private function saveProductRecord(): void{
        $data=$this->cleanPost();
        $data=$this->mergeExistingRecord('products', $data);
        $images=$this->splitList((string)($data['image_urls'] ?? ''));
        if (!empty($data['image_url'])) array_unshift($images, (string)$data['image_url']);
        $uploaded=$this->uploadedMedia('products');
        $uploadedPaths=array_column($uploaded, 'path');
        $images=array_values(array_unique(array_filter(array_merge($images, $uploadedPaths))));
        if (!empty($images)) {
            $data['image_url']=$images[0];
            $data['image_urls']=$images;
        }
        $record=(new ResourceService('products'))->save($data);
        (new AuditLogService())->record('save','products',(string)($record['id'] ?? ''),['fields'=>array_keys($data),'uploaded_media'=>count($uploaded)]);
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
    private function uploadedMedia(string $collection): array { return (new MediaService())->upload($_FILES['media_files'] ?? [], $this->mediaContext($collection)); }
    private function mediaFor(string $collection): array { return in_array($collection, ['products','temples','astrologers'], true) ? (new MediaService())->all($this->mediaContext($collection)) : []; }
    private function mediaContext(string $collection): string { return match($collection){'products'=>'products','temples'=>'temples','astrologers'=>'astrologers',default=>'shared'}; }
    private function schemaFields(string $collection, array $fallback): array { return (new SchemaService())->adminFields($collection, $fallback); }
}
