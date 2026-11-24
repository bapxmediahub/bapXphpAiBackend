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

## Customer mail events

Successful manual registration and first-time persisted Google customer creation
share `MailQueueService::enqueueWelcome` with a stable per-account signup identity.
Google welcome additionally requires the provider's verified-email flag. Existing
customer sign-ins do not resend a welcome or produce a routine login email.
The configured owner mailbox is not the customer recipient. No welcome backfill
is run when this code is deployed.

Public manual registration and password-recovery requests reserve independent atomic IP, recipient (case-normalized) and site-wide budgets before any account or reset-token mutation. Both use a 15-minute recipient cooldown; a signup flood cannot consume the recovery budget. Corrupt/unavailable admission state fails closed. A denied recovery does not rotate the active token; the public response is generic and never promises delivery. Google OAuth verified new-account welcome is unchanged.

Password recovery emails its one-time link; successful recovery completion and
successful My Profile password changes use the shared security template. Profile
changes notify only after the existing expected-hash compare-and-swap succeeds,
to the verified account record. A mail error does not undo the completed password
change. Rejected/stale attempts do not send. Never expose a reset token in preview
or alter a test customer's password to verify an email layout.
