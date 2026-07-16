---
type: doc
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

The hosted shell requires `git` and PHP. It does **not** require GitHub CLI
(`gh`). hPanel auto-deploy performs the normal production pull. For manual
diagnosis or recovery:

```bash
git status --short --branch
git fetch origin
git pull --ff-only origin main
git rev-parse HEAD
```

Do not run issue, PR, review, or handoff conversations from Hostinger. Those
belong to GitHub Actions and the GitHub web interface.

One-time Git auto-deploy from `main` is configured — commits to GitHub main deploy automatically. Merge only after local validation passes:

```bash
bapXphp update
bapXphp ci
```

## Repository Architecture

This is a **white-label** product. The upstream source of truth is `getwinharris/bapXphpAiBackend`, forked to `bapxmediahub/bapXphpAiBackend` for customer deployment. Each customer gets their own fork with their branding, domain, and configuration. The product is not customer-specific — it's a reusable automation platform rebranded per client.

### Fork Synchronization

`.github/workflows/sync-upstream.yml` on the deployment fork (`bapxmediahub/bapXphpAiBackend`) runs **hourly** via cron (`0 * * * *`) plus supports `workflow_dispatch` and `repository_dispatch` as fallbacks. It calls GitHub's `merge-upstream` API using `github.token` (no `FORK_SYNC_TOKEN` secret needed). The workflow is guarded by `if: github.repository == 'bapxmediahub/bapXphpAiBackend'` so it only runs on the deployment fork.

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
