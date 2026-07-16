---
type: skill
name: deployment
description: Use when editing Hostinger deployment, Git auto-deploy, environment, permissions, cron, or production setup documentation.
---
# Deployment

- Follow the root `AGENTS.md` repository contract for deployment documentation edits.
- Treat `bapxmediahub/bapXphpAiBackend` as the only agent working repository. The `getwinharris` remote is read-only upstream until the customer repository is unforked.
- Keep deployment guidance aligned with PHP shared hosting, `public_html`, writable `storage/`, and Git auto-deploy.
- Treat the root `.env` as a deployable repo file for shared-hosting auto-deploy (APP_NAME and APP_URL only). Never put secrets in `.env`. Admin credentials go through Admin → Settings. API secrets (Razorpay, SMTP, Google OAuth, support bot, AI model) go through Admin → Integrations and are stored in the remote MySQL `secrets` collection — never in `.env`. Keep generated runtime secret stores, lock files, logs, and backups ignored.
- Prefer upstream `push` -> authenticated `repository_dispatch` -> downstream `merge-upstream` for fork synchronization. Keep the hourly schedule only as a recovery fallback until the customer repository is unforked.
- Automatic fork sync requires a shared Git ancestry. If a fork was rewritten and has no merge base, create one reviewed history-bridge merge while preserving the verified downstream tree; never reset or force-push away customer commits.
- GitHub Action comments use the generic `github-actions[bot]` identity unless a registered GitHub App installation token is supplied. Configure `BAPXAI_APP_ID` and `BAPXAI_PRIVATE_KEY` for the `bapXai` App; upload `assets/images/bapXfavicon.png` as the App badge in GitHub settings.
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
- **Hosted tools**: plain `git` and PHP; GitHub CLI is not a Hostinger dependency

## CI/CD Pipeline

1. Developer / Agent pushes a branch to `bapxmediahub/bapXphpAiBackend`
2. GitHub Actions creates/updates the PR and runs `bapXphp ci`
3. Merge to deployment `main` after Reviewer evidence passes
4. Hostinger pulls deployment `main`
5. Read-only upstream changes enter only through `sync-upstream.yml` until unfork

## Sub-Agent Cloud Delegation

Coding agents work locally and publish branches with plain Git. GitHub Actions
owns issue, handoff, PR, and review events. On hosting, agents may use project
CLI commands for database, logs, mail, and browser diagnostics, but must not
depend on `gh`.
