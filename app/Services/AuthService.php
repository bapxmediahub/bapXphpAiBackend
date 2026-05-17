<?php
namespace App\Services;
final class AuthService { public function user(): ?array{return $_SESSION['user'] ?? null;} public function requireUser(): void{if(!$this->user()){header('Location: /login'); exit;}} }
