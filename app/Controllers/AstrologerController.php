<?php
namespace App\Controllers;
use App\Services\{AuthService,AstrologerService,ConsultationService,DatabaseService};

final class AstrologerController extends BaseController {
    private array $user;
    public function __construct() { (new AuthService())->requireAstrologer(); $this->user=$_SESSION['user']??[]; $this->seoKey='account'; }
    public function dashboard(): void {
        if (!empty($this->user['must_change_password'])) $this->redirect('/astrologer/change-password');
        $profile=(new AstrologerService())->findBySlug($this->user['astrologer_slug']??'');
        $sessions=(new ConsultationService())->sessionsFor($this->user);
        $this->render('astrologer/dashboard',compact('profile','sessions'));
    }
    public function changePassword(): void { $this->render('astrologer/change-password'); }
    public function savePassword(): void {
        $password=(string)($_POST['password']??''); $confirm=(string)($_POST['password_confirm']??'');
        if(strlen($password)<10||$password!==$confirm){$this->flash('Use at least 10 characters and confirm the same password.','warning');$this->redirect('/astrologer/change-password');}
        $store=new DatabaseService(); $users=$store->read('users');
        foreach($users as &$user) if(($user['id']??'')===($this->user['sub']??'')){ $user['password_hash']=password_hash($password,PASSWORD_DEFAULT);$user['must_change_password']=false;$user['password_changed_at']=date('c'); $_SESSION['user']['must_change_password']=false; break; }
        unset($user); $store->write('users',$users); $this->flash('Password changed.','success'); $this->redirect('/astrologer');
    }
}
