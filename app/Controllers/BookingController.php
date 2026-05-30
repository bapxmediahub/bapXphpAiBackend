<?php
namespace App\Controllers;
use App\Services\{AuthService,ResourceService,AstrologerService};
final class BookingController extends BaseController {
 public function book(): void {
  (new AuthService())->requireUser();
  $data = $_POST;
  $astrologer = (new AstrologerService())->findBySlug($data['astrologer_slug'] ?? '');
  if (!$astrologer) {
    $this->flash('Astrologer not found.');
    $this->redirect('/astrologers');
  }
  $user = $_SESSION['user'] ?? [];
  $data['customer_name'] = trim($data['customer_name'] ?? $user['name'] ?? '');
  $data['customer_email'] = trim($data['customer_email'] ?? $user['email'] ?? '');
  if (empty($data['customer_name']) || empty($data['customer_email'])) {
    $this->flash('Please provide your name and email to book this appointment.');
    $this->redirect('/astrologers/' . ($astrologer['slug'] ?? ''));
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
  $data['credits_spent'] = 0;
  $data['status'] = ($data['queue_status'] ?? '') === 'waitlist' ? 'waitlist' : 'payment_pending';
  $data['created_at'] = date('c');
  $data = array_filter($data, fn($v) => $v !== '');
  (new ResourceService('appointments'))->save($data);
  $this->flash(($data['status'] === 'waitlist' ? 'Waitlist request saved. ' : 'Session request saved. ') . 'Complete payment to start your session.');
  $this->redirect('/account/bookings');
 }
}
