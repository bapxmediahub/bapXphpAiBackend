<?php
namespace App\Services;

final class MailQueueService {
    public const SCHEDULED_SAMPLE_TYPE = 'scheduled_template_test';
    private const SAMPLE_DELAY_MINUTES = 2;
    private const SAMPLE_LIFETIME_SECONDS = 1800;

    private ?SiteProfileService $profile = null;

    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    private function profile(): SiteProfileService {
        return $this->profile ??= SiteProfileService::current();
    }

    private function templates(): MailTemplateService {
        return new MailTemplateService(profile: $this->profile());
    }

    private function supportLink(): string {
        $contact = $this->profile()->contact();
        $address = $contact['email'] ?? '';
        $url = $address !== '' ? 'mailto:' . $address : $this->siteUrl($contact['servicePath']);
        return '<a href="' . e($url) . '" style="color:' . self::BRAND['maroon'] . ';text-decoration:underline;">'
            . e($address !== '' ? 'Email ' . $address : 'Open the contact form') . '</a>';
    }

    /**
     * Transactional email shell built to the conventions real ecommerce senders use:
     * a single column capped at 600px, 16px body text, and a call to action at least
     * 44px tall — most transactional mail is read on a phone, and anything under 14px
     * is unreadable there.
     *
     * Structure is nested presentation tables with inline styles. Email-client support varies; inline/table fallbacks are retained instead of
     * depending on layout-heavy CSS, so
     * the site's stylesheet cannot be reused directly. The brand tokens below are
     * copied from assets/css/band.css so the mail matches the site.
     */
    private const BRAND = [
        'maroon'      => '#3a0003',
        'maroon_deep' => '#240002',
        'gold'        => '#d1b368',
        'gold_light'  => '#f3e8c9',
        'bg'          => '#faf7f0',
        'bg_alt'      => '#f7f0e4',
        'border'      => '#d8ccb7',
        'ink'         => '#222222',
        'muted'       => '#6c615a',
        'serif'       => "Georgia,'Times New Roman',serif",
        'sans'        => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
    ];

    private function wrapHtml(string $inner, string $audience = 'customer', string $preheader = ''): string {
        $b = self::BRAND;
        $isAdmin = $audience === 'admin';
        $settings = (new SettingsService())->public();
        $profile = $this->profile();
        $siteName = $profile->name();
        $siteUrl = rtrim($this->siteUrl('/'), '/');
        $logoUrl = trim((string)($settings['logo_url'] ?? ''));
        if ($logoUrl === '' && $profile->isReference()) $logoUrl = '/assets/images/logo-square.jpeg';
        if (str_starts_with($logoUrl, '/')) $logoUrl = $siteUrl . $logoUrl;
        $logoSize = $isAdmin ? 44 : 64;
        $logo = $logoUrl === '' ? '' : '<img src="' . e($logoUrl)
            . '" width="' . $logoSize . '" height="' . $logoSize . '" alt="' . e($siteName)
            . '" style="display:block;margin:0 auto 10px;width:' . $logoSize . 'px;height:' . $logoSize
            . 'px;border-radius:50%;border:2px solid ' . $b['gold'] . ';">';

        $legal = [];
        if (!empty($settings['gst_legal_name'])) $legal[] = e((string)$settings['gst_legal_name']);
        if (!empty($settings['gst_address']))    $legal[] = e((string)$settings['gst_address']);
        if (!empty($settings['gstin']))          $legal[] = 'GSTIN ' . e((string)$settings['gstin']);
        $legalHtml = $legal ? '<br>' . implode('<br>', $legal) : '';

        return '<!doctype html>'
            . '<html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="x-apple-disable-message-reformatting">'
            . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
            . '<style>@media(max-width:480px){.mail-content{padding:22px 18px!important}.mail-outer{padding:14px 8px!important}}</style>'
            . '<title>' . e($siteName) . '</title></head>'
            . '<body style="margin:0;padding:0;width:100%;background:' . $b['bg'] . ';-webkit-text-size-adjust:100%;">'

            // Preheader: the grey text a client shows next to the subject. Hidden in the
            // body so it never appears twice.
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($preheader !== '' ? mb_substr($preheader,0,180) : $siteName) . '</div>'

            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $b['bg'] . ';">'
            . '<tr><td align="center" class="mail-outer" style="padding:28px 12px;">'

            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid ' . $b['border'] . ';border-radius:14px;overflow:hidden;">'

            // Header
            . '<tr><td align="center" style="background:' . ($isAdmin ? $b['maroon_deep'] : $b['maroon']) . ';padding:22px 24px;border-bottom:3px solid ' . $b['gold'] . ';">'
            . $logo
            . '<div style="font-family:' . $b['serif'] . ';font-size:' . ($isAdmin ? 16 : 19) . 'px;font-weight:bold;color:' . $b['gold'] . ';letter-spacing:0.4px;">'
            . e($siteName) . '</div>'
            // Admin mail is labelled so it is obvious at a glance in a shared inbox that
            // this is a store notification, not something a customer received.
            . ($isAdmin ? '<div style="margin-top:6px;font-family:' . $b['sans'] . ';font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#c9b89a;">Store notification</div>' : '')
            . '</td></tr>'

            // Body — 16px, the floor for comfortable reading on a phone
            . '<tr><td class="mail-content" style="padding:30px 28px 10px;font-family:' . $b['sans'] . ';font-size:16px;line-height:1.65;color:' . $b['ink'] . ';">'
            . $inner . '</td></tr>'

            // Footer
            . '<tr><td style="padding:10px 28px 26px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td style="border-top:1px solid ' . $b['border'] . ';padding-top:18px;font-family:' . $b['sans'] . ';font-size:12px;line-height:1.7;color:' . $b['muted'] . ';">'
            . ($isAdmin
                ? '<strong style="color:' . $b['ink'] . ';font-family:' . $b['serif'] . ';font-size:14px;">Store notification</strong>'
                  . '<br>Sent to the store owner. Customers do not receive this email.'
                  . '<br><a href="' . e($siteUrl . '/admin') . '" style="color:' . $b['maroon'] . ';text-decoration:underline;">Open the admin panel</a>'
                : '<strong style="color:' . $b['ink'] . ';font-family:' . $b['serif'] . ';font-size:14px;">' . e($siteName) . '</strong>' . $legalHtml
                  . '<br><br>Need help? ' . $this->supportLink() . '.'
                  . '<br><a href="' . e($siteUrl) . '" style="color:' . $b['maroon'] . ';text-decoration:underline;">' . e(preg_replace('#^https?://#', '', $siteUrl)) . '</a>')
            . '</td></tr></table></td></tr>'

            . '</table>'
            . '<div style="font-family:' . $b['sans'] . ';font-size:11px;color:' . $b['muted'] . ';padding:14px 8px 0;max-width:600px;">'
            . ($isAdmin
                ? 'Automated store notification. Change the recipient in Admin &rarr; Integrations.'
                : 'You received this following an account, order, or enquiry activity with ' . e($siteName) . '.')
            . '</div>'
            . '</td></tr></table></body></html>';
    }

    /** Heading for the one thing the email is about. */
    public static function heading(string $text): string {
        return '<h1 style="margin:0 0 14px;font-family:' . self::BRAND['serif'] . ';font-size:22px;line-height:1.3;'
            . 'font-weight:bold;color:' . self::BRAND['maroon'] . ';">' . e($text) . '</h1>';
    }

    /** Key/value panel for order and appointment details. */
    public static function details(array $rows): string {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="table-layout:fixed;width:100%;margin:18px 0;background:' . self::BRAND['bg_alt'] . ';border:1px solid ' . self::BRAND['border'] . ';border-radius:10px;">';
        foreach ($rows as $label => $value) {
            if (trim((string)$value) === '') continue;
            $html .= '<tr>'
                . '<td style="padding:9px 16px;font-family:' . self::BRAND['sans'] . ';font-size:13px;color:' . self::BRAND['muted'] . ';white-space:normal;overflow-wrap:anywhere;word-wrap:break-word;">' . e((string)$label) . '</td>'
                . '<td align="right" style="padding:9px 16px;font-family:' . self::BRAND['sans'] . ';font-size:14px;font-weight:600;color:' . self::BRAND['ink'] . ';overflow-wrap:anywhere;word-wrap:break-word;">' . $value . '</td>'
                . '</tr>';
        }
        return $html . '</table>';
    }

    /** Absolute URL for links in email; relative paths are useless in an inbox. */
    private function siteUrl(string $path = '/'): string {
        $base = rtrim((string)(require app_path('config/database.php'))['app_url'], '/');
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Call to action. Outlook ignores padding on <a>, so the colour sits on a table
     * cell; 44px minimum height is the accepted mobile tap target.
     */
    public static function button(string $label, string $url): string {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">'
            . '<tr><td align="center" bgcolor="' . self::BRAND['maroon'] . '" style="border-radius:999px;height:46px;">'
            . '<a href="' . e($url) . '" style="display:inline-block;padding:13px 30px;font-family:' . self::BRAND['sans'] . ';'
            . 'font-size:15px;font-weight:600;color:' . self::BRAND['gold'] . ';text-decoration:none;border-radius:999px;">'
            . e($label) . '</a></td></tr></table>';
    }

    /** Stable per business event and recipient; no email or event text is stored in the ID. */
    public static function deliveryIdentity(string $type,string $recipient,string $event): string {
        return substr(hash('sha256',$type."\0".strtolower(trim($recipient))."\0".$event),0,16);
    }

    /** Hold only automated contact mail, including legacy records lacking event metadata. */
    public static function contactAutomationHeld(array $row): bool {
        $type = $row['type'] ?? $row['source'] ?? '';
        if (in_array($type, ['contact_customer_confirmation', 'appointment_owner_notification'], true)) return true;
        if ($type !== 'admin_notification') return false;
        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
        return !empty($meta['contact_id']) || !empty($meta['contact_kind'])
            || in_array($row['subject'] ?? '', ['New contact form submission', 'New consultation or appointment enquiry'], true);
    }

    private static function scheduledSampleOpen(array $row, int $now): bool {
        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
        $created = strtotime((string)($row['created_at'] ?? ''));
        $template = $meta['template'] ?? null;
        return ($meta['synthetic_sample'] ?? false) === true
            && is_string($template) && isset(MailTemplateService::catalog()[$template])
            && $created !== false && $created <= $now && $now < $created + self::SAMPLE_LIFETIME_SECONDS;
    }

    private static function eligibleForClaim(array $row, int $now, bool $scheduledSamplesOnly): bool {
        $isSample = ($row['type'] ?? '') === self::SCHEDULED_SAMPLE_TYPE;
        if ($isSample !== $scheduledSamplesOnly || ($isSample && !self::scheduledSampleOpen($row, $now))) return false;
        $available = strtotime((string)($row['available_at'] ?? ''));
        return !self::contactAutomationHeld($row) && ($row['status'] ?? '') === 'pending'
            && $available !== false && $available <= $now;
    }

    /** Pure state machine applied while the database host holds this row's lock. */
    public static function transitionDelivery(array $row,string $action,string $attempt,array $fields,\DateTimeImmutable $now): ?array {
        if (!preg_match('/^[a-f0-9]{32}$/D',$attempt)) throw new \InvalidArgumentException('Invalid mail attempt identity.');
        if ($action==='claim') {
            if (!self::eligibleForClaim($row, $now->getTimestamp(), ($fields['scheduled_samples_only'] ?? false) === true)) return null;
            $row['status']='sending';$row['delivery_state']='in_progress';$row['attempt_id']=$attempt;
            $row['attempts']=(int)($row['attempts']??0)+1;$row['attempted_at']=$now->format('c');
        } else {
            if(!in_array($action,['accepted','failed','uncertain'],true))throw new \InvalidArgumentException('Unsupported mail transition.');
            if(($row['status']??'')!=='sending'||!hash_equals((string)($row['attempt_id']??''),$attempt))return null;
            $row['status']=match($action){'accepted'=>'sent','failed'=>'failed',default=>'needs_review'};
            $row['delivery_state']=match($action){'accepted'=>'accepted_by_smtp','failed'=>'not_accepted',default=>'unknown'};
            $row['inbox_receipt']='unverified';
            if($action==='accepted') {
                $id=(string)($fields['message_id']??'');
                if(!preg_match('/^<[A-Za-z0-9._-]{1,100}@[A-Za-z0-9.-]{1,253}>$/D',$id))throw new \InvalidArgumentException('Invalid SMTP receipt identity.');
                $row['message_id']=$id;$row['sent_at']=$now->format('c');$row['smtp_accepted_at']=$now->format('c');
                $row['last_error']='';$row['error_code']='';
            } else {
                $code=(string)($fields['error_code']??'smtp_error');
                $allowed=['smtp_error','smtp_authentication','smtp_temporary','smtp_rejected','smtp_connection','smtp_outcome_unknown','not_configured'];
                $row['error_code']=in_array($code,$allowed,true)?$code:'smtp_error';
                $row['last_error']=str_replace('_',' ',$row['error_code']);$row['failed_at']=$now->format('c');
            }
        }
        $row['updated_at']=$now->format('c');return $row;
    }

    public static function errorCategory(\Throwable $error): string {
        if($error instanceof SmtpDeliveryUncertainException)return 'smtp_outcome_unknown';
        if(in_array($error->getCode(),[534,535],true))return 'smtp_authentication';
        if(in_array($error->getCode(),[421,450,451,452],true))return 'smtp_temporary';
        if($error->getCode()>=500&&$error->getCode()<=599)return 'smtp_rejected';
        if(str_contains(strtolower($error->getMessage()),'connect'))return 'smtp_connection';
        return 'smtp_error';
    }

    /** Canonical queue snapshot, not a receipt from a recipient mailbox. */
    public static function health(array $rows,?int $now=null): array {
        $now??=time();$states=array_fill_keys(['pending','sending','sent','failed','needs_review','unknown'],0);
        $due=0;$oldest=null;$heldContact=0;$scheduled=0;$expired=0;
        foreach($rows as $row){$status=(string)($row['status']??'unknown');
            if(!isset($states[$status]))$status='unknown';$states[$status]++;
            if($status==='pending' && self::contactAutomationHeld($row)){$heldContact++;continue;}
            if($status==='pending' && ($row['type']??'')===self::SCHEDULED_SAMPLE_TYPE){
                if(self::scheduledSampleOpen($row,$now))$scheduled++;else $expired++;
                continue;
            }
            if($status==='pending'){$at=strtotime((string)($row['available_at']??''));
                if($at!==false&&$at<=$now){$due++;$oldest=$oldest===null?$at:min($oldest,$at);}}
        }
        return ['total'=>count($rows),'states'=>$states,'due_pending'=>$due,'held_contact_pending'=>$heldContact,
            'oldest_due_age_seconds'=>$oldest===null?null:max(0,$now-$oldest),'inbox_delivery'=>'unverified',
            'scheduled_samples'=>$scheduled,'inactive_samples'=>$expired];
    }

    /** Count completed states in MySQL and pass only pending routing evidence to the existing health rules. */
    public function outboxHealth(bool $newsletter, ?int $now = null): array {
        $status = "JSON_UNQUOTE(JSON_EXTRACT(_data, '$.status'))";
        $scope = $newsletter ? " AND JSON_UNQUOTE(JSON_EXTRACT(_data, '$.type')) = 'blog_newsletter'" : '';
        $rows = $this->store->query("SELECT CAST($status AS BINARY) AS status, COUNT(*) AS total, NULL AS routing
            FROM mail_queue WHERE ($status IS NULL OR CAST($status AS BINARY) <> 'pending')$scope GROUP BY CAST($status AS BINARY)
            UNION ALL
            SELECT 'pending' AS status, 1 AS total, JSON_OBJECT(
                'status', 'pending', 'type', JSON_EXTRACT(_data, '$.type'),
                'subject', JSON_EXTRACT(_data, '$.subject'), 'available_at', JSON_EXTRACT(_data, '$.available_at'),
                'created_at', JSON_EXTRACT(_data, '$.created_at'), 'meta', JSON_OBJECT(
                    'contact_id', JSON_EXTRACT(_data, '$.meta.contact_id'),
                    'contact_kind', JSON_EXTRACT(_data, '$.meta.contact_kind'),
                    'synthetic_sample', JSON_EXTRACT(_data, '$.meta.synthetic_sample'),
                    'template', JSON_EXTRACT(_data, '$.meta.template')
                )
            ) AS routing FROM mail_queue WHERE CAST($status AS BINARY) = 'pending'$scope");
        $pending = [];
        $completed = [];
        foreach ($rows as $row) {
            if ($row['status'] === 'pending') {
                $pending[] = json_decode($row['routing'], true, flags: JSON_THROW_ON_ERROR);
            } else {
                $completed[] = $row;
            }
        }
        $health = self::health($pending, $now);
        foreach ($completed as $row) {
            $state = (string)$row['status'];
            if (!isset($health['states'][$state])) $state = 'unknown';
            $health['states'][$state] += (int)$row['total'];
            $health['total'] += (int)$row['total'];
        }
        return $health;
    }

    public function all(): array {
        return $this->store->read('mail_queue');
    }

    /**
     * $audience selects the template. Both are sent from the same authenticated
     * mailbox (support@); only the layout and footer differ, so the owner can tell a
     * store notification from something a customer received.
     */
    public function enqueue(string $type, string $to, string $subject, string $html, ?\DateTimeImmutable $availableAt = null, array $meta = [], string $audience = 'customer', ?string $eventKey = null, string $preheader = ''): array {
        if (self::contactAutomationHeld(['type'=>$type,'subject'=>$subject,'meta'=>$meta]))
            throw new \DomainException('Automatic contact email is on hold.');
        $html = $this->wrapHtml($html, $audience, $preheader);
        $record = [
            'id' => $eventKey === null ? bin2hex(random_bytes(8)) : self::deliveryIdentity($type,$to,$eventKey),
            'type' => $type,
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'status' => 'pending',
            'available_at' => ($availableAt ?? new \DateTimeImmutable())->format('c'),
            'meta' => $meta,
            'created_at' => date('c'),
        ];
        if($eventKey!==null){
            $insert=$this->store->insertIfAbsent('mail_queue',$record);$saved=$insert['record'];
            if(!$insert['inserted'])return $saved;
        }else{$saved=$this->store->upsert('mail_queue',$record);}
        try{(new MailStorageService($this->store))->recordQueuedOutbox($saved);}
        catch(\Throwable){error_log('Mail outbox projection unavailable; canonical queue entry preserved.');}
        // Only immediate messages attempt transport in this request. The row is
        // written first so a failed send leaves an auditable record.
        if ($availableAt === null) $this->deliverNow($saved);
        return $saved;
    }

    /**
     * Best-effort immediate delivery. Never throws: a mail failure must not break the
     * checkout, signup or password reset that triggered it.
     */
    public function deliverNow(array $record, ?SmtpMailer $mailer = null, bool $scheduledSamplesOnly = false): bool {
        if (self::contactAutomationHeld($record)) return false;
        $attempt=bin2hex(random_bytes(16));$id=(string)($record['id']??'');
        try{$claimed=$this->store->transitionMailDelivery($id,'claim',$attempt,['scheduled_samples_only'=>$scheduledSamplesOnly]);}
        catch(\Throwable){error_log('Mail delivery claim unavailable; no SMTP attempt was made.');return false;}
        if($claimed===null)return false;
        $receipt=null;
        try {
            $mailer??=new SmtpMailer((new SecretService())->all());
            if(!$mailer->configured())throw new \RuntimeException('not_configured');
            $messageId='<sps-'.$id.'@'.substr(strrchr($mailer->fromEmail(),'@'),1).'>';
            $receipt=$mailer->send((string)$claimed['to'],(string)$claimed['subject'],(string)$claimed['html'],$messageId);
        }catch(\Throwable $error){
            $action=$error instanceof SmtpDeliveryUncertainException?'uncertain':'failed';
            $code=$error->getMessage()==='not_configured'?'not_configured':self::errorCategory($error);
            try{$saved=$this->store->transitionMailDelivery($id,$action,$attempt,['error_code'=>$code]);
                if($saved!==null)(new MailStorageService($this->store))->updateOutboxForQueue($id,$saved['status'],[
                    'delivery_state'=>$saved['delivery_state'],'last_error'=>$saved['last_error'],'inbox_receipt'=>'unverified']);
            }catch(\Throwable){error_log('Mail outcome persistence incomplete; claimed entry requires review.');}
            error_log('Mail attempt outcome: '.$code);return false;
        }
        // SMTP already accepted. A database/projection error is NOT permission to resend.
        try{
            $saved=$this->store->transitionMailDelivery($id,'accepted',$attempt,['message_id'=>$receipt['message_id']]);
            if($saved===null)throw new \RuntimeException('Mail attempt receipt no longer matches');
            (new MailStorageService($this->store))->updateOutboxForQueue($id,'sent',[
                'from_email'=>$mailer->fromEmail(),'transport'=>'smtp','sent_at'=>$saved['sent_at'],
                'message_id'=>$receipt['message_id'],'delivery_state'=>'accepted_by_smtp','inbox_receipt'=>'unverified','last_error'=>'']);
        }catch(\Throwable){error_log('SMTP accepted mail; status projection incomplete. Do not automatically resend.');}
        return true;
    }

    public function templatePreview(string $key): array {
        $message=$this->templates()->sample($key);
        $message['html']=$this->wrapHtml($message['html'],$message['audience'],$message['preheader']);return $message;
    }
    public function enqueueTemplate(string $key,array $data,string $to,?string $eventKey=null,array $meta=[]): array {
        $message=$this->templates()->render($key,$data);
        return $this->enqueue($key,$to,$message['subject'],$message['html'],null,$meta,$message['audience'],$eventKey,$message['preheader']);
    }
    public function enqueueTemplateSample(string $key,string $to,string $eventKey,bool $scheduled=false): array {
        $message=$this->templates()->sample($key);
        $availableAt=$scheduled ? (new \DateTimeImmutable())->modify('+'.self::SAMPLE_DELAY_MINUTES.' minutes') : null;
        $type=$scheduled ? self::SCHEDULED_SAMPLE_TYPE : 'template_test';
        $record=$this->enqueue($type,$to,$message['subject'],$message['html'],$availableAt,
            ['template'=>$key,'synthetic_sample'=>true],$message['audience'],$eventKey,$message['preheader']);
        return $this->store->find('mail_queue',(string)$record['id'])??$record;
    }
    public function enqueueWelcome(array $user): ?array {
        if(($user['role']??'customer')!=='customer'||!filter_var($user['email']??'',FILTER_VALIDATE_EMAIL)||empty($user['id']))return null;
        return $this->enqueueTemplate('welcome',$user,(string)$user['email'],'signup:'.(string)$user['id']);
    }
    public function enqueuePaymentConfirmation(array $order): ?array {
        $to=trim((string)($order['customer_email']??''));$id=trim((string)($order['id']??''));
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)||$id==='')return null;
        $customer=$this->templates()->render('order_confirmation',$order);
        $sent=$this->enqueue('payment_confirmation',$to,$customer['subject'],$customer['html'],null,
            ['order_id'=>$id],'customer','paid-order:'.$id,$customer['preheader']);
        $owner=$this->templates()->render('owner_order',$order);
        $this->notifyAdmin($owner['subject'],$owner['html'],['order_id'=>$id],'paid-order:'.$id,$owner['preheader']);
        return $sent;
    }

    /** Render saved order lines without inventing prices or product claims. */
    public static function orderItemsHtml(array $items): string {
        if (!$items) return '';
        $html = '<h2 style="font-size:18px;color:' . self::BRAND['maroon'] . ';">Your items</h2><ul style="padding-left:20px;">';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $html .= '<li style="margin-bottom:8px;">' . e((string)($item['name'] ?? 'Product'))
                . ' &times; ' . e((string)($item['qty'] ?? 1)) . '</li>';
        }
        return $html . '</ul>';
    }

    /** Confirm that a customer enquiry reached the store without exposing an owner address. */
    public function enqueueContactConfirmation(array $submission): ?array {
        $to = trim((string)($submission['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return null;
        $name = trim((string)($submission['name'] ?? ''));
        $subject = trim((string)($submission['subject'] ?? 'General enquiry'));
        $brand = $this->profile()->name();
        $html = self::heading('We received your enquiry')
            . '<p>Hello ' . e($name !== '' ? $name : 'there') . ', thank you for contacting ' . e($brand) . '. Our team will review your message and reply soon.</p>'
            . self::details([
                'Reference' => e((string)($submission['id'] ?? '')),
                'Subject' => e($subject),
            ])
            . self::button('Visit the store', $this->siteUrl('/shop'));
        return $this->enqueue('contact_customer_confirmation', $to, 'We received your enquiry — ' . $brand, $html, null, ['contact_id' => $submission['id'] ?? '']);
    }

    /** Identify appointment-style contact enquiries without restoring public booking. */
    public static function contactLooksLikeAppointment(array $submission): bool {
        $text = trim((string)($submission['subject'] ?? '') . ' ' . (string)($submission['message'] ?? ''));
        return $text !== '' && preg_match('/\b(?:appointment|consult(?:ation)?|astrolog(?:y|er|ical))\b/i', $text) === 1;
    }

    /** Build the owner copy and audit metadata for a saved contact submission. */
    public static function contactOwnerNotification(array $submission): array {
        $appointmentRequest = self::contactLooksLikeAppointment($submission);
        return [
            'subject' => $appointmentRequest ? 'New consultation or appointment enquiry' : 'New contact form submission',
            'html' => '<p>' . ($appointmentRequest
                ? 'A consultation or appointment enquiry came in through the contact form.'
                : 'A new message came in through the contact form.') . '</p><dl>'
                . '<dt>Name</dt><dd>' . e((string)($submission['name'] ?? '')) . '</dd>'
                . '<dt>Email</dt><dd>' . e((string)($submission['email'] ?? '')) . '</dd>'
                . '<dt>Phone</dt><dd>' . e((string)($submission['phone'] ?? '')) . '</dd>'
                . '<dt>Subject</dt><dd>' . e((string)($submission['subject'] ?? '')) . '</dd>'
                . '<dt>Message</dt><dd>' . nl2br(e((string)($submission['message'] ?? '')), false) . '</dd></dl>',
            'meta' => [
                'contact_id' => (string)($submission['id'] ?? ''),
                'contact_kind' => $appointmentRequest ? 'appointment_enquiry' : 'general_enquiry',
            ],
        ];
    }

    /**
     * Send a copy to the owner. The destination is admin_notification_email, which is
     * deliberately separate from the sending mailbox: customers are written to from
     * support@, while the owner is notified at their own inbox.
     */
    public function notifyAdmin(string $subject, string $html, array $meta = [], ?string $eventKey = null, string $preheader = ''): ?array {
        $admin = trim((string)((new SecretService())->all()['admin_notification_email'] ?? ''));
        if ($admin === '' || !filter_var($admin, FILTER_VALIDATE_EMAIL)) return null;
        try {
            return $this->enqueue('admin_notification', $admin, $subject, $html, null, $meta, 'admin', $eventKey, $preheader);
        } catch (\Throwable $e) {
            error_log('Admin notification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Report an unconfirmed checkout without asserting a bank debit/reversal outcome.
     * Owner and customer notifications remain separate; no automatic payment retry.
     */
    public function enqueuePaymentFailure(array $order, string $reason = ''): ?array {
        $to = trim((string)($order['customer_email'] ?? ''));
        $detail = $reason !== '' ? '<p>Reason: ' . e($reason) . '</p>' : '';
        $this->notifyAdmin(
            'Payment failed for ' . (string)($order['id'] ?? 'an order'),
            '<p>A payment attempt failed.</p>'
            . '<p>Order: <strong>' . e((string)($order['id'] ?? '')) . '</strong><br>'
            . 'Customer: ' . e((string)($order['customer_name'] ?? '')) . ' &lt;' . e($to) . '&gt;<br>'
            . 'Amount: ₹' . e((string)($order['total'] ?? 0)) . '</p>' . $detail,
            ['order_id' => $order['id'] ?? '']
        );
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return null;
        $message=$this->templates()->render('payment_attention',$order);
        return $this->enqueue('payment_failed',$to,$message['subject'],$message['html'],null,
            ['order_id'=>$order['id']??''],'customer',null,$message['preheader']);
    }

    /** Security notice so an account holder learns about a sign-in they did not make. */
    public function enqueueLoginNotification(string $email, string $name, string $role): ?array {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $when = date('d M Y, H:i');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return $this->enqueue('login_notification', $email, 'New sign-in to your ' . $this->profile()->name() . ' account',
            '<p>Hello ' . e($name !== '' ? $name : 'there') . ',</p>'
            . '<p>Your account was signed in to on <strong>' . e($when) . '</strong> (IP ' . e($ip) . ').</p>'
            . '<p>If this was you, no action is needed. Otherwise, change your password immediately.</p><p>'
            . $this->supportLink() . ' for help.</p>',
            null, ['role' => $role]);
    }

    /**
     * Newsletter for a newly published post. Sent to registered customers; the owner and
     * consultants are skipped so a publish does not mail the whole staff.
     */
    public function enqueueBlogNewsletter(array $post, array $recipients): int {
        $title = trim((string)($post['title'] ?? ''));
        $slug = trim((string)($post['slug'] ?? ''));
        if ($title === '' || $slug === '') return 0;
        $excerpt = trim((string)($post['excerpt'] ?? $post['summary'] ?? ''));
        $sent = 0;
        foreach ($recipients as $email) {
            $email = trim((string)$email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            try {
                $this->enqueue('blog_newsletter', $email, 'New article: ' . $title,
                    self::heading($title)
                    . ($excerpt !== '' ? '<p>' . e($excerpt) . '</p>' : '')
                    . self::button('Read the article', $this->siteUrl('/blog/' . $slug)),
                    null, ['slug' => $slug]);
                $sent++;
            } catch (\Throwable $error) {
                error_log('Newsletter send failed for ' . $email . ': ' . $error->getMessage());
            }
        }
        return $sent;
    }

    public function enqueueShipmentNotification(array $order): ?array {
        $to=trim((string)($order['customer_email']??''));if(!filter_var($to,FILTER_VALIDATE_EMAIL)||empty($order['id']))return null;
        $message=$this->templates()->render('shipment',$order);
        $event='fulfillment:'.(string)$order['id'].':'.(string)($order['status']??'shipped').':'.(string)($order['tracking_id']??'');
        return $this->enqueue('shipment_notification',$to,$message['subject'],$message['html'],null,
            ['order_id'=>$order['id']],'customer',$event,$message['preheader']);
    }

    public function enqueueProductReviewRequest(array $order, int $waitDays = 10): ?array {
        $to = trim((string)($order['customer_email'] ?? ''));
        if ($to === '') return null;
        $shippedAt = new \DateTimeImmutable((string)($order['shipped_at'] ?? 'now'));
        $availableAt = $shippedAt->modify('+' . max(1, $waitDays) . ' days');
        $subject = 'How was your ' . $this->profile()->name() . ' product?';
        $html = '<p>We hope your order ' . e((string)($order['id'] ?? '')) . ' reached you well.</p>'
            . '<p>Please share your product rating from your account orders page.</p>';
        return $this->enqueue('product_review_request', $to, $subject, $html, $availableAt, ['order_id' => $order['id'] ?? ''], 'customer', 'review-request:'.(string)($order['id']??''));
    }

    public function due(?\DateTimeImmutable $now = null, bool $scheduledSamplesOnly = false): array {
        $now ??= new \DateTimeImmutable();
        $due = array_values(array_filter($this->all(), static fn(array $record): bool =>
            self::eligibleForClaim($record, $now->getTimestamp(), $scheduledSamplesOnly)));
        usort($due, fn($a, $b) => strcmp((string)($a['available_at'] ?? ''), (string)($b['available_at'] ?? '')));
        return $due;
    }

    public function markSent(string $id): void {
        $this->updateStatus($id, 'sent', ['sent_at' => date('c')]);
    }

    public function markFailed(string $id, string $error): void {
        $this->updateStatus($id, 'failed', ['last_error' => $error, 'failed_at' => date('c')]);
    }

    public function processDue(?SmtpMailer $mailer = null, ?\DateTimeImmutable $now = null, int $limit = 25, bool $scheduledSamplesOnly = false): int {
        $sent=0;
        foreach(array_slice($this->due($now,$scheduledSamplesOnly),0,max(0,min(25,$limit))) as $record){
            if($this->deliverNow($record,$mailer,$scheduledSamplesOnly))$sent++;
        }
        return $sent;
    }

    private function updateStatus(string $id, string $status, array $extra): void {
        $record=$this->store->find('mail_queue',$id);
        if($record===null)throw new \InvalidArgumentException('Mail record not found.');
        $this->store->upsert('mail_queue',array_merge($record,$extra,['status'=>$status]));
    }
}
