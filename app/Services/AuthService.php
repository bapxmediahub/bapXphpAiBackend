<?php
namespace App\Services;

final class AuthService {
    public function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public function requireUser(): void {
        if (!$this->user()) {
            header('Location: /login');
            exit;
        }
    }

    public function requireAdmin(): void {
        $user = $this->user();
        if (!$user) {
            header('Location: /login');
            exit;
        }
        if (($user['role'] ?? '') !== 'admin' && empty($user['is_admin'])) {
            $_SESSION['flash'] = 'Admin access required.';
            header('Location: /');
            exit;
        }
    }
}
