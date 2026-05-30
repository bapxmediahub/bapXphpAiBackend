# Google OAuth Module

Owns optional customer sign-in through Google.

Main files: `GoogleOAuthClient.php`, `AuthController.php`, `views/admin/integrations.php`.

Key checks: login uses sign-in scopes only, Calendar/Meet permissions are not requested, and missing credentials show a clear message.
