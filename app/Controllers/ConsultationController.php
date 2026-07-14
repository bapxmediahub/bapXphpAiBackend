<?php
namespace App\Controllers;
use App\Services\{AuthService,ConsultationService,AstrologerService,ResourceService,MailQueueService};

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
        if ($role!=='astrologer' && $role!=='admin') $this->jsonResponse(['error'=>'Astrologer access required.'],403);
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
        if(!empty($session['astrologer_email'])){
            try{(new MailQueueService())->enqueue('astrologer_session_notification',$session['astrologer_email'],
                'New consultation session - Sri Panchami Spiritual',
                '<p>Vanakkam '.e($session['astrologer_name']??'').',</p><p>A consultation booking was requested by '.e($session['customer_name']??'a customer').' for '.e($preferredDate).' at '.e($preferredTime).'.</p><p><a href="'.rtrim((string)(getenv('APP_URL')?:''),'/').'/astrologer">Open your dashboard</a> to review the booking.</p>');
            }catch(\Throwable $e){}
        }
        $this->flash('Consultation booking requested. The consultant will confirm the schedule.','success');
        $this->redirect('/account/dashboard/sessions');
    }

    private function session(string $id): array { $session=$this->consultations->findAccessible($id,$this->user); if(!$session)$this->jsonResponse(['error'=>'Session not found.'],404); return $session; }
    private function input(): array { $json=json_decode((string)file_get_contents('php://input'),true); return is_array($json)?$json:$_POST; }
}
