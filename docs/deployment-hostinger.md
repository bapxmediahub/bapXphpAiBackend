# Deployment Guide - Hostinger

## Deployment Steps

1. Upload the current `main` build to `/public_html` with Git or FTP.
2. Keep `.env`, `index.php`, `.htaccess`, `api/`, `app/`, `assets/`, `integrations/`, `storage/`, `tools/`, and `views/` together.
3. Set `storage/` and `storage/data/` writable by PHP.
4. Configure integration secrets from the admin integrations page after deployment.
5. Set a Hostinger cron job for queued mail after SMTP is configured:

```bash
php /home/ACCOUNT/public_html/tools/process-mail-queue.php
```

6. Smoke test public pages, account redirects, admin login, API endpoints, checkout configuration, and the mail queue.

## Hostinger Website Management Dashboard Git Auto Deployment

Hostinger hPanel supports Git deployment from GitHub using OAuth. In the Hostinger website management dashboard, open the website, go to **Advanced** -> **Git**, connect the GitHub repository, choose the branch to deploy, and set the install path to `/public_html` or leave it blank when Hostinger maps the repository to the account root. Use `main` for production unless you intentionally host a staging branch.

After the repository is connected, enable **Auto Deployment** for the selected Branch. Hostinger gives a webhook URL for automatic deployments, and updates merged into the deployment branch can trigger a new deploy. Ensure the MySQL database is backed up before deploys because all runtime data lives in MySQL tables.

The root `.env` file holds only `APP_NAME`, `APP_URL`, and `BAPX_MYSQL_*` connection credentials. Commit branch-specific values so a fresh Hostinger deploy can serve the site. **Never put secrets in `.env`.** Admin credentials are set through **Admin → Settings**. All API secrets (Razorpay, SMTP, Google OAuth, support bot) are set through **Admin → Integrations** and stored in the MySQL `secrets` table — never in `.env`. Keep generated runtime files out of Git: logs, backups, and session files remain host-local.

Recommended branch setup:

- `main`: production branch connected to Hostinger auto deploy.
- `codex/*` or feature branches: local/agent development branches.
- Merge only after local validation passes.

Local validation before merge:

```bash
php tests/run.php
php tools/generate-project-map.php
php tools/validate-project-map.php
php tools/smoke-local.php
```

## Vercel

This application is built for normal PHP hosting, not Vercel. Vercel's official platform is oriented around static output and serverless functions; PHP requires a community runtime such as `vercel-php`, which is not the target architecture for this JSON-backed `public_html` app. Use Hostinger or another PHP host for production.

## Hostinger Requirements

- PHP 8.0 or newer.
- `mod_rewrite` enabled.
- Writable `storage/` directory.
- OpenSSL and cURL PHP extensions for encrypted settings and payment/OAuth calls.

## Architecture Notes

- Frontend: PHP-rendered templates.
- Backend: PHP controllers, services, and JSON API endpoints.
- Database: MySQL (via `DatabaseService`).
- Build step: none.
- Email: queued in MySQL `mail_queue` table and sent by `tools/process-mail-queue.php` when SMTP secrets are configured.

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
  tools/
  views/
```

## Troubleshooting

- 500 error: check PHP version, `.htaccess`, and PHP error logs.
- Data not saving: check MySQL `secrets` table and `DatabaseService` connection.
- Admin blocked: confirm the existing admin user in the MySQL `users` table has `role: "admin"`.
- Razorpay disabled: add live key ID and secret in admin integrations.
- Google login not working: verify the Google Cloud Console has the correct callback URL (`https://sripanchamispiritual.com/auth/google/callback`).
- Emails not sending: configure SMTP secrets and run `tools/process-mail-queue.php` from cron.
