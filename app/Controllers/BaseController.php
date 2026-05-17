<?php
namespace App\Controllers;
abstract class BaseController {
    protected function redirect(string $path): never { header('Location: ' . $path); exit; }
    protected function flash(string $message): void { $_SESSION['flash'] = $message; }
    protected function render(string $view, array $data = []): void {
        extract($data);
        $viewFile = app_path('views/' . $view . '.php');
        require app_path('views/layouts/app.php');
    }
}
