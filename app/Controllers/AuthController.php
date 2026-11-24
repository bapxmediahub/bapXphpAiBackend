<?php
namespace App\Controllers;
use App\Services\{EnvService,SecretService,DatabaseService,PasswordResetService,AddressService,MailQueueService,SettingsService};
use App\Integrations\GoogleOAuth\GoogleOAuthClient;
final class AuthController extends BaseController {
  public function googleRedirect(): void {
  $s=(new SecretService())->all(); if(empty($s['google_client_id'])||empty($s['google_client_secret'])){$this->flash('Google login is not configured yet.','warning');$this->redirect('/login');}
  $state=bin2hex(random_bytes(16)); $_SESSION['oauth_state']=$state; $_SESSION['oauth_state_created_at']=time();
  $url=(new GoogleOAuthClient($s['google_client_id'],$s['google_client_secret']))->authorizationUrl($this->redirectUri(),$state); $this->redirect($url);
  }
  public function callback(): void {
    if(($_GET['state']??'')!==($_SESSION['oauth_state']??'')){$this->flash('Invalid OAuth state. Please try again.','error');$this->redirect('/login');}
    if(($_SESSION['oauth_state_created_at']??0)<time()-600){unset($_SESSION['oauth_state'],$_SESSION['oauth_state_created_at']);$this->flash('OAuth state expired. Please try again.','error');$this->redirect('/login');}
   $s=(new SecretService())->all(); $token=$this->post('https://oauth2.googleapis.com/token',['code'=>$_GET['code']??'','client_id'=>$s['google_client_id'],'client_secret'=>$s['google_client_secret'],'redirect_uri'=>$this->redirectUri(),'grant_type'=>'authorization_code']);
   if(!empty($token['error'])||empty($token['access_token'])){$this->flash('Google login failed. Please try again.','error');$this->redirect('/login');}
   $user=$this->get('https://openidconnect.googleapis.com/v1/userinfo',$token['access_token']);
   if(empty($user)){$this->flash('Failed to fetch your profile from Google. Please try again.','error');$this->redirect('/login');}
    $store=new DatabaseService(); $users=$store->read('users'); $role = 'customer'; $astrologerSlug = ''; $mustChange = false; $existingId = ''; $profileName = $user['name'] ?? ''; $profilePhone = ''; $matchedExisting = false;
    foreach ($users as $u) { if (($u['id'] ?? '') === ($user['sub'] ?? '') || (($u['email'] ?? '') !== '' && ($u['email'] ?? '') === ($user['email'] ?? ''))) { $matchedExisting=true; $role=$u['role'] ?? (!empty($u['is_admin']) ? 'admin' : 'customer'); $astrologerSlug=$u['astrologer_slug'] ?? ''; $mustChange=(bool)($u['must_change_password'] ?? false); $profileName=$u['name'] ?? $profileName; $profilePhone=$u['phone'] ?? ''; if (($u['email'] ?? '') === ($user['email'] ?? '')) $existingId=$u['id']??''; break; } }
    $userId = $existingId ?: ($user['sub'] ?? bin2hex(random_bytes(8)));
    unset($_SESSION['oauth_state']);
    session_regenerate_id(true);
    $_SESSION['user']=['sub'=>$userId,'email'=>$user['email'],'name'=>$profileName,'phone'=>$profilePhone,'username'=>explode('@',$user['email'])[0],'picture'=>$user['picture']??'','role'=>$role,'astrologer_slug'=>$astrologerSlug];
    try {
        $savedUser=$store->upsert('users',['id'=>$userId,'email'=>$user['email'],'name'=>$profileName,'picture'=>$user['picture']??'','role'=>$role]);
        if (!$matchedExisting && $role === 'customer' && ($user['email_verified']??false) === true) {
            try{(new MailQueueService())->enqueueWelcome($savedUser);}catch(\Throwable){error_log('New customer welcome could not be queued.');}
        }
    } catch (\Throwable) {error_log('Google account persistence could not be confirmed.');}
    $this->flash('Signed in.','success');
    session_write_close();
   if ($role === 'admin') { $this->redirect('/admin'); return; }
   if ($role === 'astrologer') { $_SESSION = []; $this->flash('Consultant access is managed by the site administrator.','info'); $this->redirect('/login'); }
    $this->redirect('/account/dashboard');
  }
 public function logout(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
   $params = session_get_cookie_params();
   setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
  }
  session_destroy();
  session_start();
  $this->flash('You are signed out.','info');
  $this->redirect('/login');
 }
 private function redirectUri(): string {
   $configured = trim((string)($_ENV['APP_URL'] ?? getenv('APP_URL') ?? ''));
   $base = $configured !== '' ? $configured : 'https://sripanchamispiritual.com';
   return rtrim($base, '/') . '/auth/google/callback';
 }
 private function post(string $url,array $data): array { $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_TIMEOUT=>10]); $body=curl_exec($ch); curl_close($ch); return json_decode($body,true)?:[]; }
 private function get(string $url,string $token): array { $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token],CURLOPT_TIMEOUT=>10]); $body=curl_exec($ch); curl_close($ch); return json_decode($body,true)?:[]; }
  public function register(): void {
     if (!empty($_SESSION['user'])) $this->redirect((($_SESSION['user']['role'] ?? '') === 'admin') ? '/admin' : '/account/dashboard');
     $this->seoKey = 'register';
     $secrets = (new \App\Services\SecretService())->all();
     $this->render('public/register', [
         'googleAuthEnabled' => !empty($secrets['google_client_id']) && !empty($secrets['google_client_secret']),
     ]);
  }
  public function registerPost(): void {
    $this->validateCsrf();
    $field=static fn(string $key):string=>is_string($_POST[$key]??null)?$_POST[$key]:'';
    $email = trim($field('email'));
    $name = trim($field('name'));
    $password = $field('password');
    $confirm = $field('password_confirm');
    $phone = trim($field('phone'));
    $address = trim($field('address'));
    $city = trim($field('city'));
    $pincode = trim($field('pincode'));
    $state = \App\Services\TaxService::normaliseState($field('state'));
    if ($password === '' || $email === '' || $name === '' || $phone === '' || $address === '' || $city === '' || $pincode === '') { $this->flash('Account and delivery address fields are required.','error'); $this->redirect('/register'); }
    if ($state === null) { $this->flash('Select a valid delivery state.','error'); $this->redirect('/register'); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->flash('Invalid email address.','error'); $this->redirect('/register'); }
    if ($password !== $confirm) { $this->flash('Passwords do not match.','error'); $this->redirect('/register'); }
    if (empty($_POST['accept_terms'])) { $this->flash('You must accept the Terms of Service and Privacy Policy to register.','error'); $this->redirect('/register'); }
    // Reserve mail/registration admission atomically before any account or welcome mutation.
    // A fresh Google-verified account uses its own existing authenticated OAuth path.
    try {
        $admitted=\App\Services\RateLimiter::consumeAccountMail('register',(string)($_SERVER['REMOTE_ADDR']??'unknown'),$email);
    } catch (\Throwable) { $admitted=false; error_log('Registration admission unavailable.'); }
    if(!$admitted){$this->flash('Registration is temporarily limited. Please try later or sign in with Google if configured.','error');$this->redirect('/register');}
    $store = new DatabaseService();
    $users = $store->read('users');
    foreach ($users as $u) { if (strcasecmp((string)($u['email'] ?? ''), $email)===0) { $this->flash('Email already registered.','error'); $this->redirect('/login'); } }
    $id = bin2hex(random_bytes(8));
    $role = 'customer';
    $record = ['id'=>$id,'email'=>$email,'name'=>$name,'role'=>$role,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'accepted_terms_at'=>date('c')];
    $store->upsert('users',$record,'id');
    (new AddressService($store))->save($email, ['address_name'=>'Home','name'=>$name,'phone'=>$phone,'address'=>$address,'city'=>$city,'pincode'=>$pincode,'state'=>$state,'is_default'=>true]);
    session_regenerate_id(true);
    $_SESSION['user'] = ['sub'=>$id,'email'=>$email,'name'=>$name,'role'=>$role];
    try {
        (new MailQueueService())->enqueueWelcome($record);
    } catch (\Throwable) {}
    $this->flash('Registered and signed in.','success');
    $this->redirect('/');
 }
  public function loginPost(): void {
    $this->validateCsrf();
    $this->checkRateLimit('login', 5, 60);
    $email = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') { $this->flash('Username or email and password required.','error'); $this->redirect('/login'); }
    $admin = (new EnvService())->adminCredentials();
    if ($admin['email'] !== '' && $admin['password'] !== '' && \App\Services\AuthService::matchesAdminIdentifier($admin,$email)) {
        if (password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['sub'=>'env-admin','email'=>$admin['email'],'name'=>$admin['username'] ?: 'Admin','role'=>'admin'];
            try { (new MailQueueService())->enqueueLoginNotification($admin['email'], $admin['username'] ?: 'Admin', 'admin'); }
            catch (\Throwable $e) { error_log('Login notification failed: ' . $e->getMessage()); }
            $this->flash('Signed in.','success');
            $this->redirect('/admin');
        }
    }
    $store = new DatabaseService();
    $users = $store->read('users');
    foreach ($users as $u) {
        $matches = strcasecmp((string)($u['email'] ?? ''), $email) === 0 || strcasecmp((string)($u['username'] ?? ''), $email) === 0;
        if ($matches && !empty($u['password_hash']) && password_verify($password,$u['password_hash'])) {
            if (($u['role'] ?? '') === 'astrologer') { $this->flash('Consultant access is managed by the site administrator.','info'); $this->redirect('/login'); }
            session_regenerate_id(true);
            $_SESSION['user'] = ['sub'=>$u['id'],'email'=>$u['email'] ?? '','username'=>$u['username'] ?? '','name'=>$u['name'] ?? '','phone'=>$u['phone'] ?? '','role'=>$u['role'] ?? (!empty($u['is_admin']) ? 'admin' : 'customer'),'astrologer_slug'=>$u['astrologer_slug'] ?? '','must_change_password'=>(bool)($u['must_change_password'] ?? false)];
            $this->flash('Signed in.','success');
            session_write_close();
            $this->redirect(($u['role'] ?? '') === 'customer' ? '/account/dashboard' : '/');
        }
    }
    $this->flash('Invalid credentials.','error');
    error_log(sprintf('Failed login attempt for %s from %s', $email, $_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $this->redirect('/login');
  }
  public function forgotPassword(): void {
     $this->seoKey = 'forgot-password';
     $this->render('public/forgot-password');
  }
  public function forgotPasswordPost(): void {
    $this->validateCsrf();
    $email = is_string($_POST['email']??null)?trim($_POST['email']):'';
    // Identical public response for unknown addresses, rate limits, transport failures
    // and admitted requests. Never rotate a reset token on a denied request.
    $admitted=false;
    if(filter_var($email,FILTER_VALIDATE_EMAIL)) {
        try {
            $admitted=\App\Services\RateLimiter::consumeAccountMail('recovery',(string)($_SERVER['REMOTE_ADDR']??'unknown'),$email);
        } catch (\Throwable) { error_log('Recovery admission unavailable.'); }
    }
    if ($admitted) {
        try {
            $token = (new PasswordResetService())->createToken($email);
            if ($token) {
                $link = $this->siteUrl('/reset-password?token=' . urlencode($token));
                (new MailQueueService())->enqueueTemplate('password_reset',['reset_url'=>$link],$email);
            }
        } catch (\Throwable) { error_log('Password recovery delivery could not be confirmed.'); }
    }
    $this->flash('If the address is registered and recovery is available, check your inbox for a reset link. If no email arrives, wait before requesting another.','info');
    $this->redirect('/forgot-password');
  }
  public function resetPassword(): void {
     $this->seoKey = 'reset-password';
     $this->render('public/reset-password', ['token' => $_GET['token'] ?? '']);
 }
  public function resetPasswordPost(): void {
    $this->validateCsrf();
    $this->checkRateLimit('reset-password', 3, 120);
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if ($password === '' || $password !== $confirm) {
        $this->flash('Passwords do not match.','error');
        $this->redirect('/reset-password?token=' . urlencode($token));
    }
    $resetEmail = (new PasswordResetService())->emailForToken($token);
    if ((new PasswordResetService())->resetPassword($token, $password)) {
        // Confirm the change to the account holder. If the reset was not theirs, this
        // is the only signal they get that someone else changed their password.
        if ($resetEmail !== '' && filter_var($resetEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                (new MailQueueService())->enqueueTemplate('password_changed',[],$resetEmail);
            } catch (\Throwable $e) { error_log('Password change mail failed: ' . $e->getMessage()); }
        }
        $this->flash('Password updated. Please sign in.','success');
        $this->redirect('/login');
    }
    $this->flash('Reset link is invalid or expired.','error');
    $this->redirect('/forgot-password');
 }
}
