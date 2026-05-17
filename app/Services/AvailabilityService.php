<?php
namespace App\Services;
final class AvailabilityService {
    public function slotsForDate(array $astrologer, string $date, array $appointments): array {
        $weekday = strtolower((new \DateTimeImmutable($date))->format('l'));
        if (!in_array($weekday, $astrologer['working_days'] ?? [], true)) return [];
        $start = new \DateTimeImmutable($date . ' ' . $astrologer['start_time']);
        $end = new \DateTimeImmutable($date . ' ' . $astrologer['end_time']);
        $minutes = (int)($astrologer['slot_minutes'] ?? 30);
        $booked = array_map(fn($a) => ($a['date'] ?? '') . ' ' . ($a['time'] ?? ''), $appointments);
        $slots = [];
        for ($cursor = $start; $cursor < $end; $cursor = $cursor->modify("+{$minutes} minutes")) {
            $key = $cursor->format('Y-m-d H:i');
            if (!in_array($key, $booked, true)) $slots[] = $cursor->format('H:i');
        }
        return $slots;
    }
}
