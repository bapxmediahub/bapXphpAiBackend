<?php
namespace App\Controllers;
use App\Services\{SecretService,JsonStoreService};
use App\Integrations\GoogleOAuth\GoogleOAuthClient;
final class AuthController extends BaseController {
 public function redirect(): void {
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
 private function redirectUri(): string { return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'sripanchamispiritual.com') . '/auth/google/callback'; }
 private function post(string $url,array $data): array { $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]); $body=curl_exec($ch); curl_close($ch); return json_decode($body,true)?:[]; }
 private function get(string $url,string $token): array { $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token]]); $body=curl_exec($ch); curl_close($ch); return json_decode($body,true)?:[]; }
}
