<?php
namespace App\Controllers;
use App\Services\{AuthService,ConsultationService,AstrologerService,ResourceService,MailQueueService,SecretService};

final class ConsultationController extends BaseController {
    private ConsultationService $consultations;
    private array $user;
    public function __construct() {
        (new AuthService())->requireUser();
        $this->user = $_SESSION['user'] ?? [];
        $this->consultations = new ConsultationService();
        $this->seoKey = 'account';
    }
    public function status(string $id): void {
        $session=$this->session($id);
        $role=$this->user['role']??'';
        $status=(string)($this->input()['status']??'');
        if ($role==='customer' && $status==='cancelled') {
            try { $updated=$this->consultations->updateStatus($session,'cancelled','customer'); $this->jsonResponse(['session'=>$updated]); }
            catch (\InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage()],422); }
            return;
        }
        if ($role!=='admin') $this->jsonResponse(['error'=>'Administrator access required.'],403);
        try { $updated=$this->consultations->updateStatus($session,$status,$role); $this->jsonResponse(['session'=>$updated]); }
        catch (\InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage()],422); }
    }
    public function initiate(): void {
        (new AuthService())->requireUser();
        $this->validateCsrf();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = new \App\Services\RateLimiter();
        if (!$limiter->check('consult-initiate:' . $ip, 5, 60)) {
            $this->flash('Too many requests. Please try again later.', 'error');
            $this->redirect('/consult');
        }
        $limiter->hit('consult-initiate:' . $ip);
        $slug=trim($_POST['astrologer_slug']??'');
        $preferredDate=trim($_POST['preferred_date']??'');
        $preferredTime=trim($_POST['preferred_time']??'');
        $phone=trim($_POST['phone']??'');
        $notes=trim($_POST['notes']??'');
        if ($slug===''||$preferredDate===''||$preferredTime===''||$phone===''){$this->flash('Consultant, date, time, and phone are required.','error');$this->redirect('/consult/'.$slug);}
        if (strtotime($preferredDate) < strtotime(date('Y-m-d'))) {$this->flash('Choose a current or future date.','error');$this->redirect('/consult/'.$slug);}
        $astrologer=(new AstrologerService())->findBySlug($slug);
        if(!$astrologer){$this->flash('Astrologer not found.','error');$this->redirect('/consult');}
        $user=$_SESSION['user']??[];
        $email=strtolower(trim($user['email']??''));
        $id=bin2hex(random_bytes(8));
        $session=[
            'id'=>$id,'customer_email'=>$email,'customer_name'=>$user['name']??'',
            'astrologer_slug'=>$slug,'astrologer_name'=>$astrologer['name']??'','astrologer_email'=>$astrologer['email']??'',
            'mode'=>'booking','session_type'=>'Consultation booking','status'=>'requested',
            'preferred_date'=>$preferredDate,'preferred_time'=>$preferredTime,'phone'=>$phone,'notes'=>mb_substr($notes,0,2000),
            'date'=>$preferredDate,'time'=>$preferredTime,'created_at'=>date('c'),
        ];
        (new ResourceService('appointments'))->save($session);
        try{
            $secrets=(new SecretService())->all();
            $ownerEmail=trim((string)(
                ($secrets['admin_notification_email'] ?? '')
                ?: (getenv('ADMIN_NOTIFICATION_EMAIL') ?: '')
                ?: ($secrets['smtp_username'] ?? '')
                ?: (getenv('SMTP_USERNAME') ?: '')
            ));
            if($ownerEmail!=='' && filter_var($ownerEmail,FILTER_VALIDATE_EMAIL)){
                $base=rtrim((string)(getenv('APP_URL')?:''),'/');
                $html='<p>A new consultation appointment was requested.</p><dl>'
                    .'<dt>Customer</dt><dd>'.e($session['customer_name']??'').'</dd>'
                    .'<dt>Email</dt><dd>'.e($session['customer_email']??'').'</dd>'
                    .'<dt>Phone</dt><dd>'.e($phone).'</dd>'
                    .'<dt>Consultant</dt><dd>'.e($session['astrologer_name']??'').'</dd>'
                    .'<dt>Requested time</dt><dd>'.e($preferredDate.' '.$preferredTime).'</dd>'
                    .'<dt>Narration</dt><dd>'.nl2br(e($session['notes']??''),false).'</dd></dl>'
                    .'<p><a href="'.e($base.'/admin/appointments').'">Review appointments in admin</a></p>';
                (new MailQueueService())->enqueue('appointment_owner_notification',$ownerEmail,'New consultation appointment - Sri Panchami Spiritual',$html,null,['appointment_id'=>$id]);
            }
        }catch(\Throwable $e){}
        $this->flash('Consultation booking requested. The consultant will confirm the schedule.','success');
        $this->redirect('/account/dashboard/sessions');
    }

    private function session(string $id): array { $session=$this->consultations->findAccessible($id,$this->user); if(!$session)$this->jsonResponse(['error'=>'Session not found.'],404); return $session; }
    private function input(): array { $json=json_decode((string)file_get_contents('php://input'),true); return is_array($json)?$json:$_POST; }
}
