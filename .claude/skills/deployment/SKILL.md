---
name: deployment
description: Use when editing Hostinger deployment, Git auto-deploy, environment, permissions, cron, or production setup documentation.
---
# Deployment

- Follow the root `AGENTS.md` repository contract for deployment documentation edits.
- Treat `bapxmediahub/bapXphpAiBackend` as the only agent working repository and deployment source. It is independent and unforked.
- Keep deployment guidance aligned with PHP shared hosting, `public_html`, writable `storage/`, and Git auto-deploy.
- `.env` is ignored, installation-specific configuration and is never deployed from Git. Keep safe placeholders in `.env.example`. Admin credentials and API secrets belong in Admin settings/integrations backed by hosted MySQL, never in tracked files.
- Do not add an upstream remote or fork-sync workflow. A future white-label package belongs in a separate repository.
- GitHub Action comments use the generic `github-actions[bot]` identity unless a registered GitHub App installation token is supplied. Configure `BAPXAI_APP_ID` and `BAPXAI_PRIVATE_KEY` for the `bapXai` App; upload `assets/images/bapXfavicon.png` as the App badge in GitHub settings.
- Read production operational history from remote MySQL `audit_events` with `bapXphp logs`. Never commit hosted logs, local `server.log`, or browser-test output to Git.
- Do not introduce Node build, SPA deployment, or serverless assumptions.
- Before committing, run `bapXphp update`. Before creating or merging a PR, run non-mutating `bapXphp ci`; it validates tests, both generated maps, and `cli/smoke-local.php`.

## Hosting Infrastructure

- **Host**: Hostinger shared hosting / VPS
- **Auto-deploy**: merge/push to deployment `main` → Hostinger Git integration pulls production
- **CI**: GitHub Actions (`bapXphp ci`) runs on push/PR to main
- **Database**: Remote MySQL (production), direct connection or `/remotedb` fallback
- **AI Model**: Configured in Admin → Integrations, stored in MySQL `secrets` table
- **Hosted tools**: plain `git` and PHP; GitHub CLI is not a Hostinger dependency

## CI/CD Pipeline

1. Developer / Agent pushes a branch to `bapxmediahub/bapXphpAiBackend`
2. The branch/PR workflow runs repository checks and may create/update an eligible PR according to `.github/workflows/branch-pr.yml`
3. Merge to deployment `main` after Reviewer evidence passes
4. The already-configured Hostinger Git integration pulls deployment `main`; this is
   a one-time hosting setup, not a per-release manual configuration step
5. Verify the deployed revision and live health; a merge alone is not deployment evidence.

## Testing

Confirm `./bapXphp ci` is green **before** merging to `main`. `main` is the Hostinger deployment source, so a red build can still reach production — check CI status explicitly rather than assuming.

## Keeping this skill current

Update when the host, deployment trigger, or required PHP version changes.
