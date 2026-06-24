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
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $user = $this->user();
        if (!$user) {
            header('Location: /login');
            exit;
        }
        if (($user['role'] ?? '') !== 'admin' && empty($user['is_admin'])) {
            $_SESSION['flash'] = ['message' => 'Admin access required.', 'type' => 'warning'];
            header('Location: /');
            exit;
        }
    }

    public function requireAstrologer(): void {
        $user = $this->user();
        if (!$user) { header('Location: /login'); exit; }
        if (($user['role'] ?? '') !== 'astrologer') { $_SESSION['flash']=['message'=>'Astrologer access required.','type'=>'warning']; header('Location: /'); exit; }
    }
}
