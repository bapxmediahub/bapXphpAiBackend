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

`bapxmediahub/bapXphpAiBackend` is the independent source repository and the
Hostinger deployment source. All issues, branches, PRs, handoffs, reviews, and
customer-specific changes belong there. It is no longer a fork and has no
upstream synchronization workflow.

A reusable white-label PHP/AI backend package should be published later as a
separate repository and product. Do not reconnect this deployment repository
to `getwinharris/bapXphpAiBackend`.

### bapXai GitHub App

The built-in `GITHUB_TOKEN` always posts as `github-actions[bot]`; workflow YAML
cannot change its avatar. To make `@bapXai` comments use the supplied bot logo:

1. Register a private GitHub App named `bapXai`.
2. Grant repository **Issues: read/write**, **Pull requests: read/write**, and
   **Contents: read/write** permissions.
3. Install it only on `bapxmediahub/bapXphpAiBackend`.
4. Upload `assets/images/bapXfavicon.png` as the App badge.
5. Add repository variable `BAPXAI_APP_ID`.
6. Add repository secret `BAPXAI_PRIVATE_KEY` containing the generated private
   key.

`.github/workflows/issue-comment-handoff.yml` then exchanges those credentials
for a short-lived installation token. Mentioning `@bapXai` on an issue routes
the issue title, description, triggering comment, and URL into the CTO handoff
and posts the acknowledgement as the installed App. Without these two
credentials, the workflow still runs but comments as `github-actions[bot]`.

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
- Email: sent immediately when generated, using the SMTP secrets from Admin → Integrations. No cron job is required. Use Admin → Integrations → Send a Test Email to verify.

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
- Emails not sending: check Admin → Integrations → Send a Test Email. It reports the exact SMTP error. For Hostinger use `smtp.hostinger.com`, port 465 (SSL) or 587 (TLS), the full email address as username, and a From Email matching that mailbox.
