<?php
namespace App\Services;

final class ConsultationService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function sessionsFor(array $user): array {
        $items = $this->store->read('appointments');
        if (($user['role'] ?? '') === 'admin') return $items;
        if (($user['role'] ?? '') === 'astrologer') {
            return array_values(array_filter($items, fn($row) => ($row['astrologer_slug'] ?? '') === ($user['astrologer_slug'] ?? '')));
        }
        return array_values(array_filter($items, fn($row) => strcasecmp((string)($row['customer_email'] ?? ''), (string)($user['email'] ?? '')) === 0));
    }

    public function findAccessible(string $id, array $user): ?array {
        foreach ($this->sessionsFor($user) as $row) if (($row['id'] ?? '') === $id) return $row;
        return null;
    }

    public function messages(string $appointmentId, string $after = ''): array {
        return array_values(array_filter($this->store->read('consultation_messages'), function($row) use ($appointmentId, $after) {
            return ($row['appointment_id'] ?? '') === $appointmentId && ($after === '' || strcmp((string)($row['created_at'] ?? ''), $after) > 0);
        }));
    }

    public function sendMessage(array $session, array $user, string $body): array {
        $mode = (string)($session['mode'] ?? '');
        $status = (string)($session['status'] ?? 'requested');
        if ($mode === 'text_session' && !in_array($status, ['accepted', 'active'], true)) throw new \InvalidArgumentException('Messaging becomes available after the astrologer accepts the session.');
        if ($mode === 'direct_call' && $status !== 'active') throw new \InvalidArgumentException('Call chat becomes available after the astrologer starts the session.');
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) throw new \InvalidArgumentException('Message must contain 1 to 2000 characters.');
        $message = [
            'id' => bin2hex(random_bytes(8)),
            'appointment_id' => $session['id'],
            'sender_id' => $user['sub'] ?? '',
            'sender_name' => $user['name'] ?? '',
            'sender_role' => $user['role'] ?? 'customer',
            'body' => $body,
            'created_at' => date('c'),
            'read_at' => null,
        ];
        $this->store->upsert('consultation_messages', $message);
        $this->touch($session['id']);
        return $message;
    }

    public function signals(string $appointmentId, string $after = '', string $afterId = ''): array {
        $sql = "SELECT * FROM consultation_signals WHERE JSON_UNQUOTE(JSON_EXTRACT(_data, '$.appointment_id')) = ? AND (_created_at > ? OR (_created_at = ? AND id > ?) OR ? = '') ORDER BY _created_at ASC, id ASC";
        $rows = $this->store->query($sql, [$appointmentId, $after, $after, $afterId, $after]);
        $results = array_map(fn($r) => array_merge(json_decode($r['_data'] ?? '{}', true) ?: [], ['id' => $r['id']]), $rows);
        return $results;
    }

    public function sendSignal(array $session, array $user, string $type, array $payload): array {
        if (($session['mode'] ?? '') !== 'direct_call' || ($session['status'] ?? '') !== 'active') throw new \InvalidArgumentException('Calls are available only during an active call session.');
        if (!in_array($type, ['offer','answer','ice','hangup'], true)) throw new \InvalidArgumentException('Invalid call signal.');
        $signal = ['id'=>bin2hex(random_bytes(8)), 'appointment_id'=>$session['id'], 'sender_id'=>$user['sub'] ?? '', 'type'=>$type, 'payload'=>$payload, 'created_at'=>date('c')];
        $this->store->upsert('consultation_signals', $signal);
        $this->touch($session['id']);
        return $signal;
    }

    public function updateStatus(array $session, string $status, string $actorRole = 'astrologer'): array {
        if (!in_array($status, ['accepted','active','completed','declined','cancelled'], true)) throw new \InvalidArgumentException('Invalid session status.');
        $current = (string)($session['status'] ?? 'requested');
        $allowed = [
            'queued' => ['accepted', 'declined', 'cancelled'],
            'requested' => ['accepted', 'declined', 'cancelled'],
            'accepted' => ['active', 'cancelled'],
            'active' => ['completed'],
        ];
        if (!in_array($status, $allowed[$current] ?? [], true)) throw new \InvalidArgumentException('Invalid session status transition.');
        if ($actorRole === 'customer' && $status !== 'cancelled') throw new \InvalidArgumentException('Customer can only cancel a pending session.');
        $now = date('c');
        $session['status'] = $status;
        $session['last_activity_at'] = $now;
        if ($status === 'accepted') $session['accepted_at'] = $now;
        if ($status === 'active') $session['started_at'] ??= $now;
        if ($status === 'completed') {
            $session['ended_at'] = $now;
            if (!empty($session['started_at'])) $session['duration_seconds'] = max(0, time() - strtotime($session['started_at']));
            $signals = array_values(array_filter($this->store->read('consultation_signals'), fn($row) => ($row['appointment_id'] ?? '') !== ($session['id'] ?? '')));
            $this->store->write('consultation_signals', $signals);
        }
        return (new ResourceService('appointments'))->save($session);
    }

    public function analytics(): array {
        $sessions = $this->store->read('appointments');
        $messages = $this->store->read('consultation_messages');
        $completed = array_filter($sessions, fn($row) => in_array(($row['status'] ?? ''), ['completed','session_ended'], true));
        $accepted = array_filter($sessions, fn($row) => in_array(($row['status'] ?? ''), ['accepted','active','completed','session_started','session_ended'], true));
        $durations = array_map(fn($row) => (int)($row['duration_seconds'] ?? 0), $completed);
        $responseTimes = [];
        foreach ($sessions as $row) if (!empty($row['created_at']) && !empty($row['accepted_at'])) $responseTimes[] = max(0, strtotime($row['accepted_at']) - strtotime($row['created_at']));
        return [
            'total_sessions'=>count($sessions), 'accepted_sessions'=>count($accepted), 'completed_sessions'=>count($completed),
            'message_count'=>count($messages), 'credits_spent'=>array_sum(array_map(fn($row)=>(int)($row['credits_spent'] ?? $row['spent_credits'] ?? 0), $sessions)),
            'average_duration_seconds'=>$durations ? (int)round(array_sum($durations)/count($durations)) : 0,
            'average_response_seconds'=>$responseTimes ? (int)round(array_sum($responseTimes)/count($responseTimes)) : 0,
        ];
    }

    private function touch(string $id): void {
        foreach ($this->store->read('appointments') as $row) if (($row['id'] ?? '') === $id) { $row['last_activity_at'] = date('c'); (new ResourceService('appointments'))->save($row); break; }
    }
}
