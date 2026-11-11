<?php
namespace App\Services;

final class UserOrderHistoryService {
    public static function forUsers(array $users, array $orders): array {
        $emailKey = static fn($email): string => strtolower(trim((string)$email));
        $byEmail = [];
        foreach ($users as $user) {
            if ($email = $emailKey($user['email'] ?? '')) $byEmail[$email] = [];
        }
        $zone = new \DateTimeZone('Asia/Kolkata');
        foreach ($orders as $order) {
            $key = $emailKey($order['customer_email'] ?? '');
            if (!isset($byEmail[$key]) || empty($order['id'])) continue;
            $raw = (string)($order['created_at'] ?? '');
            $date = null;
            if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $raw)) {
                try { $date = (new \DateTimeImmutable($raw, $zone))->setTimezone($zone); }
                catch (\Exception) {}
            }
            $byEmail[$key][] = [
                'id' => (string)$order['id'], 'date' => $date,
                'status' => (string)($order['status'] ?? ''),
                'total' => $order['total'] ?? null,
                'invoice' => (string)($order['invoice_number'] ?? ''),
            ];
        }
        foreach ($byEmail as &$history) {
            usort($history, static fn($a, $b) => ($b['date']?->getTimestamp() ?? PHP_INT_MIN) <=> ($a['date']?->getTimestamp() ?? PHP_INT_MIN) ?: strcmp($b['id'], $a['id']));
        }
        unset($history);
        return array_map(static fn($user) => [
            'name' => (string)($user['name'] ?? ''), 'email' => (string)($user['email'] ?? ''),
            'role' => (string)($user['role'] ?? ''),
            'orders' => $byEmail[$emailKey($user['email'] ?? '')] ?? [],
        ], $users);
    }
}
