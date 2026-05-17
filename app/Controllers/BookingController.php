<?php
namespace App\Controllers;
use App\Services\{ResourceService,AvailabilityService,CalendarService};
final class BookingController extends BaseController {
 public function book(): void { $data=$_POST; $data['id']=bin2hex(random_bytes(8)); $data['status']=($data['mode']??'in-person')==='remote'?'payment_pending':'confirmed'; (new ResourceService('appointments'))->save($data); $this->flash('Appointment request saved.'); $this->redirect('/account/bookings'); }
}
