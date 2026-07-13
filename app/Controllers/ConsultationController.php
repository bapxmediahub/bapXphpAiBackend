<?php
namespace App\Controllers;
use App\Services\{AuthService,ConsultationService,AstrologerService,WalletService,ResourceService,MailQueueService,SecretService};

final class ConsultationController extends BaseController {
    private ConsultationService $consultations;
    private array $user;
    public function __construct() {
        (new AuthService())->requireUser();
        $this->user = $_SESSION['user'] ?? [];
        $this->consultations = new ConsultationService();
        $this->seoKey = 'account';
    }
    public function room(string $id): void {
        $session=$this->consultations->findAccessible($id,$this->user);
        if(!$session){ $this->renderNotFound(); }
        $secrets=(new SecretService())->all();
        $iceServers=[['urls'=>'stun:stun.l.google.com:19302']];
        if(!empty($secrets['turn_server_url']) && !empty($secrets['turn_username']) && !empty($secrets['turn_credential'])) {
            $iceServers[]=['urls'=>$secrets['turn_server_url'],'username'=>$secrets['turn_username'],'credential'=>$secrets['turn_credential']];
        }
        $this->render('account/consultation', ['session'=>$session, 'messages'=>$this->consultations->messages($id), 'currentUser'=>$this->user, 'iceServers'=>$iceServers]);
    }
    public function messages(string $id): void { $session=$this->session($id); $this->jsonResponse(['messages'=>$this->consultations->messages($id, (string)($_GET['after'] ?? '')), 'session'=>$session]); }
    public function sendMessage(string $id): void {
        $session = $this->session($id); $input = $this->input();
        try { $message=$this->consultations->sendMessage($session,$this->user,(string)($input['body'] ?? '')); $this->jsonResponse(['message'=>$message],201); }
        catch (\InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage()],422); }
    }
    public function signals(string $id): void {
        $this->session($id); $signals=$this->consultations->signals($id,(string)($_GET['after'] ?? ''),(string)($_GET['after_id'] ?? ''));
        $signals=array_values(array_filter($signals,fn($row)=>($row['sender_id']??'')!==($this->user['sub']??'')));
        $this->jsonResponse(['signals'=>$signals]);
    }
    public function sendSignal(string $id): void {
        $session=$this->session($id); $input=$this->input();
        try { $signal=$this->consultations->sendSignal($session,$this->user,(string)($input['type']??''),(array)($input['payload']??[])); $this->jsonResponse(['signal'=>$signal],201); }
        catch (\InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage()],422); }
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
    public function billCallTime(string $id): void {
        $session=$this->session($id);
        if(($session['status']??'')!=='active'){$this->jsonResponse(['error'=>'Session not active.'],400);}
        $email=strtolower(trim($this->user['email']??''));
        if(!$email){$this->jsonResponse(['error'=>'User email not found.'],400);}
        try{$result=$this->consultations->billCallTime($id,$email);$this->jsonResponse($result);}
        catch(\RuntimeException $e){$this->jsonResponse(['error'=>$e->getMessage()],400);}
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
        $mode=trim($_POST['mode']??'');
        $isWaitlist=(trim($_POST['queue_status']??'')==='waitlist');
        if ($slug===''||!in_array($mode,['text_session','direct_call'],true)){$this->flash('Invalid request.','error');$this->redirect('/consult');}
        $astrologer=(new AstrologerService())->findBySlug($slug);
        if(!$astrologer){$this->flash('Astrologer not found.','error');$this->redirect('/consult');}
        if (!$isWaitlist && ($astrologer['availability_status'] ?? 'offline') !== 'available') {
            $this->flash('This astrologer is currently unavailable. Please choose an available provider or join their waitlist.', 'warning');
            $this->redirect('/consult');
        }
        $user=$_SESSION['user']??[];
        $email=strtolower(trim($user['email']??''));
        $initialCredits=$mode==='text_session'?(int)($astrologer['message_credit_cost']??5):max(1,(int)ceil(((float)($astrologer['call_credit_per_second']??0.5))*60));
        $wallet=new WalletService();
        if(!$isWaitlist&&$wallet->balanceFor($email)<$initialCredits){$this->flash('Please recharge your wallet to start this session.','warning');$this->redirect('/account/dashboard/wallet?amount=100');}
        $id=bin2hex(random_bytes(8));
        $session=[
            'id'=>$id,'customer_email'=>$email,'customer_name'=>$user['name']??'',
            'astrologer_slug'=>$slug,'astrologer_name'=>$astrologer['name']??'','astrologer_email'=>$astrologer['email']??'',
            'mode'=>$mode,'session_type'=>$mode==='text_session'?'Message':'Call',
            'credit_rate'=>$mode==='text_session'?(string)($astrologer['message_credit_cost']??5).' credits/message':(string)($astrologer['call_credit_per_second']??0.5).' credits/sec',
            'credits_spent'=>$isWaitlist?0:$initialCredits,'status'=>$isWaitlist?'queued':'requested',
            'date'=>date('Y-m-d'),'time'=>date('H:i'),'created_at'=>date('c'),
        ];
        (new ResourceService('appointments'))->save($session);
        if(!$isWaitlist)$wallet->spend($email,$initialCredits,$id,$session['session_type'].' session with '.($session['astrologer_name']??'astrologer'));
        if(!empty($session['astrologer_email'])){
            try{(new MailQueueService())->enqueue('astrologer_session_notification',$session['astrologer_email'],
                'New consultation session - Sri Panchami Spiritual',
                '<p>Vanakkam '.e($session['astrologer_name']??'').',</p><p>A new '.e($session['session_type']??'').' session has been initiated by '.e($session['customer_name']??'a customer').'.</p><p><a href="'.rtrim((string)(getenv('APP_URL')?:''),'/').'/astrologer">Open your dashboard</a> to accept the session.</p>');
            }catch(\Throwable $e){}
        }
        $this->flash($isWaitlist?'Waitlist request saved.':'Consultation session created.','success');
        $this->redirect('/consultation/'.$id);
    }

    private function session(string $id): array { $session=$this->consultations->findAccessible($id,$this->user); if(!$session)$this->jsonResponse(['error'=>'Session not found.'],404); return $session; }
    private function input(): array { $json=json_decode((string)file_get_contents('php://input'),true); return is_array($json)?$json:$_POST; }
}
