# Auth Module

Owns login, registration, logout, Google OAuth, password reset, and admin session handling.

Main files: `AuthController.php`, `AuthService.php`, `EnvService.php`, public auth templates.

Key checks: public registration creates customer users only; admin credentials can log in; astrologer accounts are admin-created, accept username login, and require a password change before workspace access; private routes redirect guests to `/login`.
