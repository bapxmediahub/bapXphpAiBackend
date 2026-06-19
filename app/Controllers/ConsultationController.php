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
    }
    public function room(string $id): void {
        $session = $this->session($id);
        $this->render('account/consultation', ['session'=>$session, 'messages'=>$this->consultations->messages($id), 'currentUser'=>$this->user]);
    }
    public function messages(string $id): void { $this->session($id); $this->jsonResponse(['messages'=>$this->consultations->messages($id, (string)($_GET['after'] ?? ''))]); }
    public function sendMessage(string $id): void {
        $session = $this->session($id); $input = $this->input();
        try { $message=$this->consultations->sendMessage($session,$this->user,(string)($input['body'] ?? '')); $this->jsonResponse(['message'=>$message],201); }
        catch (\InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage()],422); }
    }
    public function signals(string $id): void {
        $this->session($id); $signals=$this->consultations->signals($id,(string)($_GET['after'] ?? ''));
        $signals=array_values(array_filter($signals,fn($row)=>($row['sender_id']??'')!==($this->user['sub']??'')));
        $this->jsonResponse(['signals'=>$signals]);
    }
    public function sendSignal(string $id): void {
        $session=$this->session($id); $input=$this->input();
        try { $signal=$this->consultations->sendSignal($session,$this->user,(string)($input['type']??''),(array)($input['payload']??[])); $this->jsonResponse(['signal'=>$signal],201); }
        catch (\InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage()],422); }
    }
    public function status(string $id): void {
        $session=$this->session($id);
        if (($this->user['role']??'')!=='astrologer' && ($this->user['role']??'')!=='admin') $this->jsonResponse(['error'=>'Astrologer access required.'],403);
        try { $updated=$this->consultations->updateStatus($session,(string)($this->input()['status']??'')); $this->jsonResponse(['session'=>$updated]); }
        catch (\InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage()],422); }
    }
    private function session(string $id): array { $session=$this->consultations->findAccessible($id,$this->user); if(!$session)$this->jsonResponse(['error'=>'Session not found.'],404); return $session; }
    private function input(): array { $json=json_decode((string)file_get_contents('php://input'),true); return is_array($json)?$json:$_POST; }
}
