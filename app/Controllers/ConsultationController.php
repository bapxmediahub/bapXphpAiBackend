<?php
namespace App\Controllers;
use App\Services\{AuthService,ConsultationService};

final class ConsultationController extends BaseController {
    private ConsultationService $consultations;
    private array $user;
    public function __construct() {
        (new AuthService())->requireUser();
        $this->user = $_SESSION['user'] ?? [];
        $this->consultations = new ConsultationService();
        $this->seoKey = 'account';
    }
    public function status(string $id): void {
        // Session records are retained for the owner, but customers no longer have a
        // public consultation journey or customer-session surface.
        if (($this->user['role'] ?? '') !== 'admin') $this->jsonResponse(['error' => 'Not found.'], 404);
        $session=$this->session($id);
        $role=$this->user['role']??'';
        $status=(string)($this->input()['status']??'');
        try { $updated=$this->consultations->updateStatus($session,$status,$role); $this->jsonResponse(['session'=>$updated]); }
        catch (\InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage()],422); }
    }
    public function initiate(): void {
        (new AuthService())->requireUser();
        $this->validateCsrf();
        $this->flash('Consultation bookings are no longer available. Please send a general enquiry instead.', 'info');
        $this->redirect('/contact#contact-form');
    }

    private function session(string $id): array { $session=$this->consultations->findAccessible($id,$this->user); if(!$session)$this->jsonResponse(['error'=>'Session not found.'],404); return $session; }
    private function input(): array { $json=json_decode((string)file_get_contents('php://input'),true); return is_array($json)?$json:$_POST; }
}
