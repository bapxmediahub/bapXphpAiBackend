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

- Single unified login page (`/login`) serving admin and customer roles.
- Role-based redirect after authentication:
  - **admin** → `/admin`
  - **customer password sign-in** → `/account/dashboard`
- The configured admin identity is verified by `EnvService` against the canonical MySQL `app_settings` password hash; plain text is not accepted. Changes require the authorized settings workflow, not a model instruction.

## Google OAuth

- Button appears only when `google_client_id` and `google_client_secret` are configured in Admin → Integrations.
- Uses `openid email profile` scopes only (no Calendar/Meet).
- Callback redirects based on user role (same as loginPost).
- Redirect URI: `https://sripanchamispiritual.com/auth/google/callback` (production), `http://127.0.0.1:6020/auth/google/callback` (local dev).

## Registration

- Public registration creates `customer` role users only.
- Historical service records are owner-only; public consultation booking is retired.
- Private routes redirect guests to `/login`.

## Data Storage

- Registration signs the new customer in and returns to `/`; password sign-in has the separate dashboard redirect above.
- Users and addresses stored in MySQL via `DatabaseService` (not JSON files).
- Schema defined in `storage/schema/collections.php`.
