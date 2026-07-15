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
  - Consultant profiles do not authenticate; central admin coordinates appointments.
  - **customer** → `/` (home)
- New Google-authenticated users are upserted into `users.json` with role `customer`.
- Existing users (matched by email) retain their previously assigned role.
