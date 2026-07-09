<?php
namespace App\Controllers;
use App\Services\{AuthService,SupportBotService};

final class SupportController extends BaseController {
    public function page(): void {
        $this->seoKey = 'support';
        $this->render('public/support', []);
    }

    public function ask(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = new \App\Services\RateLimiter();
        if (!$limiter->check('support:' . $ip, 10, 60)) {
            $this->jsonResponse(['error' => 'Too many requests. Please try again later.'], 429);
            return;
        }
        $limiter->hit('support:' . $ip);
        try {
            $user = (new AuthService())->user();
            $answer = (new SupportBotService())->answer($_POST['message'] ?? '', $user);
            $this->jsonResponse($answer);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Unable to answer right now. Please try again.'], 400);
        }
    }
}
