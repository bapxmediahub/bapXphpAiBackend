<?php
namespace App\Integrations\GoogleCalendar;
final class GoogleCalendarClient { public function remoteAppointmentPayload(array $appointment): array{return ['summary'=>'Remote Astrology Consultation','attendees'=>[['email'=>$appointment['customer_email']],['email'=>$appointment['astrologer_email']]],'conferenceData'=>['createRequest'=>['requestId'=>$appointment['id']]],'reminders'=>['useDefault'=>false,'overrides'=>[['method'=>'email','minutes'=>$appointment['previous_evening_minutes']],['method'=>'email','minutes'=>60],['method'=>'popup','minutes'=>0]]]];} }
