---
title: Deployment Hostinger
description: Guide for Hostinger Git auto-deploy setup and production configuration.
category: docs
---

# Deployment Guide - Hostinger

## Deployment Steps

1. In Hostinger hPanel, open **Advanced → Git** and connect the GitHub repository to `/public_html`.
2. Enable **Auto Deployment** for the production **Branch** (`main`) so merged commits deploy automatically.
3. Upload the current `main` build to `/public_html` with Git or FTP when doing a manual recovery deploy.
4. Keep `.env`, `index.php`, `.htaccess`, `api/`, `app/`, `assets/`, `integrations/`, `storage/`, `cli/`, and `views/` together.
5. Configure `BAPX_MYSQL_HOST`, `BAPX_MYSQL_PORT`, `BAPX_MYSQL_DB`, `BAPX_MYSQL_USER`, and `BAPX_MYSQL_PASS` in `.env` for direct hosted MySQL access.
6. Configure application secrets from Admin -> Integrations; they are stored in remote MySQL, not `.env`.
7. Set a Hostinger cron job for queued mail after SMTP is configured:

```bash
php /home/ACCOUNT/public_html/cli/process-mail-queue.php
```

8. Smoke test public pages, account redirects, admin login, API endpoints, checkout configuration, and the mail queue.

One-time Git auto-deploy from `main` is configured — commits to GitHub main deploy automatically. Merge only after local validation passes:

```bash
bapXphp update
bapXphp ci
```

## Fork Synchronization

Upstream pushes to `getwinharris/bapXphpAiBackend:main` run `.github/workflows/notify-fork.yml`. The workflow sends an `upstream-main-updated` repository dispatch to `bapxmediahub/bapXphpAiBackend`, whose `sync-upstream.yml` workflow calls GitHub's supported `merge-upstream` API. Store a fine-grained token as the upstream Actions secret `FORK_SYNC_TOKEN`; it needs access only to the downstream repository with Contents write permission. Keep manual dispatch enabled for recovery. Do not add a scheduled polling fallback.

## Production Logs

Production operational history belongs in remote MySQL `audit_events`, visible in Admin -> Audit Log and through `bapXphp logs`. Local `server.log`, `storage/logs/`, and `output/playwright/` are ignored development/runtime artifacts and must never be committed. Use `bapXphp logs --local` only when diagnosing the local PHP server. Do not auto-commit hosted request or error logs: they may contain customer data and each log commit would retrigger deployment.

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
- Database: direct hosted MySQL via `.env`, with `APP_URL/remotedb` as the developer/agent fallback.
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
- Data not saving: run `bapXphp db status`, then verify the `BAPX_MYSQL_*` values and hosted database permissions.
- Admin blocked: verify the admin account in remote MySQL and the current Admin -> Settings configuration.
- Razorpay disabled: add live key ID and secret in admin integrations.
- Google login not working: verify the Google Cloud Console has the correct callback URL (`https://sripanchamispiritual.com/auth/google/callback`).
- Emails not sending: configure SMTP secrets and run `cli/process-mail-queue.php` from cron.
