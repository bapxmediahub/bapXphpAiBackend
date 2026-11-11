<?php
namespace App\Services;

final class PasswordResetService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function createToken(string $email, ?\DateTimeImmutable $now = null): ?string {
        $now ??= new \DateTimeImmutable();
        $users = $this->store->read('users');
        foreach ($users as $user) {
            if (strcasecmp((string)($user['email'] ?? ''), $email) !== 0) continue;
            $token = bin2hex(random_bytes(32));
            return $this->store->patchUser((string)$user['id'], [
                'reset_token_hash' => hash('sha256', $token),
                'reset_token_expires_at' => $now->modify('+1 hour')->format(DATE_ATOM),
            ]) ? $token : null;
        }
        return null;
    }

    /**
     * Email address a reset token belongs to. Needed to confirm the change to the
     * account holder after the token has been consumed and is no longer resolvable.
     */
    public function emailForToken(string $token): string {
        if ($token === '') return '';
        $hash = hash('sha256', $token);
        foreach ($this->store->read('users') as $user) {
            if (($user['reset_token_hash'] ?? '') === $hash) return (string)($user['email'] ?? '');
        }
        return '';
    }

    public function resetPassword(string $token, string $password, ?\DateTimeImmutable $now = null): bool {
        if ($token === '' || $password === '') return false;
        $now ??= new \DateTimeImmutable();
        $users = $this->store->read('users');
        $hash = hash('sha256', $token);
        foreach ($users as $user) {
            if (($user['reset_token_hash'] ?? '') !== $hash) continue;
            $expires = new \DateTimeImmutable((string)($user['reset_token_expires_at'] ?? '1970-01-01'));
            if ($expires < $now) return false;
            return $this->store->patchUser((string)$user['id'], self::passwordFields($password, $now), 'reset_token_hash', $hash);
        }
        return false;
    }

    public static function passwordFields(string $password, \DateTimeImmutable $now): array {
        return [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_changed_at' => $now->format(DATE_ATOM),
            'must_change_password' => false,
            'reset_token_hash' => null,
            'reset_token_expires_at' => null,
        ];
    }
}
