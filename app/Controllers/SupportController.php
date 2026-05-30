<?php
namespace App\Controllers;
use App\Services\{AuthService,SupportBotService};

final class SupportController extends BaseController {
    public function ask(): void {
        try {
            $user = (new AuthService())->user();
            $answer = (new SupportBotService())->answer($_POST['message'] ?? '', $user);
            $this->jsonResponse($answer);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Unable to answer right now. Please try again.'], 400);
        }
    }
}
