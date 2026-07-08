<?php
namespace App\Services;

final class PasswordResetService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function createToken(string $email, ?\DateTimeImmutable $now = null): ?string {
        $now ??= new \DateTimeImmutable();
        $users = $this->store->read('users');
        foreach ($users as $index => $user) {
            if (strcasecmp((string)($user['email'] ?? ''), $email) !== 0) continue;
            $token = bin2hex(random_bytes(32));
            $users[$index]['reset_token_hash'] = hash('sha256', $token);
            $users[$index]['reset_token_expires_at'] = $now->modify('+1 hour')->format(DATE_ATOM);
            $this->store->write('users', $users);
            return $token;
        }
        return null;
    }

    public function resetPassword(string $token, string $password, ?\DateTimeImmutable $now = null): bool {
        if ($token === '' || $password === '') return false;
        $now ??= new \DateTimeImmutable();
        $users = $this->store->read('users');
        $hash = hash('sha256', $token);
        foreach ($users as $index => $user) {
            if (($user['reset_token_hash'] ?? '') !== $hash) continue;
            $expires = new \DateTimeImmutable((string)($user['reset_token_expires_at'] ?? '1970-01-01'));
            if ($expires < $now) return false;
            $users[$index]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            unset($users[$index]['reset_token_hash'], $users[$index]['reset_token_expires_at']);
            $this->store->write('users', $users);
            return true;
        }
        return false;
    }
}
