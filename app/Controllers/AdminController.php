<?php
namespace App\Controllers;
use App\Services\{AuditLogService,AuthService,BlogDraftService,ConsultationService,EnvService,MailStorageService,MarkdownRenderer,MediaService,OrderService,ResourceService,SchemaService,SecretService,SettingsService,StoragePermissionService};
final class AdminController extends BaseController {
    protected string $layout = 'admin';
    public function __construct() {
        (new AuthService())->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->validateCsrf();
        $this->seoKey = 'admin';
    }

    public function dashboard(): void{
        $productCount = count((new ResourceService('products'))->all());
        $orderCount = count((new ResourceService('orders'))->all());
        $bookingCount = count((new ResourceService('appointments'))->all());
        $this->render('admin/dashboard', ['pageTitle' => 'Dashboard', 'productCount' => $productCount, 'orderCount' => $orderCount, 'bookingCount' => $bookingCount]);
    }
    public function products(): void{
        $this->render('admin/product-form',['pageTitle'=>'Products','title'=>'Products','collection'=>'products','items'=>(new ResourceService('products'))->all(),'categories'=>(new ResourceService('categories'))->all(),'mediaFiles'=>$this->mediaFor('products')]);
    }
    public function saveProduct(): void{$this->saveProductRecord();}
    public function deleteProduct(): void{$this->delete('products');}
    public function categories(): void{$this->resource('Categories','categories',['name','description']);}
    public function saveCategory(): void{$this->save('categories');}
    public function deleteCategory(): void{$this->delete('categories');}
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
    public function saveOrderStatus(string $id): void{try{(new OrderService())->updateStatus($id, $_POST['status'] ?? 'confirmed'); (new AuditLogService())->record('save','order.status',$id,['status'=>$_POST['status'] ?? 'confirmed']); $this->flash('Order status updated.','success');}catch(\Throwable){$this->flash('Unable to update order status.','error');} $this->redirect('/admin/orders/'.$id);}
    public function shipping(): void{$this->render('admin/settings',['pageTitle' => 'Shipping', 'title' => 'Shipping']);}
    public function astrologers(): void{
        $this->render('admin/astrologer-form',['pageTitle'=>'Astrologers','title'=>'Astrologers','collection'=>'astrologers','items'=>(new ResourceService('astrologers'))->all(),'mediaFiles'=>$this->mediaFor('astrologers')]);
    }
    public function saveAstrologer(): void{$this->save('astrologers');}
    public function deleteAstrologer(): void{
        $id=(string)($_POST['id']??'');
        (new ResourceService('astrologers'))->delete($id); (new AuditLogService())->record('delete','astrologers',$id); $this->flash('Deleted.','info'); $this->redirect('/admin/astrologers');
    }
    public function appointments(): void{$this->list('Sessions','appointments');}
    public function consultationAnalytics(): void{$this->render('admin/consultation-analytics',['pageTitle'=>'Consultation Analytics','metrics'=>(new ConsultationService())->analytics()]);}
    public function temples(): void{$this->resource('Temples','temples',$this->schemaFields('temples',['name','description','image_url','address','map_url']));}
    public function saveTemple(): void{$this->save('temples');}
    public function deleteTemple(): void{$this->delete('temples');}
    public function settings(): void{$this->render('admin/settings',['pageTitle' => 'Settings', 'title' => 'Site Settings', 'settings'=>(new SettingsService())->public(), 'adminCredentials'=>(new EnvService())->adminCredentials()]);}
    public function saveSettings(): void{(new SettingsService())->savePublic(['shipping_mode'=>$_POST['shipping_mode'] ?? 'free','flat_rate'=>max(0,(float)($_POST['flat_rate'] ?? 0)),'currency'=>$_POST['currency'] ?? 'INR','timezone'=>$_POST['timezone'] ?? 'Asia/Kolkata','gstin'=>$_POST['gstin'] ?? '','gst_legal_name'=>$_POST['gst_legal_name'] ?? '','gst_trade_name'=>$_POST['gst_trade_name'] ?? '','gst_address'=>$_POST['gst_address'] ?? '','gst_state'=>$_POST['gst_state'] ?? '','gst_state_code'=>$_POST['gst_state_code'] ?? '']); (new AuditLogService())->record('save','settings','public',['fields'=>['shipping_mode','flat_rate','currency','timezone','gstin']]); $this->flash('Settings saved.','success'); $this->redirect('/admin/settings');}
    public function saveAdminCredentials(): void{(new EnvService())->saveAdminCredentials($_POST); (new AuditLogService())->record('save','admin-credentials','env'); $this->flash('Admin credentials saved.','success'); $this->redirect('/admin/settings');}
    public function integrations(): void{$this->render('admin/integrations',['pageTitle' => 'Integrations', 'secrets'=>(new SecretService())->all()]);}
    public function saveIntegrations(): void{(new SecretService())->save($_POST); (new AuditLogService())->record('save','integrations','secrets'); $this->flash('Integration settings saved.','success'); $this->redirect('/admin/integrations');}
    public function agent(): void{
        $secrets = new SecretService();
        $modelConfig = $secrets->getModelConfig();
        $this->render('admin/agent',['pageTitle'=>'AI Agent','modelConfig'=>$modelConfig]);
    }
    public function agentAsk(): void{
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {$this->jsonResponse(['error'=>'Message is required'],400); return;}
        try {
            $secrets = new SecretService();
            $db = new \App\Services\DatabaseService();
            $modelConfig = $secrets->getModelConfig();
            $userCount = count($db->read('users'));
            $orderCount = count($db->read('orders'));
            $productCount = count($db->read('products'));
            $astrologerCount = count($db->read('astrologers'));
            $appointmentCount = count($db->read('appointments'));
            $ticketCount = count($db->read('support_tickets'));
            $revenue = array_sum(array_column($db->read('orders'), 'total'));
            $attachments = '';
            $tempDir = app_path('.agents/temp');
            if (is_dir($tempDir)) {
                $files = array_diff(scandir($tempDir), ['.','..']);
                if (!empty($files)) $attachments = "\n\nAttachments available in .agents/temp/: " . implode(', ', $files);
            }
            $context = "Site data:\n- Users: {$userCount}\n- Orders: {$orderCount}\n- Products: {$productCount}\n- Astrologers: {$astrologerCount}\n- Appointments: {$appointmentCount}\n- Support tickets: {$ticketCount}\n- Revenue (sum of totals): ₹" . number_format($revenue, 2) . $attachments;
            if (!empty($modelConfig['apiKey'])) {
                $answer = $this->callAiApi($modelConfig, $message, $context);
            } else {
                $answer = "AI model not configured. Go to Admin → Integrations and set api_endpoint, agent_api_key, and agent_model.";
            }
            $this->jsonResponse(['answer'=>$answer]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error'=>'Agent error: '.$e->getMessage()],500);
        }
    }
    private function callAiApi(array $config, string $message, string $context): string {
        $endpoint = rtrim($config['endpoint'] ?? 'https://api.openai.com/v1', '/');
        $model = $config['model'] ?? 'gemma-4-31b-it';
        $key = $config['apiKey'] ?? '';
        $provider = $config['provider'] ?? 'openai';
        $prompt = "You are the AI assistant for the site. Answer concisely in Markdown.\n\n{$context}\n\nQuestion: {$message}";
        if ($provider === 'google') {
            $url = $endpoint . '/' . rawurlencode($model) . ':generateContent';
            $payload = json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>1024]]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'x-goog-api-key: '.$key], CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>30, CURLOPT_CONNECTTIMEOUT=>10]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status !== 200 || $body === false) return "API error (HTTP {$status}). Check model config.";
            $result = json_decode($body, true);
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No response.';
        }
        $url = $endpoint . '/chat/completions';
        $payload = json_encode(['model'=>$model,'messages'=>[['role'=>'system','content'=>$context],['role'=>'user','content'=>$message]],'max_tokens'=>1024]);
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
        if ($provider === 'anthropic') {
            $headers[] = 'x-api-key: ' . $key;
            $headers[] = 'anthropic-version: 2023-06-01';
        } else {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>30, CURLOPT_CONNECTTIMEOUT=>10]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200 || $body === false) return "API error (HTTP {$status}). Check endpoint/key/model in Admin → Integrations.";
        $result = json_decode($body, true);
        return $result['choices'][0]['message']['content'] ?? 'No response.';
    }
    public function appearance(): void{
        $s=(new SettingsService())->public();
        $d = ['#3A0003','#D1B368','#FAF7F0','#222222','#3A0003'];
        $this->render('admin/appearance',['pageTitle'=>'Logo & Favicon','logo_url'=>$s['logo_url']??'','favicon_url'=>$s['favicon_url']??'','palette_primary'=>$s['palette_primary']??$d[0],'palette_secondary'=>$s['palette_secondary']??$d[1],'palette_canvas'=>$s['palette_canvas']??$d[2],'palette_text'=>$s['palette_text']??$d[3],'palette_link'=>$s['palette_link']??$d[4]]);
    }
    public function saveAppearance(): void{
        $s=(new SettingsService())->public(); $d=app_path('assets/images/brand'); if(!is_dir($d)) mkdir($d,0775,true); $e='';
        if(!empty($_POST['logo_remove'])){$s['logo_url']='';}
        if(!empty($_FILES['logo_file']['name'])&&$_FILES['logo_file']['error']===UPLOAD_ERR_OK){
            $i=getimagesize($_FILES['logo_file']['tmp_name']);$w=$i[0]??0;$h=$i[1]??0;$sz=$_FILES['logo_file']['size'];
            if($w>512||$h>512)$e='Logo exceeds 512×512 px.';
            elseif($sz>102400)$e='Logo exceeds 100 KB.';
            else{$x=strtolower(pathinfo($_FILES['logo_file']['name'],PATHINFO_EXTENSION));move_uploaded_file($_FILES['logo_file']['tmp_name'],$d.'/logo.'.$x);$s['logo_url']='/assets/images/brand/logo.'.$x;}
        }
        if(!empty($_POST['favicon_remove'])){$s['favicon_url']='';}
        if(!empty($_FILES['favicon_file']['name'])&&$_FILES['favicon_file']['error']===UPLOAD_ERR_OK){
            $i=getimagesize($_FILES['favicon_file']['tmp_name']);$w=$i[0]??0;$h=$i[1]??0;$sz=$_FILES['favicon_file']['size'];
            if($w>64||$h>64)$e='Favicon exceeds 64×64 px.';
            elseif($sz>51200)$e='Favicon exceeds 50 KB.';
            else{$x=strtolower(pathinfo($_FILES['favicon_file']['name'],PATHINFO_EXTENSION));move_uploaded_file($_FILES['favicon_file']['tmp_name'],$d.'/favicon.'.$x);$s['favicon_url']='/assets/images/brand/favicon.'.$x;}
        }
        $paletteBefore = array_intersect_key($s, array_flip(['palette_primary','palette_secondary','palette_canvas','palette_text','palette_link']));
        if (!empty($_POST['reset_palette'])) {
            foreach (['palette_primary','palette_secondary','palette_canvas','palette_text','palette_link'] as $k) unset($s[$k]);
        } else {
            $defaults = ['#3A0003','#D1B368','#FAF7F0','#222222','#3A0003'];
            $keys = ['palette_primary','palette_secondary','palette_canvas','palette_text','palette_link'];
            $vals = [];
            $errs = [];
            foreach ($keys as $i => $k) {
                $v = strtoupper(trim((string)($_POST[$k] ?? '')));
                if ($v === '') continue;
                if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v)) {
                    $errs[] = 'Invalid hex color for ' . str_replace('_', ' ', $k) . '.';
                    continue;
                }
                if (strlen($v) === 4) $v = '#' . $v[1] . $v[1] . $v[2] . $v[2] . $v[3] . $v[3];
                $vals[$k] = $v;
            }
            if (empty($errs) && !empty($vals)) {
                $canvas = $vals['palette_canvas'] ?? $s['palette_canvas'] ?? $defaults[2];
                $text = $vals['palette_text'] ?? $s['palette_text'] ?? $defaults[3];
                $link = $vals['palette_link'] ?? $s['palette_link'] ?? $defaults[4];
                $tc = self::contrast($text, $canvas);
                if ($tc < 4.5) $errs[] = 'Text contrast ratio ' . number_format($tc,2) . ':1 is below 4.5:1 minimum against canvas.';
                else { $lc = self::contrast($link, $canvas); if ($lc < 4.5) $errs[] = 'Link contrast ratio ' . number_format($lc,2) . ':1 is below 4.5:1 minimum against canvas.'; }
            }
            if ($errs) { $e = implode(' ', $errs); }
            else { foreach ($vals as $k => $v) $s[$k] = $v; }
        }
        $paletteAfter = array_intersect_key($s, array_flip(['palette_primary','palette_secondary','palette_canvas','palette_text','palette_link']));
        $changed = array_keys(array_diff_assoc($paletteAfter, $paletteBefore));
        (new SettingsService())->savePublic($s);
        if ($changed) (new AuditLogService())->record('save','appearance','palette',['changed_fields'=>$changed,'reset'=>!empty($_POST['reset_palette'])]);
        $this->flash($e ?: 'Appearance saved.','success'); $this->redirect('/admin/appearance');
    }
    public function backups(): void{$this->list('Backups','settings');}
    public function audit(): void{$this->list('Audit Log','audit_events');}
    public function contactSubmissions(): void{$this->resource('Contact Submissions','contact_submissions',['name','email','phone','subject','message','status']);}
    public function saveContactSubmission(): void{$this->save('contact_submissions');}
    public function deleteContactSubmission(): void{$this->delete('contact_submissions');}
    public function supportTickets(): void{$this->render('admin/list',['pageTitle'=>'Support Tickets','title'=>'Support Tickets','collection'=>'support_tickets','items'=>(new \App\Services\SupportTicketService())->all()]);}
    public function saveSupportTicket(): void{
        $id=(string)($_POST['id']??'');
        $reply=trim((string)($_POST['reply']??''));
        if ($id !== '' && $reply !== '') {
            try {
                (new \App\Services\SupportTicketService())->reply($id, $reply);
                $this->flash('Reply saved.','success');
            } catch (\Throwable $e) {
                $this->flash('Unable to save reply.','error');
            }
        }
        $this->redirect('/admin/support-tickets');
    }
    public function emailInbox(): void{$this->render('admin/mailbox',['pageTitle'=>'Email Inbox','title'=>'Email Inbox','box'=>'inbox','items'=>(new MailStorageService())->inbox()]);}
    public function emailOutbox(): void{$this->render('admin/mailbox',['pageTitle'=>'Email Outbox','title'=>'Email Outbox','box'=>'outbox','items'=>(new MailStorageService())->outbox()]);}
    public function media(): void{$this->render('admin/media',['pageTitle'=>'Media Library','items'=>(new MediaService())->all()]);}
    public function uploadMedia(): void{$uploaded=(new MediaService())->upload($_FILES['media_files'] ?? [], $_POST['context'] ?? 'shared', $_POST['description'] ?? null); (new AuditLogService())->record('upload','media','',['count'=>count($uploaded),'context'=>$_POST['context'] ?? 'shared']); $this->flash(count($uploaded).' media file'.(count($uploaded) === 1 ? '' : 's').' uploaded.','success'); $this->redirect('/admin/media');}
    public function fixPermissions(): void{(new StoragePermissionService())->fix(); (new AuditLogService())->record('fix','permissions','storage'); $this->flash('Storage permissions checked and updated where PHP is allowed.','success'); $this->redirect('/admin/settings');}
    public function projectMap(): void{$map = \App\Services\ProjectMapService::scan(); $this->render('admin/project-map',['pageTitle' => 'Project Map', 'map'=>$map,'validation'=>\App\Services\ProjectMapService::validate($map)]);}
    public function workflow(): void{
        $agentRoot = app_path('.agents');
        $skills = [];
        foreach (glob($agentRoot . '/skills/*/SKILL.md') ?: [] as $f) {
            $name = basename(dirname($f));
            $meta = file_exists($f) ? (preg_match('/^description: (.+)$/m', (string)file_get_contents($f), $m) ? trim($m[1]) : '') : '';
            $skills[] = ['name' => $name, 'description' => $meta, 'file' => str_replace(app_path(), '', $f)];
        }
        $workflows = [];
        foreach (glob($agentRoot . '/workflows/*.md') ?: [] as $f) {
            $workflows[] = ['name' => basename($f), 'path' => str_replace(app_path(), '', $f)];
        }
        $handoffs = [];
        foreach (glob($agentRoot . '/handoffs/events/*.json') ?: [] as $f) {
            $data = json_decode((string)file_get_contents($f), true);
            $handoffs[] = ['file' => basename($f), 'issue' => $data['issue'] ?? '?', 'role' => $data['role'] ?? '?', 'next_role' => $data['next_role'] ?? '?'];
        }
        $this->render('admin/workflow', ['pageTitle'=>'Agent Workflow','skills'=>$skills,'workflows'=>$workflows,'handoffs'=>$handoffs,'agentPath'=>str_replace(app_path(), '', $agentRoot)]);
    }
    public function blog(): void{
        $blog = new \App\Services\BlogService();
        $this->render('admin/blog',['pageTitle'=>'Blog','title'=>'Blog Posts','posts'=>$blog->all(),'categories'=>$blog->categories()]);
    }
    public function saveBlog(): void{
        $blog = new \App\Services\BlogService();
        $blog->save($_POST);
        (new AuditLogService())->record('save','blog',$_POST['slug'] ?? '');
        $this->flash('Blog post saved.','success');
        $this->redirect('/admin/blog');
    }
    public function deleteBlog(): void{
        $slug = (string)($_POST['slug'] ?? '');
        if ($slug !== '') {
            (new \App\Services\BlogService())->delete($slug);
            (new AuditLogService())->record('delete','blog',$slug);
        }
        $this->flash('Blog post deleted.','info');
        $this->redirect('/admin/blog');
    }
    public function previewBlog(): void{
        $this->layout = 'app';
        $this->seoKey = 'blog.post';
        $content = (new MarkdownRenderer())->render($_POST['content'] ?? '');
        $meta = [
            'title' => $_POST['title'] ?? 'Preview',
            'slug' => $_POST['slug'] ?? '',
            'category' => $_POST['category'] ?? '',
            'excerpt' => $_POST['excerpt'] ?? '',
            'summary' => $_POST['summary'] ?? '',
            'published_at' => $_POST['published_at'] ?? date('Y-m-d'),
            'author' => $_POST['author'] ?? 'Admin',
            'og_image' => $_POST['og_image'] ?? '',
            'image_alt' => $_POST['image_alt'] ?? '',
            'source_url' => $_POST['source_url'] ?? '',
            'template' => $_POST['template'] ?? 'editorial',
        ];
        $slug = $_POST['slug'] ?? 'preview';
        $this->render('public/blog-post', [
            'content' => $content,
            'meta' => $meta,
            'slug' => $slug,
        ]);
    }
    public function aiDraftBlog(): void{
        $template = $_POST['template'] ?? 'editorial';
        $title = $_POST['title'] ?? 'Article';
        $sourceUrl = $_POST['source_url'] ?? '/';
        $draft = (new BlogDraftService())->draft($template, $title, $sourceUrl);
        $this->jsonResponse(['content' => $draft]);
    }
    public function taxReport(): void{
        $orders = (new OrderService())->all();
        $orders = array_values(array_filter($orders, fn($o) => !empty($o['invoice_number'])));
        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');
        if ($from !== '') $orders = array_values(array_filter($orders, fn($o) => ($o['invoice_date'] ?? '') >= $from));
        if ($to !== '') $orders = array_values(array_filter($orders, fn($o) => ($o['invoice_date'] ?? '') <= $to . 'T23:59:59'));
        if (($_GET['format'] ?? '') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gst-tax-report.csv"');
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice','Date','Customer','Place of Supply','Taxable','CGST','SGST','IGST','Total']);
            foreach ($orders as $o) fputcsv($handle, [$o['invoice_number']??'',substr($o['invoice_date']??'',0,10),$o['customer_email']??'',$o['place_of_supply']??'',$o['taxable_value']??0,$o['cgst_total']??0,$o['sgst_total']??0,$o['igst_total']??0,$o['total']??0]);
            fclose($handle); exit;
        }
        $totals = ['taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0,'tax'=>0,'gross'=>0];
        foreach ($orders as $o) {
            $totals['taxable'] += (float)($o['taxable_value'] ?? 0);
            $totals['cgst']    += (float)($o['cgst_total'] ?? 0);
            $totals['sgst']    += (float)($o['sgst_total'] ?? 0);
            $totals['igst']    += (float)($o['igst_total'] ?? 0);
            $totals['tax']     += (float)($o['cgst_total'] ?? 0) + (float)($o['sgst_total'] ?? 0) + (float)($o['igst_total'] ?? 0);
            $totals['gross']   += (float)($o['total'] ?? 0);
        }
        $this->render('admin/tax-report', ['pageTitle'=>'GST Tax Report','title'=>'GST Product Sales Report','orders'=>$orders,'from'=>$from,'to'=>$to,'totals'=>$totals]);
    }
    private function list(string $title, ?string $collection = null): void{$this->render('admin/list',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'items'=>$collection ? (new ResourceService($collection))->all() : []]);}
    private function resource(string $title,string $collection,array $fields): void{$this->render('admin/resource',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'fields' => $fields, 'items'=>(new ResourceService($collection))->all(), 'mediaFiles'=>$this->mediaFor($collection)]);}
    private function save(string $collection): void{
        $data=$this->cleanPost();
        $data=$this->mergeExistingRecord($collection, $data);
        if(isset($data['working_days']))$data['working_days']=$this->splitList($data['working_days']);
        if(isset($data['modes']))$data['modes']=$this->splitList($data['modes']);
        if(isset($data['languages']))$data['languages']=$this->splitList($data['languages']);
        $uploaded=$this->uploadedMedia($collection);
        if ($collection === 'astrologers') {
            $photos=$this->splitList((string)($data['photo_urls'] ?? ''));
            if (!empty($data['photo_url'])) array_unshift($photos, (string)$data['photo_url']);
            $uploadedPaths=array_column($uploaded, 'url');
            $photos=array_values(array_unique(array_filter(array_merge($photos, $uploadedPaths))));
            if (!empty($photos)) {
                $data['photo_url']=$photos[0];
                $data['photo_urls']=$photos;
            }
        }
        if ($collection === 'temples' && $uploaded && empty($data['image_url'])) $data['image_url']=$uploaded[0]['url'];
        $record=(new ResourceService($collection))->save($data);
        $entityName = (string)($record['name'] ?? $record['slug'] ?? '');
        if ($uploaded) (new MediaService())->recordUsage($uploaded, $collection, (string)($record['id'] ?? ''), $entityName);
        (new AuditLogService())->record('save',$collection,(string)($record['id'] ?? ''),['fields'=>array_keys($data),'uploaded_media'=>count($uploaded)]);
        $this->flash($collection==='astrologers'?'Consultant profile saved.':'Saved.','success');
        $this->redirect('/admin/'.$collection);
    }
    private function saveProductRecord(): void{
        $data=$this->cleanPost();
        $data=$this->mergeExistingRecord('products', $data);
        $images=$this->splitList((string)($data['image_urls'] ?? ''));
        if (!empty($data['image_url'])) array_unshift($images, (string)$data['image_url']);
        $uploaded=$this->uploadedMedia('products');
        $uploadedPaths=array_column($uploaded, 'url');
        $images=array_values(array_unique(array_filter(array_merge($images, $uploadedPaths))));
        if (!empty($images)) {
            $data['image_url']=$images[0];
            $data['image_urls']=$images;
        }
        $record=(new ResourceService('products'))->save($data);
        $entityName = (string)($record['name'] ?? $record['slug'] ?? '');
        if ($uploaded) (new MediaService())->recordUsage($uploaded, 'products', (string)($record['id'] ?? ''), $entityName);
        (new AuditLogService())->record('save','products',(string)($record['id'] ?? ''),['fields'=>array_keys($data),'uploaded_media'=>count($uploaded)]);
        $this->flash('Product saved.','success');
        $this->redirect('/admin/products');
    }
    private function delete(string $collection): void{
        $id=(string)($_POST['id']??'');
        (new ResourceService($collection))->delete($id);
        (new AuditLogService())->record('delete',$collection,$id);
        $this->flash('Deleted.','info');
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
    private static function contrast(string $hex1, string $hex2): float {
        $l1 = self::luminance($hex1); $l2 = self::luminance($hex2);
        return (max($l1,$l2) + 0.05) / (min($l1,$l2) + 0.05);
    }
    private static function luminance(string $hex): float {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        $rgb = [hexdec($hex[0].$hex[1]), hexdec($hex[2].$hex[3]), hexdec($hex[4].$hex[5])];
        $vals = [];
        foreach ($rgb as $c) { $s = $c / 255; $vals[] = $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4; }
        return 0.2126 * $vals[0] + 0.7152 * $vals[1] + 0.0722 * $vals[2];
    }
}
