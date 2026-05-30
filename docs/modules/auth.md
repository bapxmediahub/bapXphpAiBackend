# Auth Module

Owns login, registration, logout, Google OAuth, password reset, and admin session handling.

Main files: `AuthController.php`, `AuthService.php`, `EnvService.php`, public auth templates.

Key checks: public registration creates customer users only; `.env` admin credentials can log in; private routes redirect guests to `/login`.
