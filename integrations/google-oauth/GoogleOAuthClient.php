<?php
namespace App\Integrations\GoogleOAuth;
final class GoogleOAuthClient {
    public function __construct(private string $clientId, private string $clientSecret){}
    public function authorizationUrl(string $redirectUri, string $state): string {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(['client_id'=>$this->clientId,'redirect_uri'=>$redirectUri,'response_type'=>'code','scope'=>'openid email profile','access_type'=>'offline','prompt'=>'consent','state'=>$state]);
    }
}
