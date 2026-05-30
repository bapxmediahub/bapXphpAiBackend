# Deployment Guide - Hostinger

## Deployment Steps

1. Upload the current `main` build to `/public_html` with Git or FTP.
2. Keep `index.php`, `.htaccess`, `api/`, `app/`, `assets/`, `integrations/`, `storage/`, `tools/`, and `views/` together.
3. Set `storage/` and `storage/data/` writable by PHP.
4. Configure integration secrets from the admin integrations page after deployment.
5. Set a Hostinger cron job for queued mail after SMTP is configured:

```bash
php /home/ACCOUNT/public_html/tools/process-mail-queue.php
```

6. Smoke test public pages, account redirects, admin login, API endpoints, checkout configuration, and the mail queue.

## Hostinger Requirements

- PHP 8.0 or newer.
- `mod_rewrite` enabled.
- Writable `storage/` directory.
- OpenSSL and cURL PHP extensions for encrypted settings and payment/OAuth calls.

## Architecture Notes

- Frontend: PHP-rendered templates plus vanilla JavaScript assets.
- Backend: PHP controllers, services, and JSON API endpoints.
- Database: JSON files in `storage/data/`.
- Build step: none.
- Email: queued in JSON and sent by `tools/process-mail-queue.php` when SMTP secrets are configured.

## Directory Structure on Hostinger

```text
/public_html/
  .htaccess
  index.php
  api/
  app/
  assets/
  docs/
  integrations/
  storage/
    data/
  tools/
  views/
```

## Troubleshooting

- 500 error: check PHP version, `.htaccess`, and PHP error logs.
- Data not saving: check `storage/data/` permissions.
- Admin blocked: confirm the existing admin user in `storage/data/users.json` has `role: "admin"`.
- Razorpay disabled: add live key ID and secret in admin integrations.
- Google login disabled: add Google OAuth client ID, secret, and callback URL.
- Emails not sending: configure SMTP secrets and run `tools/process-mail-queue.php` from cron.
