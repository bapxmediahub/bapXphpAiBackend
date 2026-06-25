---
name: deployment
description: Use when editing Hostinger deployment, Git auto-deploy, environment, permissions, cron, or production setup documentation.
---

# Deployment

- Follow root `AGENTS.md` and `docs/AGENTS.md` for deployment documentation edits.
- Keep deployment guidance aligned with PHP shared hosting, `public_html`, writable `storage/`, and Git auto-deploy.
- Treat the root `.env` as a deployable repo file for shared-hosting auto-deploy; keep generated runtime secret stores, lock files, logs, and backups ignored.
- Do not introduce Node build, SPA deployment, or serverless assumptions.
- Before committing or pushing to remote `main`, run `php tests/run.php`, regenerate/validate the map, and run `php tools/smoke-local.php`.
