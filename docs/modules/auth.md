---
type: doc
title: Auth Module
description: Owns login, registration, logout, Google OAuth, password reset, and admin session handling.
category: module
---
# Auth Module

Owns login, registration, logout, Google OAuth, password reset, and admin session handling.

Main files: `AuthController.php`, `AuthService.php`, `EnvService.php`, `PublicController.php`, public auth templates.

## Login

- Single unified login page (`/login`) serving all three roles: admin, astrologer, customer.
- Role-based redirect after authentication:
  - **admin** → `/admin`
  - Consultant profiles are not login accounts; central admin coordinates appointments.
  - **customer** → `/` (home)
- Admin credentials checked first from `settings.json` (admin_email/admin_password), then falls back to JSON `users` collection.

## Google OAuth

- Button appears only when `google_client_id` and `google_client_secret` are configured in Admin → Integrations.
- Uses `openid email profile` scopes only (no Calendar/Meet).
- Callback redirects based on user role (same as loginPost).
- Redirect URI: `https://sripanchamispiritual.com/auth/google/callback` (production), `http://127.0.0.1:6020/auth/google/callback` (local dev).

## Registration

- Public registration creates `customer` role users only.
- Astrologer accounts are admin-created, accept username login, and require a password change before workspace access.
- Private routes redirect guests to `/login`.
