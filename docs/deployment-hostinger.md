# Deployment Guide - Hostinger

## Deployment Steps

1. Upload the current `main` build to `/public_html` with Git or FTP.
2. Keep `.env`, `index.php`, `.htaccess`, `api/`, `app/`, `assets/`, `integrations/`, `storage/`, `cli/`, and `views/` together.
3. Set `storage/` and `storage/data/` writable by PHP.
4. Configure integration secrets from the admin integrations page after deployment.
5. Set a Hostinger cron job for queued mail after SMTP is configured:

```bash
php /home/ACCOUNT/public_html/cli/process-mail-queue.php
```

6. Smoke test public pages, account redirects, admin login, API endpoints, checkout configuration, and the mail queue.

One-time Git auto-deploy from `main` is configured — commits to GitHub main deploy automatically. Merge only after local validation passes:

```bash
php tests/run.php
php cli/generate-project-map.php
php cli/validate-project-map.php
php cli/smoke-local.php
```

## Vercel

This application is built for normal PHP hosting, not Vercel. Vercel's official platform is oriented around static output and serverless functions; PHP requires a community runtime such as `vercel-php`, which is not the target architecture for this MySQL-backed `public_html` app. Use Hostinger or another PHP host for production.

## Hostinger Requirements

- PHP 8.0 or newer.
- `mod_rewrite` enabled.
- Writable `storage/` directory.
- OpenSSL and cURL PHP extensions for encrypted settings and payment/OAuth calls.

## Architecture Notes

- Frontend: PHP-rendered templates.
- Backend: PHP controllers, services, and JSON API endpoints.
- Database: MySQL tables via `DatabaseService`; JSON files only for one-time seeding.
- Build step: none.
- Email: queued in JSON and sent by `cli/process-mail-queue.php` when SMTP secrets are configured.

## Directory Structure on Hostinger

```text
/public_html/
  .env
  .htaccess
  index.php
  api/
  app/
  assets/
  docs/
  integrations/
  storage/
    data/
  cli/
  views/
```

## Troubleshooting

- 500 error: check PHP version, `.htaccess`, and PHP error logs.
- Data not saving: check `storage/data/` permissions.
- Admin blocked: confirm the existing admin user in `storage/data/users.json` has `role: "admin"`.
- Razorpay disabled: add live key ID and secret in admin integrations.
- Google login not working: verify the Google Cloud Console has the correct callback URL (`https://sripanchamispiritual.com/auth/google/callback`).
- Emails not sending: configure SMTP secrets and run `cli/process-mail-queue.php` from cron.
