<?php
namespace App\Controllers;
use App\Services\{AuditLogService,MailQueueService,AuthService,BlogDraftService,ConsultationService,EnvService,MailStorageService,MarkdownRenderer,MediaService,OrderService,ResourceService,SchemaService,SecretService,SettingsService,StoragePermissionService};
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
        // Public consultation booking has been retired, but historical requests remain
        // an owner responsibility. Keep them visible from the admin dashboard rather
        // than making their recovery depend on a public-site module flag.
        $appointments = (new ResourceService('appointments'))->all();
        $agentMonitoring = null;
        try { $agentMonitoring = AuditLogService::agentSummary((new AuditLogService())->all()); }
        catch (\Throwable) { /* Monitoring must not prevent dashboard access. */ }
        usort($appointments, static fn(array $a, array $b): int => strcmp(
            (string)($b['created_at'] ?? $b['preferred_date'] ?? ''),
            (string)($a['created_at'] ?? $a['preferred_date'] ?? '')
        ));
        $this->render('admin/dashboard', [
            'pageTitle' => 'Dashboard',
            'productCount' => $productCount,
            'orderCount' => $orderCount,
            'bookingCount' => count($appointments),
            'appointments' => array_slice($appointments, 0, 5),
            'agentMonitoring' => $agentMonitoring,
        ]);
    }
    public function products(): void{
        $draft = $_SESSION['product_form_draft'] ?? null;
        unset($_SESSION['product_form_draft']);
        $this->render('admin/product-form',['pageTitle'=>'Products','title'=>'Products','collection'=>'products','items'=>(new ResourceService('products'))->all(),'categories'=>(new ResourceService('categories'))->all(),'mediaFiles'=>$this->mediaFor('products'),'draft'=>$draft]);
    }
    public function saveProduct(): void{$this->saveProductRecord();}
    public function deleteProduct(): void{$this->delete('products');}
    public function categories(): void{$this->render('admin/resource',['pageTitle'=>'Categories','title'=>'Categories','collection'=>'categories','fields'=>['name','description'],'items'=>(new \App\Services\CategoryService())->all(),'mediaFiles'=>[]]);}
    public function saveCategory(): void{$this->save('categories');}
    public function deleteCategory(): void{$this->delete('categories');}
    public function collections(): void {
        $this->render('admin/collections', ['pageTitle'=>'Special Collections', 'collections'=>(new \App\Services\CategoryService())->collections(), 'products'=>(new \App\Services\ProductService())->all()]);
    }
    public function saveCollection(): void {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { $this->flash('Enter a collection title.', 'error'); $this->redirect('/admin/collections'); }
        $service = new \App\Services\CategoryService();
        $id = (string)($_POST['id'] ?? '');
        $existing = null;
        foreach ($service->collections() as $row) if (($row['id'] ?? '') === $id && $id !== '') $existing = $row;
        if ($id !== '' && !$existing) { $this->flash('Collection not found.', 'error'); $this->redirect('/admin/collections'); }
        $valid = array_fill_keys(array_map(fn($p)=>(string)$p['id'], (new \App\Services\ProductService())->all()), true);
        $ids = array_values(array_unique(array_filter(array_map('strval', (array)($_POST['product_ids'] ?? [])), fn($id)=>isset($valid[$id]))));
        $submittedOrder = is_array($_POST['product_order'] ?? null) ? $_POST['product_order'] : [];
        $originalPosition = array_flip($ids);
        usort($ids, static function (string $left, string $right) use ($submittedOrder, $originalPosition): int {
            $leftOrder = max(0, (int)($submittedOrder[$left] ?? 0));
            $rightOrder = max(0, (int)($submittedOrder[$right] ?? 0));
            return ($leftOrder <=> $rightOrder) ?: ($originalPosition[$left] <=> $originalPosition[$right]);
        });
        $record = $existing ?? ['id'=>bin2hex(random_bytes(8)), 'slug'=>'collection-'.bin2hex(random_bytes(6))];
        $record = array_merge($record, ['name'=>$name, 'is_collection'=>true, 'show_home'=>isset($_POST['show_home']), 'show_shop'=>isset($_POST['show_shop']), 'sort_order'=>max(0, (int)($_POST['sort_order'] ?? 0)), 'product_ids'=>$ids]);
        (new ResourceService('categories'))->save($record);
        (new AuditLogService())->record('save','collections',$record['id'],['fields'=>['name','show_home','show_shop','sort_order','product_ids']]);
        $this->flash('Collection saved.', 'success'); $this->redirect('/admin/collections');
    }
    public function deleteCollection(): void {
        $id = (string)($_POST['id'] ?? '');
        foreach ((new \App\Services\CategoryService())->collections() as $row) {
            if ($id !== '' && ($row['id'] ?? '') === $id) {
                (new ResourceService('categories'))->delete($id);
                (new AuditLogService())->record('delete','collections',$id);
                $this->flash('Collection deleted. Products were not deleted.', 'success'); $this->redirect('/admin/collections');
            }
        }
        $this->flash('Collection not found.', 'error'); $this->redirect('/admin/collections');
    }
    public function coupons(): void{$this->resource('Coupons','coupons',[
        'code','discount_type','discount_value','max_discount',
        'starts_at','ends_at','min_spend','max_spend',
        'usage_limit','usage_limit_per_customer','free_shipping','active',
    ]);}
    public function saveCoupon(): void{$this->save('coupons');}
    public function deleteCoupon(): void{$this->delete('coupons');}
    public function orders(): void{$this->list('Orders','orders');}
    public function order(string $id): void{
        $orders = (new ResourceService('orders'))->all();
        $order = null;
        foreach ($orders as $item) {
            if (($item['id'] ?? '') === $id) { $order = $item; break; }
        }
        $this->render('admin/detail',['pageTitle' => 'Order '.$id, 'title' => 'Order '.$id, 'order' => $order, 'taxStates' => \App\Services\TaxService::states()]);
    }
    /** Issued invoice snapshots are private and must not change with today's settings. */
    public function orderInvoice(string $id): void {
        $order = null;
        foreach ((new OrderService())->all() as $candidate) {
            if ((string)($candidate['id'] ?? '') === $id && !empty($candidate['invoice_number'])) {
                $order = $candidate;
                break;
            }
        }
        if (!$order) { http_response_code(404); echo 'Invoice not found.'; return; }
        header('Cache-Control: private, no-store');
        // The template uses this only as a fallback for old invoices without snapshots.
        // Never fill an issued invoice with current supplier settings.
        $settings = [];
        $invoiceReviewReason = \App\Services\TaxService::invoiceReviewReason($order, (new SettingsService())->public());
        $download = isset($_GET['download']) && $_GET['download'] === '1';
        if ($download) {
            $downloadResponse = self::invoiceDownload($order, $invoiceReviewReason);
            foreach ($downloadResponse['headers'] as $header) header($header);
            echo $downloadResponse['body'];
            return;
        }
        $adminInvoice = true;
        $this->render('account/invoice', compact('order', 'settings', 'adminInvoice', 'invoiceReviewReason'));
    }
    /** Build a self-contained download from the issued snapshot, with no runtime assets. */
    private static function invoiceDownload(array $order, ?string $invoiceReviewReason): array {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$order['invoice_number']);
        $settings = [];
        ob_start();
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tax invoice</title><style>body{font:15px/1.5 Arial,sans-serif;color:#222;margin:2rem} .invoice-sheet{max-width:1000px;margin:auto}.invoice-head,.invoice-parties{display:flex;justify-content:space-between;gap:2rem;flex-wrap:wrap}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:.5rem;text-align:left}.table-wrap{overflow:auto}.invoice-totals{margin-left:auto;max-width:350px}.invoice-totals p{display:flex;justify-content:space-between}@media print{.no-print{display:none}body{margin:0}}</style></head><body>';
        require app_path('views/account/invoice.php');
        echo '</body></html>';
        return [
            'headers' => [
                'Content-Type: text/html; charset=utf-8',
                'Content-Disposition: attachment; filename="invoice-' . $filename . '.html"',
            ],
            'body' => ob_get_clean(),
        ];
    }
    public function saveOrderStatus(string $id): void{
        $status = (string)($_POST['status'] ?? 'confirmed');
        $tracking = [
            'tracking_id'  => (string)($_POST['tracking_id'] ?? ''),
            'tracking_url' => (string)($_POST['tracking_url'] ?? ''),
            'courier_name' => (string)($_POST['courier_name'] ?? ''),
            'availability_confirmed' => !empty($_POST['availability_confirmed']),
        ];
        try {
            (new OrderService())->updateStatus($id, $status, null, $tracking);
            (new AuditLogService())->record('save','order.status',$id,['status'=>$status,'tracking_id'=>$tracking['tracking_id'],'availability_confirmed'=>$tracking['availability_confirmed']]);
            $this->flash('Order status updated.','success');
        } catch (\InvalidArgumentException $e) {
            // Surface the real reason — a missing tracking ID used to read as a generic
            // "unable to update", leaving the owner guessing.
            $this->flash($e->getMessage(),'error');
        } catch (\Throwable) {
            $this->flash('Unable to update order status.','error');
        }
        $this->redirect('/admin/orders/'.$id);
    }
    public function importOrderRefund(string $id): void {
        try {
            (new OrderService())->importRefund($id, trim((string)($_POST['refund_id'] ?? '')));
            (new AuditLogService())->record('save', 'order.refund', $id);
            $this->flash('Verified refund recorded.', 'success');
        } catch (\InvalidArgumentException $error) {
            $this->flash($error->getMessage(), 'error');
        } catch (\Throwable) {
            $this->flash('The refund could not be verified. Check the gateway settings and try again.', 'error');
        }
        $this->redirect('/admin/orders/' . rawurlencode($id));
    }

    public function resolveOrderTaxReview(string $id): void{
        try {
            $updated = (new OrderService())->resolveTaxReview($id, (string)($_POST['shipping_state'] ?? ''), (new SettingsService())->public());
            (new AuditLogService())->record('save', 'order.tax-review', $id, [
                'shipping_state' => $updated['shipping_state'] ?? '',
                'invoice_number' => $updated['invoice_number'] ?? '',
            ]);
            $this->flash('Delivery state corrected and invoice issued.', 'success');
        } catch (\InvalidArgumentException $error) {
            $this->flash($error->getMessage(), 'error');
        } catch (\Throwable $error) {
            error_log('Tax review recovery failed for order ' . $id . ': ' . $error->getMessage());
            $this->flash('Unable to issue the invoice. Check the order and GST settings, then try again.', 'error');
        }
        $this->redirect('/admin/orders/'.$id);
    }
    public function shipping(): void{$this->render('admin/settings',['pageTitle' => 'Shipping', 'title' => 'Shipping']);}
    public function astrologers(): void{
        $this->redirect('/admin');
    }
    public function appointments(): void{$this->list('Sessions','appointments');}
    public function consultationAnalytics(): void{$this->render('admin/consultation-analytics',['pageTitle'=>'Consultation Analytics','metrics'=>(new ConsultationService())->analytics()]);}
    public function temples(): void{$this->resource('Temples','temples',$this->schemaFields('temples',['name','description','image_url','address','map_url']));}
    public function saveTemple(): void{$this->save('temples');}
    public function deleteTemple(): void{$this->delete('temples');}
    public function settings(): void{$this->render('admin/settings',['pageTitle' => 'Settings', 'title' => 'Site Settings', 'settings'=>(new SettingsService())->public(), 'adminCredentials'=>(new EnvService())->adminCredentials()]);}
    public function saveSettings(): void{
        // The settings page posts several independent forms to this one action, so only
        // apply the fields actually submitted. Defaulting absent keys would blank out
        // every field belonging to the other forms (notably the GST configuration).
        $service = new SettingsService();
        $settings = $service->admin();
        foreach (['shipping_mode','currency','timezone','gstin','gst_legal_name','gst_trade_name','gst_address','gst_state','gst_state_code','default_hsn_code'] as $key) {
            if (array_key_exists($key, $_POST)) $settings[$key] = trim((string)$_POST[$key]);
        }
        if (array_key_exists('flat_rate', $_POST)) $settings['flat_rate'] = max(0, (float)$_POST['flat_rate']);
        if (array_key_exists('default_gst_rate', $_POST)) $settings['default_gst_rate'] = max(0, min(100, (float)$_POST['default_gst_rate']));
        $changedModules = [];
        foreach (array_keys(SettingsService::MODULES) as $key) {
            $field = 'module_' . $key;
            if (!array_key_exists($field, $_POST)) continue;
            $settings[$field] = ((string)$_POST[$field] === '1') ? '1' : '0';
            $changedModules[] = $field;
        }
        $service->savePublic($settings);
        (new AuditLogService())->record('save','settings','public',['fields'=>array_merge(array_values(array_intersect(['shipping_mode','flat_rate','currency','timezone','gstin'], array_keys($_POST))), $changedModules)]);
        $this->flash('Settings saved.','success');
        $this->redirect('/admin/settings');
    }
    public function saveAdminCredentials(): void{(new EnvService())->saveAdminCredentials($_POST); (new AuditLogService())->record('save','admin-credentials','env'); $this->flash('Admin credentials saved.','success'); $this->redirect('/admin/settings');}
    public function integrations(): void {
        $secrets = new SecretService();
        $this->render('admin/integrations', [
            'pageTitle' => 'Integrations',
            'secrets' => $secrets->all(),
            'comparisonModels' => self::comparisonModels($secrets->getModelConfig()),
        ]);
    }
    public function saveIntegrations(): void{(new SecretService())->save($_POST); (new AuditLogService())->record('save','integrations','secrets'); $this->flash('Integration settings saved.','success'); $this->redirect('/admin/integrations');}
    public function agent(): void{
        $secrets = new SecretService();
        $modelConfig = $secrets->getModelConfig();
        // Everything the owner may write @ against, so the chat can autocomplete rather
        // than making them remember slugs and filenames.
        $attachables = (new \App\Services\AgentAttachmentService())->catalogue();
        $this->render('admin/agent',['pageTitle'=>'AI Agent','modelConfig'=>$modelConfig,'attachables'=>$attachables]);
    }
    public function agentAsk(): void{
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {$this->jsonResponse(['error'=>'Message is required'],400); return;}
        $startedAt = microtime(true);
        try {
            // @terms, @privacy, @some-article and @filename.jpg pull that document into
            // the prompt. Resolved here so both a draft command and an ordinary question
            // can refer to one.
            $attachments = (new \App\Services\AgentAttachmentService())->resolve($message);

            // /create-blog and /add-product draft content and hand back a filled-in form.
            // The agent never writes to the site: the owner reads the draft and saves it
            // through the same route the normal admin screens post to.
            $command = \App\Services\AgentDraftService::command($message);
            if ($command !== null) {
                $draft = (new \App\Services\AgentDraftService())->draft(
                    $command,
                    \App\Services\AgentDraftService::subject($message),
                    $attachments['context']
                );
                $draft['missing'] = $attachments['missing'];
                (new AuditLogService())->agentRun('admin', 'draft', $startedAt);
                $this->jsonResponse(['draft' => $draft]);
                return;
            }
            $secrets = new SecretService();
            $modelConfig = $secrets->getModelConfig();
            $tools = new \App\Services\AgentToolRegistry(ownerQuestion: $message);
            // Straight aggregate questions do not need a model round trip. The same
            // allowlisted sales tool supplies a deterministic, privacy-safe answer.
            $localAnswer = $attachments['context'] === '' ? $tools->aggregateAnswer($message) : null;
            if ($localAnswer !== null) {
                (new AuditLogService())->agentRun('admin', 'local', $startedAt);
                $this->jsonResponse(['answer' => $localAnswer, 'missing' => $attachments['missing']]);
                return;
            }
            $context = (new \App\Services\AgentContextService())->forOwnerAnalytics()
                . ($attachments['context'] !== '' ? "\n\nDocuments the owner attached with @:" . $attachments['context'] : '');
            $reason = null;
            if (!empty($modelConfig['apiKey'])) {
                $answer = $this->callAiApi($modelConfig, $message, $context, $reason, $tools);
            } else {
                $reason = 'not_configured';
                $answer = "AI model not configured. Go to Admin → Integrations and set api_endpoint, ai_api_key, and agent_model.";
            }
            $failed = empty($modelConfig['apiKey']) || \App\Services\AiClient::isError($answer);
            (new AuditLogService())->agentRun('admin', $failed ? 'error' : ($reason === 'rejected_response' ? 'fallback' : 'model'), $startedAt, $reason);
            if ($failed) {
                $this->jsonResponse(['error'=>$answer, 'missing'=>$attachments['missing']],502);
                return;
            }
            $this->jsonResponse(['answer'=>$answer, 'missing'=>$attachments['missing']]);
        } catch (\Throwable $e) {
            (new AuditLogService())->agentRun('admin', 'error', $startedAt, 'internal_error');
            $this->jsonResponse(['error'=>'Agent error: '.$e->getMessage()],500);
        }
    }
    /**
     * One-shot model call for the blog Enhance buttons. Reuses the configured agent
     * model rather than adding a second provider path. Returns null when no key is set
     * so the caller can give the owner a specific message.
     */
    /** Shared with the support bot so both agents strip scaffold the same way. */
    private function cleanAnswer(string $text): string {
        $cleaner = new \App\Services\AiReplyCleaner();
        $clean = $cleaner->clean($text, '');
        if ($clean === '' || $cleaner->looksInternal($clean)) {
            return 'I could not produce a clear answer to that. Try asking it a different way.';
        }
        return $clean;
    }

    private function askModel(string $prompt): ?string {
        return (new \App\Services\AiClient())->completeOrNull($prompt);
    }

    /**
     * The provider call now lives in AiClient, shared with the customer support agent,
     * which used to hardcode its own endpoint and model and so ignored whatever the
     * admin had configured here. This method keeps the admin's prompt and its
     * error-to-screen behaviour.
     */
    private function callAiApi(array $config, string $message, string $context, ?string &$reason = null, ?\App\Services\AgentToolRegistry $tools = null): string {
        // "Answer directly / give only the final answer" produced a bare "5" for
        // "how many enquiries came for orders" — and that 5 was the order count from
        // the statistics blob, not enquiries, of which there are none. A number with no
        // stated source is unverifiable, so the persona now asks for the figure, how it
        // was arrived at, and what was checked.
        $prompt = "You are the admin analyst for this store, talking to its owner. "
            . "Be brief and concrete: two to five sentences, or a short Markdown list.\n"
            . "When you give a number, say what it counts, where it came from, and any judgement you made "
            . "to arrive at it. If a word in the question is open to interpretation, say how you read it.\n"
            . "If the data shows none of something, say so plainly. Never substitute a different number that "
            . "happens to be available.\n"
            . "End with the single most useful next step, when there is one.\n"
            . "Do not restate your role, the context, the constraints or the question. Do not show your reasoning "
            . "or a plan.\n"
            . "You can look things up. Use the tools for anything about a specific order, product, coupon, "
            . "support enquiry or article, or for sales over a period, instead of saying you do not have the "
            . "information. Only report what a tool returned; never invent an order id, a tracking number or a "
            . "figure.\n"
            . "\n{$context}\n\nQuestion: {$message}";
        // With tools the agent can answer "where is order 9426" — the data was always
        // there, but nothing let the model ask for it.
        $client = new \App\Services\AiClient();
        $tools ??= new \App\Services\AgentToolRegistry(ownerQuestion: $message);
        $answer = $client->completeWithTools($prompt, $tools);
        $reason = $client->failureCategory();
        if (\App\Services\AiClient::isError($answer)) return $answer;
        if (trim($answer) === '') return 'AI request failed: the provider returned no answer. Please try again.';

        // A bare figure is the failure this persona exists to prevent, so it is caught
        // rather than trusted. One retry, then whatever comes back is shown — an
        // unadorned number the owner can see is better than a loop they wait through.
        if (self::isBareFigure($answer) && !self::wantsNumberOnly($message)) {
            $retry = $client->completeWithTools(
                $prompt . "\n\nYour previous reply was just \"" . trim($answer) . "\". "
                . "Say what that number counts, which data you checked to get it, and how you interpreted the question.",
                $tools
            );
            if (!\App\Services\AiClient::isError($retry) && trim($retry) !== '') {
                $answer = $retry;
                $reason = $client->failureCategory();
            } // A failed retry is discarded: the original answer is still shown.
        }
        $clean = $this->cleanAnswer($answer);
        if ($clean === 'I could not produce a clear answer to that. Try asking it a different way.') {
            $reason = 'rejected_response';
        }
        return $clean;
    }

    /** A reply that is only a number, with nothing saying what it counts. */
    private static function isBareFigure(string $answer): bool {
        $stripped = trim(preg_replace('/[\s*_`#.,:₹%-]+/u', '', $answer) ?? '');
        return $stripped !== '' && mb_strlen($stripped) <= 12 && preg_match('/^\d+$/', $stripped) === 1;
    }

    /** The owner explicitly asked for just the figure. */
    private static function wantsNumberOnly(string $question): bool {
        return (bool)preg_match(
            '/\b(?:just|only)\s+(?:the\s+)?(?:number|count|figure|total)\b'   // "just the number"
            . '|\b(?:number|count|figure|total)\s+only\b'                     // "the count only"
            . '|\bno\s+explanation\b/i',
            $question
        );
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
    public function media(): void{
        $media = new MediaService();
        $this->render('admin/media',['pageTitle'=>'Media Library','items'=>$media->all(),
            'imageHealth'=>$media->productImageHealth((new ResourceService('products'))->all()),
            'uploadStorageReady'=>is_dir(MediaService::persistentDirectory())
                ? is_writable(MediaService::persistentDirectory())
                : is_writable(dirname(MediaService::persistentDirectory()))]);
    }
    /**
     * Every upload in the admin lands in the media library, whichever screen sent it.
     *
     * The agent chat uploads here too, and comes back to the chat rather than dumping
     * the owner on the media page mid-conversation. The return path is restricted to
     * this admin so a crafted form cannot bounce anyone off-site.
     */
    public function uploadMedia(): void{
        try {
            $uploaded=(new MediaService())->upload($_FILES['media_files'] ?? [], $_POST['context'] ?? 'shared', $_POST['description'] ?? null);
        } catch (\InvalidArgumentException $error) {
            $this->flash($error->getMessage(), 'error');
            $this->redirect('/admin/media');
        }
        (new AuditLogService())->record('upload','media','',['count'=>count($uploaded),'context'=>$_POST['context'] ?? 'shared']);
        $names = array_filter(array_map(fn($f) => basename((string)($f['url'] ?? $f['path'] ?? '')), $uploaded));
        $this->flash(
            count($uploaded).' media file'.(count($uploaded) === 1 ? '' : 's').' uploaded.'
            . ($names ? ' Attach with @'.implode(' or @', $names).'.' : ''),
            'success'
        );
        $redirect = (string)($_POST['redirect'] ?? '');
        $this->redirect(preg_match('#^/admin/[a-z0-9/-]*$#i', $redirect) ? $redirect : '/admin/media');
    }
    public function fixPermissions(): void{(new StoragePermissionService())->fix(); (new AuditLogService())->record('fix','permissions','storage'); $this->flash('Storage permissions checked and updated where PHP is allowed.','success'); $this->redirect('/admin/settings');}
    /**
     * Ask the configured model one real question and report exactly what came back.
     *
     * Both agents fall back to a canned reply when a call is rejected — correct for a
     * customer, but it left the owner unable to tell "the model is switched off" from
     * "the model name is wrong" or "the key expired". Every one of those looked like a
     * bot that had stopped thinking.
     */
    public function testAi(): void{
        $this->validateCsrf();
        $config = (new SecretService())->getModelConfig();
        $model = (string)($config['model'] ?? \App\Services\SecretService::PRIMARY_MODEL);
        $client = new \App\Services\AiClient();
        $answer = $client->complete('Reply with exactly: connection ok', 32, 0.0);
        $ok = !\App\Services\AiClient::isError($answer) && trim($answer) !== '';
        $timings = $client->transferTimings();
        $lastAttempt = end($timings);
        $answeringModel = $ok ? ($lastAttempt['model'] ?? $model) : $model;
        $_SESSION['ai_test_result'] = [
            'ok' => $ok,
            'model' => $answeringModel,
            'endpoint' => (string)($config['endpoint'] ?? ''),
            'message' => $ok ? trim($answer) : ($answer !== '' ? $answer : 'The provider returned an empty response.'),
            'timings' => $timings,
            'tool_calls' => null,
        ];
        (new AuditLogService())->record('test','ai',$model,['result'=>$ok ? 'ok' : 'failed']);
        $this->redirect('/admin/integrations');
    }

    private static function comparisonModels(array $config): array {
        return [];
    }

    public function testEmail(): void{
        $to = trim((string)($_POST['test_email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mail_test_result'] = ['ok'=>false,'transport'=>'','message'=>'Enter a valid email address.'];
            $this->redirect('/admin/integrations');
        }
        $mailer = new \App\Services\SmtpMailer((new SecretService())->all());
        if (!$mailer->configured()) {
            $_SESSION['mail_test_result'] = ['ok'=>false,'transport'=>'','message'=>'SMTP is not fully configured. Save the host, port, mailbox username, password and From Email above first.'];
            $this->redirect('/admin/integrations');
        }
        $transport = $mailer->transport();
        $sentAt = date('c');
        try {
            $mailer->send(
                $to,
                'Test email from Sri Panchami Spiritual',
                '<p>This is a test message sent from Admin &rarr; Integrations.</p>'
                . '<p>Transport: <strong>' . e($transport) . '</strong><br>From: <strong>' . e($mailer->fromEmail()) . '</strong><br>Sent: ' . e($sentAt) . '</p>'
                . '<p>If you received this, transactional email is working.</p>'
            );
            $_SESSION['mail_test_result'] = ['ok'=>true,'transport'=>$transport,'message'=>'Test email sent to ' . $to . ' from ' . $mailer->fromEmail() . '. Check the inbox, and the spam folder.'];
            (new AuditLogService())->record('test','email',$to,['transport'=>$transport,'result'=>'sent']);
        } catch (\Throwable $e) {
            $_SESSION['mail_test_result'] = ['ok'=>false,'transport'=>$transport,'message'=>$e->getMessage()];
            (new AuditLogService())->record('test','email',$to,['transport'=>$transport,'result'=>'failed','error'=>$e->getMessage()]);
        }
        $this->redirect('/admin/integrations');
    }
    public function blog(): void{
        $this->requireModule('blog');
        $blog = new \App\Services\BlogService();
        // The editor's "Choose from uploads" picker needs the library, otherwise it
        // renders empty and the only way to set an image is to type a path.
        $this->render('admin/blog',[
            'pageTitle'=>'Blog','title'=>'Blog Posts',
            'posts'=>$blog->all(true),'categories'=>$blog->categories(),
            'mediaFiles'=>(new MediaService())->all('blog'),
            'currentAuthor'=>$this->currentAuthorName(),
        ]);
    }
    /** The signed-in admin's own name, falling back to a generic byline. */
    private function currentAuthorName(): string {
        $user = $_SESSION['user'] ?? [];
        foreach (['name', 'username'] as $key) {
            $value = trim((string)($user[$key] ?? ''));
            if ($value !== '') return $value;
        }
        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') return explode('@', $email)[0];
        return 'Admin';
    }

    public function saveBlog(): void{
        $blog = new \App\Services\BlogService();
        $slug = trim((string)($_POST['slug'] ?? ''));
        // Only a post that did not exist before triggers the newsletter, so editing an
        // article never re-mails everyone who already received it.
        $isNew = $slug === '' || $blog->find($slug) === null;

        // The date and the author are facts the site already knows, so it should not be
        // asking for them. Left blank they were saved blank, which is why a new post
        // showed no date and sorted to the bottom of the list. Both stay editable for
        // the case where a post is backdated or written by someone else.
        $post = $_POST;
        if (trim((string)($post['published_at'] ?? '')) === '') {
            $existing = $slug !== '' ? $blog->find($slug) : null;
            $post['published_at'] = (string)($existing['published_at'] ?? '') ?: date('Y-m-d');
        }
        if (trim((string)($post['author'] ?? '')) === '') {
            $post['author'] = $this->currentAuthorName();
        }
        $post['updated_at'] = date('Y-m-d');
        $blog->save($post);
        (new AuditLogService())->record('save','blog',$_POST['slug'] ?? '');
        $sent = 0;
        if ($isNew && ($_POST['notify_subscribers'] ?? '') === '1') {
            $sent = $this->sendBlogNewsletter($_POST);
        }
        $this->flash($sent > 0 ? "Blog post saved. Newsletter sent to {$sent} subscriber(s)." : 'Blog post saved.','success');
        $this->redirect('/admin/blog');
    }
    /** Registered customers only — the owner and consultants are not subscribers. */
    private function sendBlogNewsletter(array $post): int {
        try {
            $recipients = [];
            foreach ((new \App\Services\DatabaseService())->read('users') as $user) {
                $role = (string)($user['role'] ?? 'customer');
                if ($role !== 'customer') continue;
                $email = trim((string)($user['email'] ?? ''));
                if ($email !== '') $recipients[] = $email;
            }
            return (new MailQueueService())->enqueueBlogNewsletter($post, array_unique($recipients));
        } catch (\Throwable $e) {
            error_log('Blog newsletter failed: ' . $e->getMessage());
            return 0;
        }
    }
    /** Flip a post between published and hidden without opening the editor. */
    public function toggleBlog(): void{
        $slug = trim((string)($_POST['slug'] ?? ''));
        $blog = new \App\Services\BlogService();
        $post = $slug !== '' ? $blog->find($slug) : null;
        if (!$post) { $this->flash('Post not found.','error'); $this->redirect('/admin/blog'); }
        $post['published'] = empty($post['published']);
        $blog->save($post);
        (new AuditLogService())->record('save','blog.published',$slug,['published'=>$post['published']]);
        $this->flash($post['published'] ? 'Post is now visible on the site.' : 'Post is now hidden from the site.','success');
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
    /**
     * Enhance one field at a time. The single AI Draft button rewrote the whole body
     * from a title, so improving a headline meant losing the article. Title and content
     * are now separate and each returns only its own field.
     */
    public function enhanceBlogTitle(): void{
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') { $this->jsonResponse(['error' => 'Enter a title first.'], 400); return; }
        $result = $this->askModel(
            "Rewrite this blog headline for a Tamil spiritual products and astrology site. "
            . "Return ONE headline only, plain text, no quotes, no markdown, under 70 characters.\n\nHeadline: " . $title
        );
        if ($result === null) { $this->jsonResponse(['error' => 'No AI API key is configured. Set ai_api_key in Admin → Integrations.'], 400); return; }
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($result)) ?? $result, " \t\n\r\0\x0B\"'");
        $this->jsonResponse(['title' => mb_substr($clean, 0, 120)]);
    }
    public function enhanceBlogContent(): void{
        $content = trim((string)($_POST['content'] ?? ''));
        if ($content === '') { $this->jsonResponse(['error' => 'Write some content first.'], 400); return; }
        $result = $this->askModel(
            "Improve the clarity and flow of this blog article. Keep the author's meaning, language and "
            . "any Tamil text. Return Markdown only — no HTML, no code fences around the whole answer, "
            . "no commentary.\n\nArticle:\n" . $content
        );
        if ($result === null) { $this->jsonResponse(['error' => 'No AI API key is configured. Set ai_api_key in Admin → Integrations.'], 400); return; }
        $this->jsonResponse(['content' => trim($result)]);
    }
    public function taxReport(): void{
        $orders = (new OrderService())->all();
        $orders = array_values(array_filter($orders, fn($o) => !empty($o['invoice_number'])));
        $settings = (new SettingsService())->public();
        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');
        if ($from !== '') $orders = array_values(array_filter($orders, fn($o) => ($o['invoice_date'] ?? '') >= $from));
        if ($to !== '') $orders = array_values(array_filter($orders, fn($o) => ($o['invoice_date'] ?? '') <= $to . 'T23:59:59'));
        if (($_GET['format'] ?? '') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gst-tax-report.csv"');
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice','Date','Customer','Place of Supply','Taxable','CGST','SGST','IGST','Total','Review']);
            foreach ($orders as $o) fputcsv($handle, [$o['invoice_number']??'',substr($o['invoice_date']??'',0,10),$o['customer_email']??'',$o['place_of_supply']??'',$o['taxable_value']??0,$o['cgst_total']??0,$o['sgst_total']??0,$o['igst_total']??0,$o['total']??0,\App\Services\TaxService::invoiceReviewReason($o, $settings)??'']);
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
        $invoiceReviewReasons = [];
        foreach ($orders as $o) if ($reason = \App\Services\TaxService::invoiceReviewReason($o, $settings)) $invoiceReviewReasons[(string)($o['id'] ?? $o['invoice_number'] ?? '')] = $reason;
        $this->render('admin/tax-report', ['pageTitle'=>'GST Tax Report','title'=>'GST Product Sales Report','orders'=>$orders,'from'=>$from,'to'=>$to,'totals'=>$totals,'invoiceReviewReasons'=>$invoiceReviewReasons]);
    }
    private function list(string $title, ?string $collection = null): void{$this->render('admin/list',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'items'=>$collection ? (new ResourceService($collection))->all() : []]);}
    private function resource(string $title,string $collection,array $fields): void{$this->render('admin/resource',['pageTitle' => $title, 'title' => $title, 'collection' => $collection, 'fields' => $fields, 'items'=>(new ResourceService($collection))->all(), 'mediaFiles'=>$this->mediaFor($collection)]);}
    private function save(string $collection): void{
        $data=$this->cleanPost();
        // Saving the blank form created an empty record. Every collection here is
        // identified by a name or a code, so refuse when neither is present rather than
        // adding a nameless row the owner then has to hunt down and delete.
        $identifier = trim((string)($data['name'] ?? $data['code'] ?? $data['title'] ?? ''));
        if ($identifier === '') {
            $this->flash('Enter a name before saving.', 'error');
            $this->redirect('/admin/'.$collection);
        }
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
        // Product details are structured so the public product page can present them
        // consistently. Empty fields intentionally clear old imported values.
        foreach (['highlights', 'description_points'] as $field) {
            if (array_key_exists($field, $_POST)) $data[$field] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$_POST[$field]) ?: []), static fn($line) => $line !== ''));
        }
        if (array_key_exists('specifications', $_POST)) {
            $data['specifications'] = $this->parseSpecifications((string)$_POST['specifications']);
        }
        if ($conflict = \App\Services\ProductService::contentConflict($data)) {
            $draft = $_POST;
            unset($draft['_csrf']);
            $_SESSION['product_form_draft'] = $draft;
            $this->flash($conflict . ' Correct the description or specifications before saving.', 'error');
            $this->redirect('/admin/products');
        }
        // Explicit empty gallery fields clear images; omitted fields preserve them.
        // Existing database galleries are arrays, not strings.
        $gallery = $_POST['image_urls'] ?? $data['image_urls'] ?? [];
        $images = is_array($gallery) ? $gallery : $this->splitList((string)$gallery);
        $featured = trim((string)($_POST['image_url'] ?? $data['image_url'] ?? ''));
        if ($featured !== '') array_unshift($images, $featured);
        try { $uploaded=$this->uploadedMedia('products'); }
        catch (\InvalidArgumentException $error) {
            $draft = $_POST;
            unset($draft['_csrf']);
            $_SESSION['product_form_draft'] = $draft;
            $this->flash($error->getMessage() . ' Your product details are kept below. Select the image again before saving.', 'error');
            $this->redirect('/admin/products');
        }
        $uploadedPaths=array_column($uploaded, 'url');
        $images=array_values(array_unique(array_filter((string)($_POST['upload_featured'] ?? '') === '1'
            ? array_merge($uploadedPaths, $images)
            : array_merge($images, $uploadedPaths))));
        unset($data['upload_featured']);
        $data['image_url']=$images[0] ?? '';
        $data['image_urls']=$images;
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
    private function parseSpecifications(string $value): array {
        $specifications = [];
        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = preg_split('/\s*[:=]\s*/', $line, 2);
            if (count($parts) !== 2) continue;
            [$label, $detail] = array_map('trim', $parts);
            if ($label !== '' && $detail !== '') $specifications[$label] = $detail;
        }
        return $specifications;
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
