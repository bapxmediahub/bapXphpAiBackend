<?php
namespace App\Services;

final class AuthService {
    public static function validPhone(string $phone): bool {
        if (!preg_match('/^\+?[0-9 ()-]{7,25}$/D', $phone)) return false;
        $digits = strlen(preg_replace('/\D/', '', $phone));
        return $digits >= 7 && $digits <= 15;
    }

    /** Match only the actual configured owner identity; password verification remains mandatory. */
    public static function matchesAdminIdentifier(array $admin,string $identifier): bool {
        $identifier=trim($identifier);
        if($identifier==='')return false;
        foreach(['email','username'] as $field){
            $stored=$admin[$field]??null;
            if(is_string($stored)&&$stored!==''&&strcasecmp($stored,$identifier)===0)return true;
        }
        return false;
    }

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
