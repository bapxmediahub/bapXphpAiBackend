---
type: doc
title: Google OAuth Module
description: Owns optional customer sign-in through Google.
category: module
---
# Google OAuth Module

Owns optional customer sign-in through Google.

Main files: `GoogleOAuthClient.php`, `AuthController.php`, `views/admin/integrations.php`, `views/public/login.php`, `views/public/register.php`.

## Configuration

1. Go to **Admin → Integrations** and fill in:
   - **Google Client ID**
   - **Google Client Secret**

2. In the Google Cloud Console, add these Authorized redirect URIs to your OAuth 2.0 Client ID:
   - `https://sripanchamispiritual.com/auth/google/callback` (production)
   - `http://127.0.0.1:6020/auth/google/callback` (local dev)

3. The button appears on both /login and /register pages only when credentials are set. Missing credentials hide the button entirely — no dead click targets.

## Behavior

- Login uses sign-in scopes only (`openid email profile`), no Calendar/Meet permissions.
- After authentication, users are redirected based on their role:
  - **admin** → `/admin`
  - Historical service records are owner-only; public consultation booking is retired.
  - **customer** → `/account/dashboard`
- New Google-authenticated users are upserted into the MySQL `users` collection via `DatabaseService` with role `customer`.
- Existing users (matched by email) retain their previously assigned role.

## New-account welcome versus returning sign-in

After a new customer is successfully persisted, a provider-verified email may
receive the same deduplicated welcome used by manual registration. Matching an
existing account or signing in as admin does not send a customer welcome. Delivery
uses the verified customer's own email, not the configured owner notification
address. The signup identity prevents repeated event calls producing duplicate
welcomes; SMTP acceptance still needs separate receiving-mailbox confirmation.
No login scopes, database connection or stored recipient configuration changes
are required for this event parity.
