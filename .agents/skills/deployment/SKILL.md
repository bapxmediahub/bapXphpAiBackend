---
type: skill
name: deployment
description: Use when editing Hostinger deployment, Git auto-deploy, environment, permissions, cron, or production setup documentation.
---
# Deployment

- Follow the root `AGENTS.md` repository contract for deployment documentation edits.
- Keep deployment guidance aligned with PHP shared hosting, `public_html`, writable `storage/`, and Git auto-deploy.
- Treat the root `.env` as a deployable repo file for shared-hosting auto-deploy (APP_NAME and APP_URL only). Never put secrets in `.env`. Admin credentials go through Admin → Settings. API secrets (Razorpay, SMTP, Google OAuth, support bot, AI model) go through Admin → Integrations and are stored in the remote MySQL `secrets` collection — never in `.env`. Keep generated runtime secret stores, lock files, logs, and backups ignored.
- Use upstream `push` -> authenticated `repository_dispatch` -> downstream `merge-upstream` for fork synchronization. Do not use scheduled polling when event-driven dispatch is configured.
- Read production operational history from remote MySQL `audit_events` with `bapXphp logs`. Never commit hosted logs, local `server.log`, or browser-test output to Git.
- Do not introduce Node build, SPA deployment, or serverless assumptions.
- Before committing, run `bapXphp update`. Before creating or merging a PR, run non-mutating `bapXphp ci`; it validates tests, both generated maps, and `cli/smoke-local.php`.

## Hosting Infrastructure

- **Host**: Hostinger shared hosting / VPS
- **Auto-deploy**: Git push → webhook → production `git pull`
- **CI**: GitHub Actions (`bapXphp ci`) runs on push/PR to main
- **Database**: Remote MySQL (production), direct connection or `/remotedb` fallback
- **AI Model**: Configured in Admin → Integrations, stored in MySQL `secrets` table
- **Agent sub-delegation**: Sub-agents can trigger `workflow_dispatch` on GitHub Actions for long-running deployment tasks

## CI/CD Pipeline

1. Developer / Agent pushes to `main`
2. GitHub Actions runs `bapXphp ci` (lint → test → map validation → smoke)
3. If passing, production webhook pulls the new code
4. Fork sync dispatches upstream-main-updated to downstream
5. Recovery: `gh repo sync` only if event-driven sync fails

## Sub-Agent Cloud Delegation

Coding agents (OpenCode, Claude Code, Codex) acting as CTO can:
1. Trigger GitHub Actions `workflow_dispatch` from the hosting server
2. SSH into hosting for direct database inspection (via `bapXphp db hosted` credentials)
3. Read production audit logs via `bapXphp logs`
4. Query production MySQL via `/remotedb` endpoint
