---
name: deployment
description: Use when editing Hostinger deployment, Git auto-deploy, environment, permissions, cron, or production setup documentation.
---

# Deployment

- Follow the root `AGENTS.md` repository contract for deployment documentation edits.
- Keep deployment guidance aligned with PHP shared hosting, `public_html`, writable `storage/`, and Git auto-deploy.
- Treat the root `.env` as a deployable repo file for shared-hosting auto-deploy (APP_NAME and APP_URL only). Never put secrets in `.env`. Admin credentials go through Admin → Settings. API secrets (Razorpay, SMTP, Google OAuth, support bot) go through Admin → Integrations and are stored in the remote MySQL `secrets` collection — never in `.env`. Keep generated runtime secret stores, lock files, logs, and backups ignored.
- Use upstream `push` -> authenticated `repository_dispatch` -> downstream `merge-upstream` for fork synchronization. Do not use scheduled polling when event-driven dispatch is configured.
- Read production operational history from remote MySQL `audit_events` with `bapXphp logs`. Never commit hosted logs, local `server.log`, or browser-test output to Git.
- Do not introduce Node build, SPA deployment, or serverless assumptions.
- Before committing, run `bapXphp update`. Before creating or merging a PR, run non-mutating `bapXphp ci`; it validates tests, both generated maps, and `cli/smoke-local.php`.
