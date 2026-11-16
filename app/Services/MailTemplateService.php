<?php
declare(strict_types=1);
namespace App\Services;

/** Pure event rendering: never chooses recipients, creates orders or issues invoices. */
final class MailTemplateService
{
    private string $base;
    public function __construct(?string $base=null){
        $base??=(string)(require app_path('config/database.php'))['app_url'];
        $url=parse_url($base);
        if(!self::safeUrl($base)||isset($url['query'])||isset($url['fragment']))throw new \InvalidArgumentException('Valid site origin required.');
        $this->base=rtrim($base,'/');
    }
    public static function catalog(): array {
        return [
            'welcome'=>['title'=>'Welcome & account access','audience'=>'customer','trigger'=>'Successfully created customer account (manual or Google), once per account.'],
            'order_confirmation'=>['title'=>'Paid order & invoice access','audience'=>'customer','trigger'=>'New gateway-verified order; availability holds are stated explicitly.'],
            'owner_order'=>['title'=>'Owner: new paid order','audience'=>'admin','trigger'=>'Same verified order event; configured owner-notification recipient only.'],
            'payment_attention'=>['title'=>'Payment needs checking','audience'=>'customer','trigger'=>'Checkout cannot confirm completion; never claims the bank made no charge.'],
            'shipment'=>['title'=>'Shipment / delivery update','audience'=>'customer','trigger'=>'Explicit order status update; delivered is not described as a fresh shipment.'],
            'password_reset'=>['title'=>'Password reset','audience'=>'customer','trigger'=>'Requested valid account recovery; the real one-time link is never included in previews.'],
            'password_changed'=>['title'=>'Password changed','audience'=>'customer','trigger'=>'Successful account password change, not an attempted/failed change.'],
        ];
    }
    public function render(string $key,array $data): array {
        if(!isset(self::catalog()[$key]))throw new \InvalidArgumentException('Unknown message template.');
        $audience=self::catalog()[$key]['audience'];$body='';$subject='';$preview='';
        $name=self::text($data['customer_name']??$data['name']??'there');
        if($key==='welcome'){
            $subject='Welcome to Sri Panchami Spiritual';$preview='Your account is ready. Orders, saved addresses and support in one place.';
            $body=$this->label('ACCOUNT READY').MailQueueService::heading('Welcome to Sri Panchami Spiritual')
                .'<p>Vanakkam '.e($name).',</p><p>Your customer account is ready. Keep your order details, delivery addresses and support requests together.</p>'
                .MailQueueService::button('Open your account',$this->url('/account/dashboard'))
                .$this->nextSteps(['Review your profile and delivery address before checkout.','Use the same account to view your orders and issued invoices.','Never share your password or a one-time sign-in code with support.']);
        }elseif(in_array($key,['order_confirmation','owner_order'],true)){
            $id=self::text($data['id']??'');if($id==='')throw new \InvalidArgumentException('Order reference required.');
            $held=!empty($data['fulfillment_review_required']);$owner=$key==='owner_order';
            $subject=$owner?'New paid order '.$id:($held?'Payment received — order under review':'Order confirmed — Sri Panchami Spiritual');
            $preview=$held?'Payment recorded; product availability requires review before fulfillment.':'Order '.$id.' — '.self::money($data['total']??null).' recorded. View order and invoice details.';
            $body=$this->label($held?'PAYMENT RECORDED · REVIEW REQUIRED':'PAYMENT RECORDED')
                .MailQueueService::heading($owner?'A new paid order needs your attention':($held?'We received your payment':'Thank you for your order'))
                .'<p>'.($owner?'Review the saved order before dispatch.':'Vanakkam '.e($name).', your verified payment has been recorded.').'</p>'
                .($held?'<p style="padding:14px;background:#fff3d8;border-left:4px solid #b98224"><strong>Product availability review:</strong> fulfillment is on hold while the team checks this order. Payment received does not guarantee immediate dispatch.</p>':'<p>'.($owner?'Use the owner order screen for fulfillment and customer updates.':'The team will prepare your order and send a shipping update when dispatched.').'</p>')
                .MailQueueService::details(['Order reference'=>e($id),'Payment total'=>e(self::money($data['total']??null)),
                    'Invoice'=>e(self::text($data['invoice_number']??''))]);
            $body.=$this->items((array)($data['items']??[]));
            $rows=[];
            foreach(['subtotal'=>'Item subtotal','discount'=>'Discount','shipping_cost'=>'Shipping'] as $field=>$title){if(isset($data[$field])&&is_numeric($data[$field]))$rows[$title]=e(self::money($data[$field]));}
            if($rows!==[])$body.=MailQueueService::details($rows);
            if($owner)$body.=MailQueueService::details(['Customer'=>e($name),'Customer email'=>e(self::text($data['customer_email']??''))]);
            $path=$owner?'/admin/orders/':'/account/orders/';
            $body.=MailQueueService::button($owner?'Open owner order':'View your order',$this->url($path.rawurlencode($id)));
            if(!$owner&&!empty($data['invoice_number']))$body.='<p><a href="'.e($this->url('/account/orders/'.rawurlencode($id).'/invoice')).'" style="color:#3a0003;font-weight:bold">View invoice</a> — sign in with the account used for this order. This email is not a replacement for the issued invoice.</p>';
            if(!$owner&&empty($data['invoice_number']))$body.='<p>Invoice details are not yet available in this notification. Check the order page or contact the team; no invoice number has been invented.</p>';
            if(!$owner)$body.='<p style="font-size:14px;color:#6c615a">Please do not pay again for the same confirmed order. Keep the order reference when contacting support.</p>';
        }elseif($key==='payment_attention'){
            $subject='Please check your payment status';$preview='Completion was not confirmed. Check the payment reference before trying again.';
            $body=$this->label('PAYMENT CHECK NEEDED').MailQueueService::heading('We could not confirm completion')
                .'<p>Vanakkam '.e($name).', we could not confirm that this checkout completed. This message does not establish whether your bank has charged or reversed the payment.</p>'
                .MailQueueService::details(['Reference'=>e(self::text($data['id']??'')),'Attempted amount'=>e(self::money($data['total']??null))])
                .'<p><strong>Do not pay again while the outcome is uncertain.</strong> Check your order page and the payment-provider or bank reference. Contact the team with that reference if a debit is visible but no confirmed order appears.</p>'
                .MailQueueService::button('Check your orders',$this->url('/account/dashboard/orders'))
                .'<p>Your cart may still be available. Retry payment only after you have established the earlier attempt did not complete.</p>';
        }elseif($key==='shipment'){
            $delivered=($data['status']??'')==='delivered';
            $subject=$delivered?'Your order is marked delivered':'Your Sri Panchami Spiritual order has shipped';
            $preview=$delivered?'Check your delivery and contact the team if anything is missing.':'Courier and tracking details for your order.';
            $body=$this->label($delivered?'DELIVERY UPDATE':'DISPATCH UPDATE').MailQueueService::heading($delivered?'Your order is marked delivered':'Your order is on its way')
                .'<p>'.($delivered?'The order record is marked delivered. If you have not received it or an item needs attention, contact the team with the order reference.':'The team has recorded your dispatch. Check the courier for current progress; tracking information can take time to appear.').'</p>'
                .MailQueueService::details(['Order'=>e(self::text($data['id']??'')),'Courier'=>e(self::text($data['courier_name']??'')),'Tracking reference'=>e(self::text($data['tracking_id']??''))]);
            $tracking=self::text($data['tracking_url']??'');
            $body.=MailQueueService::button(!$delivered&&self::safeUrl($tracking)?'Track your parcel':'View your order',!$delivered&&self::safeUrl($tracking)?$tracking:$this->url('/account/dashboard/orders'));
        }elseif($key==='password_reset'){
            $link=self::text($data['reset_url']??'');
            if(!self::safeUrl($link)||!str_starts_with($link,$this->base.'/reset-password?'))throw new \InvalidArgumentException('A valid same-site reset link is required.');
            $subject='Reset your Sri Panchami Spiritual password';$preview='Use your requested reset link; do not share it with anyone.';
            $body=$this->label('ACCOUNT SECURITY').MailQueueService::heading('Reset your password')
                .'<p>Use the link below only if you requested a password reset. It is intended for you and must not be forwarded.</p>'
                .MailQueueService::button('Reset your password',$link)
                .'<p>If you did not request this, ignore this email. Your password is not changed merely by receiving it. An expired or used link needs a new recovery request.</p>';
        }else{
            $subject='Your Sri Panchami Spiritual password was changed';$preview='Security notice: review this change if you did not make it.';
            $body=$this->label('ACCOUNT SECURITY').MailQueueService::heading('Your password was changed')
                .'<p>The application confirmed a password change for your account. If this was you, no further action is required.</p>'
                .'<p><strong>If this was not you, act now:</strong> use the account recovery flow and contact support. Do not send your password or reset link by email.</p>'
                .MailQueueService::button('Open account help',$this->url('/forgot-password'));
        }
        return ['template'=>$key,'audience'=>$audience,'subject'=>$subject,'preheader'=>$preview,'html'=>$body];
    }
    public function sample(string $key): array {
        $data=['id'=>'TEST-SAMPLE','name'=>'Sample Customer','customer_name'=>'Sample Customer','customer_email'=>'sample@example.invalid',
            'total'=>1250,'subtotal'=>1200,'shipping_cost'=>50,'items'=>[['name'=>'Sample brass lamp','qty'=>2,'line_total'=>1200]],
            'status'=>'shipped','courier_name'=>'Example courier','tracking_id'=>'SAMPLE-NOT-TRACKABLE',
            'reset_url'=>$this->url('/reset-password?token=TEST-SAMPLE-NOT-VALID')];
        $result=$this->render($key,$data);
        $result['subject']='[TEST SAMPLE - NO ORDER] '.self::catalog()[$key]['title'];
        $result['preheader']='Synthetic template test only. No order, payment, invoice or password change.';
        $result['html']='<div style="padding:16px;margin-bottom:22px;border:2px solid #b98224;background:#fff3d8"><strong>TEST SAMPLE ONLY</strong><br>No order was placed, no payment was taken and no invoice or account change was issued. Names, amounts and references below are synthetic examples.</div>'.$result['html'];
        // Diagnostic messages must not send users to nonexistent private order/reset targets.
        $result['html']=preg_replace_callback('/href="([^"]+)"/',static function(array $m):string{
            $url=html_entity_decode($m[1],ENT_QUOTES,'UTF-8');
            return str_contains($url,'TEST-SAMPLE')?'href="#sample-only"':$m[0];
        },$result['html'])??'';
        return $result;
    }
    public static function sampleRecipient(string $target,string $customerEmail,string $ownerEmail): string {
        if(!in_array($target,['customer','owner'],true))throw new \InvalidArgumentException('Choose customer or configured owner.');
        $email=trim($target==='owner'?$ownerEmail:$customerEmail);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n\0]/',$email))throw new \InvalidArgumentException('A valid recipient is required.');
        return $email;
    }
    private function items(array $items): string {
        if($items===[])return '';
        $html='<h2 style="font-size:18px;margin:24px 0 10px;color:#3a0003">Order items</h2><table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px"><thead><tr><th align="left" style="padding:10px 4px;border-bottom:2px solid #d8ccb7">Item</th><th style="padding:10px 4px;border-bottom:2px solid #d8ccb7">Qty</th><th align="right" style="padding:10px 4px;border-bottom:2px solid #d8ccb7">Line total</th></tr></thead><tbody>';
        foreach(array_slice($items,0,100) as $item){if(!is_array($item))continue;
            $html.='<tr><td style="padding:12px 4px;border-bottom:1px solid #e8dfd1;word-break:break-word">'.e(self::text($item['name']??'Item')).'</td><td align="center" style="padding:12px 4px;border-bottom:1px solid #e8dfd1">'.e(self::text($item['qty']??'')).'</td><td align="right" style="padding:12px 4px;border-bottom:1px solid #e8dfd1;white-space:nowrap">'.e(self::money($item['line_total']??null)).'</td></tr>';
        }
        return $html.'</tbody></table>';
    }
    private function label(string $text): string {return '<p style="margin:0 0 12px;color:#6c615a;font-size:11px;font-weight:bold;letter-spacing:1px">'.e($text).'</p>';}
    private function nextSteps(array $steps): string {return '<h2 style="font-size:17px;color:#3a0003">What you can do next</h2><ul style="padding-left:20px">'.implode('',array_map(static fn($s)=>'<li style="margin-bottom:9px">'.e($s).'</li>',$steps)).'</ul>';}
    private function url(string $path): string {return $this->base.'/'.ltrim($path,'/');}
    private static function money(mixed $value): string {return is_numeric($value)&&is_finite((float)$value)&&$value>=0?'₹'.number_format((float)$value,2):'Not recorded';}
    private static function text(mixed $value): string {return is_scalar($value)?mb_substr(trim((string)$value),0,4000):'';}
    private static function safeUrl(string $url): bool {$p=parse_url($url);return is_array($p)&&in_array($p['scheme']??'',['http','https'],true)&&!isset($p['user'])&&!isset($p['pass'])&&!preg_match('/[\x00-\x20\x7f\\\\]/',$url)&&filter_var($url,FILTER_VALIDATE_URL)!==false;}
}
