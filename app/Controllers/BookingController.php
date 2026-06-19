<?php
namespace App\Controllers;
use App\Services\{AuthService,ResourceService,AstrologerService,WalletService};
final class BookingController extends BaseController {
 public function book(): void {
  (new AuthService())->requireUser();
  $data = $_POST;
  $astrologer = (new AstrologerService())->findBySlug($data['astrologer_slug'] ?? '');
  if (!$astrologer) {
    $this->flash('Astrologer not found.');
    $this->redirect('/consult');
  }
  $user = $_SESSION['user'] ?? [];
  $data['customer_name'] = trim($data['customer_name'] ?? $user['name'] ?? '');
  $data['customer_email'] = trim($data['customer_email'] ?? $user['email'] ?? '');
  if (empty($data['customer_name']) || empty($data['customer_email'])) {
    $this->flash('Please provide your name and email to start this remote session.');
    $this->redirect('/consult/' . ($astrologer['slug'] ?? ''));
  }
  $mode = in_array(($data['mode'] ?? 'direct_call'), ['text_session', 'direct_call'], true) ? $data['mode'] : 'direct_call';
  $data['id'] = bin2hex(random_bytes(8));
  $data['astrologer_name'] = $astrologer['name'] ?? '';
  $data['astrologer_email'] = $astrologer['email'] ?? '';
  $data['mode'] = $mode;
  $data['session_type'] = $mode === 'text_session' ? 'Message' : 'Call';
  $data['date'] = date('Y-m-d');
  $data['time'] = date('H:i');
  $data['credit_rate'] = $mode === 'text_session'
    ? (string)($astrologer['message_credit_cost'] ?? 5) . ' credits/message'
    : (string)($astrologer['call_credit_per_second'] ?? 0.5) . ' credits/sec';
  $initialCredits = $mode === 'text_session' ? (int)($astrologer['message_credit_cost'] ?? 5) : max(1, (int)ceil(((float)($astrologer['call_credit_per_second'] ?? 0.5)) * 60));
  $wallet = new WalletService();
  if (($data['queue_status'] ?? '') !== 'waitlist' && $wallet->balanceFor($data['customer_email']) < $initialCredits) {
    $this->flash('Please recharge your wallet to start this session.');
    $this->redirect('/recharge?amount=100');
  }
  $data['credits_spent'] = ($data['queue_status'] ?? '') === 'waitlist' ? 0 : $initialCredits;
  $data['status'] = ($data['queue_status'] ?? '') === 'waitlist' ? 'queued' : 'requested';
  $data['created_at'] = date('c');
  $data = array_filter($data, fn($v) => $v !== '');
  (new ResourceService('appointments'))->save($data);
  if ($data['status'] !== 'queued') {
    $wallet->spend($data['customer_email'], $initialCredits, $data['id'], $data['session_type'] . ' session with ' . ($data['astrologer_name'] ?? 'astrologer'));
    (new ResourceService('appointments'))->save($data);
  }
  $this->flash($data['status'] === 'queued' ? 'Waitlist request saved.' : 'Consultation request created and initial credits were deducted.');
  $this->redirect('/consultation/' . $data['id']);
 }
}
