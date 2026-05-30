<?php
namespace App\Controllers;
use App\Services\{EnvService,SecretService,JsonStoreService,PasswordResetService};
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
  $user=$this->get('https://openidconnect.googleapis.com/v1/userinfo',$token['access_token']);
  $store=new JsonStoreService(); $users=$store->read('users'); $role = 'customer';
  foreach ($users as $u) { if (($u['id'] ?? '') === ($user['sub'] ?? '') || (($u['email'] ?? '') !== '' && ($u['email'] ?? '') === ($user['email'] ?? ''))) { $role=$u['role'] ?? (!empty($u['is_admin']) ? 'admin' : 'customer'); break; } }
  $_SESSION['user']=['sub'=>$user['sub'],'email'=>$user['email'],'name'=>$user['name']??'','picture'=>$user['picture']??'','role'=>$role];
  $store->upsert('users',['id'=>$user['sub'],'email'=>$user['email'],'name'=>$user['name']??'','picture'=>$user['picture']??'','role'=>$role]); $this->redirect('/');
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
    $role = 'customer';
    $record = ['id'=>$id,'email'=>$email,'name'=>$name,'role'=>$role,'password_hash'=>password_hash($password,PASSWORD_DEFAULT)];
    $store->upsert('users',$record,'id');
    $_SESSION['user'] = ['sub'=>$id,'email'=>$email,'name'=>$name,'role'=>$role];
    $this->flash('Registered and signed in.');
    $this->redirect('/');
 }
 public function loginPost(): void {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') { $this->flash('Email and password required.'); $this->redirect('/login'); }
    $admin = (new EnvService())->adminCredentials();
    if ($admin['email'] !== '' && $admin['password'] !== '' && $email === $admin['email'] && hash_equals($admin['password'], $password)) {
        $_SESSION['user'] = ['sub'=>'env-admin','email'=>$admin['email'],'name'=>$admin['username'] ?: 'Admin','role'=>'admin'];
        $this->flash('Signed in.');
        $this->redirect('/admin');
    }
    $store = new JsonStoreService();
    $users = $store->read('users');
    foreach ($users as $u) {
        if (($u['email'] ?? '') === $email && !empty($u['password_hash']) && password_verify($password,$u['password_hash'])) {
            $_SESSION['user'] = ['sub'=>$u['id'],'email'=>$u['email'],'name'=>$u['name'] ?? '','role'=>$u['role'] ?? (!empty($u['is_admin']) ? 'admin' : 'customer')];
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
    if ($email !== '') {
        $token = (new PasswordResetService())->createToken($email);
        if ($token) {
            $_SESSION['last_reset_link'] = '/reset-password?token=' . urlencode($token);
        }
    }
    $this->flash('If this email is registered, a reset link will be sent.');
    $this->redirect('/forgot-password');
 }
 public function resetPassword(): void {
    $this->render('public/reset-password', ['token' => $_GET['token'] ?? '']);
 }
 public function resetPasswordPost(): void {
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if ($password === '' || $password !== $confirm) {
        $this->flash('Passwords do not match.');
        $this->redirect('/reset-password?token=' . urlencode($token));
    }
    if ((new PasswordResetService())->resetPassword($token, $password)) {
        $this->flash('Password updated. Please sign in.');
        $this->redirect('/login');
    }
    $this->flash('Reset link is invalid or expired.');
    $this->redirect('/forgot-password');
 }
}
