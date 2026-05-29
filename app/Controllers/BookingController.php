<?php
namespace App\Controllers;
use App\Services\{ResourceService,AvailabilityService,AstrologerService,AppointmentService};
final class BookingController extends BaseController {
 public function book(): void {
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
  $appointments = (new AppointmentService())->all();
  $slots = (new AvailabilityService())->slotsForDate($astrologer, $data['date'] ?? date('Y-m-d'), $appointments);
  if (!in_array($data['time'] ?? '', $slots, true)) {
    $this->flash('Selected slot is no longer available. Please choose another slot.');
    $this->redirect('/astrologers/' . ($astrologer['slug'] ?? ''));
  }
  $data['id'] = bin2hex(random_bytes(8));
  $data['astrologer_name'] = $astrologer['name'] ?? '';
  $data['astrologer_email'] = $astrologer['email'] ?? '';
  $data['status'] = in_array(($data['mode'] ?? 'direct_call'), ['text_session', 'direct_call'], true) ? 'payment_pending' : 'confirmed';
  $data['created_at'] = date('c');
  $data = array_filter($data, fn($v) => $v !== '');
  (new ResourceService('appointments'))->save($data);
  $this->flash('Session request saved. ' . ($data['status'] === 'payment_pending' ? 'Complete payment to start your session.' : 'Your appointment is confirmed.'));
  $this->redirect('/account/bookings');
 }
}
