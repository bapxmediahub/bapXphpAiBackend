<?php
namespace App\Controllers;
use App\Services\{AuthService,AstrologerService,ConsultationService,DatabaseService,ResourceService,AuditLogService};

final class AstrologerController extends BaseController {
    private array $user;
    public function __construct() { (new AuthService())->requireAstrologer(); $this->user=$_SESSION['user']??[]; $this->seoKey='account'; }
    public function dashboard(): void {
        if (!empty($this->user['must_change_password'])) $this->redirect('/astrologer/change-password');
        $profile=(new AstrologerService())->findBySlug($this->user['astrologer_slug']??'');
        $sessions=(new ConsultationService())->sessionsFor($this->user);
        $this->render('astrologer/dashboard',compact('profile','sessions'));
    }
    public function updateAvailability(): void {
        $this->validateCsrf();
        $status = (string)($_POST['availability_status'] ?? 'offline');
        if (!in_array($status, ['available', 'busy', 'offline'], true)) {
            $this->flash('Invalid availability status.', 'error');
            $this->redirect('/astrologer');
        }
        $profile = (new AstrologerService())->findBySlug((string)($this->user['astrologer_slug'] ?? ''));
        if (!$profile) { $this->flash('Astrologer profile not found.', 'error'); $this->redirect('/astrologer'); }
        $profile['availability_status'] = $status;
        (new ResourceService('astrologers'))->save($profile);
        (new AuditLogService())->record('availability_update', 'astrologers', (string)($profile['id'] ?? ''), ['availability_status' => $status]);
        $this->flash('Availability updated.', 'success');
        $this->redirect('/astrologer');
    }
    public function changePassword(): void { $this->render('astrologer/change-password'); }
    public function savePassword(): void {
        $this->validateCsrf();
        $password=(string)($_POST['password']??''); $confirm=(string)($_POST['password_confirm']??'');
        if(strlen($password)<10||$password!==$confirm){$this->flash('Use at least 10 characters and confirm the same password.','warning');$this->redirect('/astrologer/change-password');}
        $store=new DatabaseService(); $users=$store->read('users');
        foreach($users as &$user) if(($user['id']??'')===($this->user['sub']??'')){ $user['password_hash']=password_hash($password,PASSWORD_DEFAULT);$user['must_change_password']=false;$user['password_changed_at']=date('c'); $_SESSION['user']['must_change_password']=false; break; }
        unset($user); $store->write('users',$users); $this->flash('Password changed.','success'); $this->redirect('/astrologer');
    }
}
