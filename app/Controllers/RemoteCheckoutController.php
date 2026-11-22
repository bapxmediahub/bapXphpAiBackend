<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{RemoteCheckoutService,RemoteCartException};

/** Payment-only merchant continuation and signature-authenticated completion. */
final class RemoteCheckoutController
{
    public function __construct(private ?RemoteCheckoutService $checkout=null){}

    public function payment():void
    {
        header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow, noarchive');header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
        $payment=null;$error=null;
        try{$payment=($this->checkout??=new RemoteCheckoutService())->paymentHandoff(is_string($_GET['ticket']??null)?$_GET['ticket']:'');}
        catch(RemoteCartException $failure){http_response_code($failure->httpStatus);$error=$failure->getMessage();}
        // Capability URLs never load analytics, support widgets or provider scripts.
        require app_path('views/public/agent-payment.php');
    }

    public function webhook():void
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');echo '{"received":false}';return;}
        $body=file_get_contents('php://input',false,null,0,65537);$signature=$_SERVER['HTTP_X_RAZORPAY_SIGNATURE']??'';
        try {
            if(!is_string($body)||!is_string($signature))throw new RemoteCartException('invalid_notification','Invalid payment notification.');
            ($this->checkout??=new RemoteCheckoutService())->receiveWebhook($body,$signature);
            echo '{"received":true}';
        }catch(RemoteCartException $error){
            http_response_code($error->httpStatus);if($error->retryAfter>0)header('Retry-After: '.$error->retryAfter);
            echo '{"received":false}';
        }catch(\Throwable){http_response_code(503);header('Retry-After: 30');echo '{"received":false}';}
    }
}
