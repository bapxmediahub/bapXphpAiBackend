<?php
namespace App\Services;
final class CalendarService { public function remindersFor(string $start): array { $event = new \DateTimeImmutable($start); $previousEvening = $event->modify('-1 day')->setTime(18,0); return [($event->getTimestamp()-$previousEvening->getTimestamp())/60,60,0]; } }
