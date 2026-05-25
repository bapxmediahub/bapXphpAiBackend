<?php
namespace App\Controllers;
use App\Services\{SecretService,JsonStoreService,PasswordResetService,SettingsService,SmtpMailer};
use App\Integrations\GoogleOAuth\GoogleOAuthClient;
final class AuthController extends BaseController {
 public function googleRedirect(): void {
  $s=(new SecretService())->all(); if(empty($s['google_client_id'])||empty($s['google_client_secret'])){$this->flash('Google login is not configured yet.');$this->redirect('/login');}
  $state=bin2hex(random_bytes(16)); $_SESSION['oauth_state']=$state;
  $url=(new GoogleOAuthClient($s['google_client_id'],$s['google_client_secret']))->authorizationUrl($this->redirectUri(),$state); $this->redirect($url);
 }
 public function callback(): void {
  if(($_GET['state']??'')!==($_SESSION['oauth_state']??'')) throw new \RuntimeException('Invalid OAuth state');
  $s=(new SecretService())->all(); $token=$this->post('https://oauth2.googleapis.com/token',['code'=>$_GET['code']??'','client_id'=>$s['google_client_id'],'client_secret'=>$s['google_client_secret'],'redirect_uri'=>$this->redirectUri(),'grant_type'=>'authorization_code']);
  $user=$this->get('https://openidconnect.googleapis.com/v1/userinfo',$token['access_token']); $_SESSION['user']=$user;
  $store=new JsonStoreService(); $store->upsert('users',['id'=>$user['sub'],'email'=>$user['email'],'name'=>$user['name']??'','picture'=>$user['picture']??'']); $this->redirect('/');
 }
 public function logout(): void {
  unset($_SESSION['user']);
  $this->flash('You are signed out.');
  $this->redirect('/');
 }
 private function redirectUri(): string { $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'sripanchamispiritual.com') . '/auth/google/callback'; }
 private function post(string $url,array $data): array { $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]); $body=curl_exec($ch); curl_close($ch); return json_decode($body,true)?:[]; }
 private function get(string $url,string $token): array { $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token]]); $body=curl_exec($ch); curl_close($ch); return json_decode($body,true)?:[]; }
 public function register(): void {
    $this->render('public/register');
 }
 public function registerPost(): void {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if ($password === '' || $email === '' || $name === '') { $this->flash('All fields are required.'); $this->redirect('/register'); }
    if ($password !== $confirm) { $this->flash('Passwords do not match.'); $this->redirect('/register'); }
    $store = new JsonStoreService();
    $users = $store->read('users');
    foreach ($users as $u) { if (($u['email'] ?? '') === $email) { $this->flash('Email already registered.'); $this->redirect('/login'); } }
    $id = bin2hex(random_bytes(8));
    $record = ['id'=>$id,'email'=>$email,'name'=>$name,'password_hash'=>password_hash($password,PASSWORD_DEFAULT)];
    $store->upsert('users',$record,'id');
    $_SESSION['user'] = ['sub'=>$id,'email'=>$email,'name'=>$name];
    $this->sendAuthNotification($email, $name, 'Welcome to Sri Panchami Spiritual', '<p>Your account has been created successfully.</p>');
    $this->flash('Registered and signed in.');
    $this->redirect('/');
 }
 public function loginPost(): void {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') { $this->flash('Email and password required.'); $this->redirect('/login'); }
    $store = new JsonStoreService();
    $users = $store->read('users');
    foreach ($users as $u) {
        if (($u['email'] ?? '') === $email && !empty($u['password_hash']) && password_verify($password,$u['password_hash'])) {
            $_SESSION['user'] = ['sub'=>$u['id'],'email'=>$u['email'],'name'=>$u['name'] ?? ''];
            $this->flash('Signed in.');
            $this->redirect('/');
        }
    }
    $this->flash('Invalid credentials.');
    $this->redirect('/login');
 }
 public function forgotPassword(): void {
    $this->render('public/forgot-password');
 }
 public function forgotPasswordPost(): void {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') { $this->flash('Enter your registered email address.'); $this->redirect('/forgot-password'); }
    $token = (new PasswordResetService())->createToken($email);
    if ($token) {
        $settings = (new SettingsService())->public();
        $link = $this->baseUrl() . '/reset-password?token=' . urlencode($token);
        try {
            (new SmtpMailer($settings))->send($email, 'Reset your Sri Panchami password', '<p>Use this secure link to reset your password. It expires in one hour.</p><p><a href="' . e($link) . '">Reset password</a></p>');
        } catch (\Throwable $e) {
            $this->flash('Password reset link created, but SMTP failed: ' . $e->getMessage());
            $this->redirect('/forgot-password');
        }
    }
    $this->flash('If that email is registered, a reset link has been sent.');
    $this->redirect('/login');
 }
 public function resetPassword(): void {
    $token = $_GET['token'] ?? '';
    $this->render('public/reset-password', ['token'=>$token]);
 }
 public function resetPasswordPost(): void {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if ($password === '' || $password !== $confirm) { $this->flash('Enter matching passwords.'); $this->redirect('/reset-password?token=' . urlencode($token)); }
    if ((new PasswordResetService())->resetPassword($token, $password)) {
        $this->flash('Password updated. You can sign in now.');
        $this->redirect('/login');
    }
    $this->flash('Reset link is invalid or expired.');
    $this->redirect('/forgot-password');
 }
 private function sendAuthNotification(string $email, string $name, string $subject, string $html): void {
    $settings = (new SettingsService())->public();
    try {
        $mailer = new SmtpMailer($settings);
        if ($mailer->configured()) $mailer->send($email, $subject, $html);
        if (!empty($settings['admin_notification_email'])) {
            $mailer->send($settings['admin_notification_email'], 'New Sri Panchami user registered', '<p>' . e($name) . ' registered with ' . e($email) . '.</p>');
        }
    } catch (\Throwable) {
        // Authentication should not fail when notification delivery is unavailable.
    }
 }
 private function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
 }
}
