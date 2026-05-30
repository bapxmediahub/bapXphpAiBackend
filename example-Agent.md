# Example Agent Workflow

Use this file as the operating note for Codex, Claude Code, Hermes-style agents, or any other coding agent working on this PHP/JSON project.

## Product Shape

- The app is a small PHP hosting product meant to run from `public_html`.
- The backend is PHP controllers and services.
- The data store is local JSON under `storage/data/`.
- The customer, account, and admin UI are PHP templates in `views/`.
- There is no SPA fallback. Unknown routes must return the PHP 404 page.

## Required Local Workflow

1. Read `README.md`, `docs/architecture.md`, and `docs/PROJECT_MAP.md`.
2. Use the project map before editing routes, controllers, services, or pages.
3. Run the local server when browser testing is needed:

```bash
php -S 127.0.0.1:6020 index.php
```

4. Test like a user in a browser for UI changes: navigate pages, click buttons, submit guarded forms, and confirm redirects.
5. Run validation before committing:

```bash
php tests/run.php
php tools/validate-project-map.php
php tools/smoke-local.php
```

6. Regenerate the project map after route/service changes:

```bash
php tools/generate-project-map.php
```

7. Commit only when validation passes. Commit to the branch the host deploys, usually `main`.

## Git Branch and CI/CD

- Use `main` as the production branch when Hostinger is connected to auto deployment.
- Use feature branches such as `codex/fix-checkout` or `codex/admin-settings` for local work when the change is risky.
- Merge into `main` only after tests and browser checks pass.
- Do not leave uncommitted deployment changes on the hosting server. The repository should be the source of truth except for runtime JSON data and environment secrets.

## Hostinger Setup

In Hostinger hPanel:

1. Open the website dashboard.
2. Go to **Advanced** -> **Git**.
3. Connect GitHub with OAuth.
4. Select the repository.
5. Select the deployment branch, normally `main`.
6. Use `/public_html` as the install path, or leave the install path blank if Hostinger maps the repository to the hosting root.
7. Enable **Auto Deployment** for that branch.
8. Keep the webhook URL Hostinger provides for future automation.

Before enabling auto deployment, edit `.env` on the host:

```dotenv
APP_URL=https://your-domain.example
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@your-domain.example
ADMIN_PASSWORD=ChangeThisAdmin123!
```

After deployment:

- Confirm `.htaccess` is present.
- Confirm `storage/` and `storage/data/` are writable by PHP.
- Log in to `/admin`.
- Change the admin email/password in Admin Settings.
- Configure Razorpay, Google OAuth, and SMTP in Admin Integrations.
- Set Hostinger cron for queued mail:

```bash
php /home/ACCOUNT/public_html/tools/process-mail-queue.php
```

## Vercel Note

Do not use Vercel as the primary production host for this project. The app is built for a persistent PHP host with writable local JSON storage. Vercel PHP support depends on a community runtime and serverless behavior, which does not match this `public_html` JSON-storage architecture.

## Things Agents Must Not Reintroduce

- Do not add React, CDN React, or a second frontend.
- Do not add a SPA fallback.
- Do not route unknown pages to a fake app shell.
- Do not add placeholder pages or "coming soon" flows.
- Do not replace JSON storage with SQL unless the user explicitly starts a separate migration.
- Do not make public registration create admin users.
