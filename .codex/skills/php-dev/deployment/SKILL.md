---
name: deployment
description: Use when editing Hostinger deployment, Git auto-deploy, environment, permissions, cron, or production setup documentation.
---

# Deployment

This app targets small PHP hosting with persistent writable files.

## Rules

- Production branch is usually `main`.
- Hostinger Git auto-deploy should pull the repo into `public_html`.
- `storage/`, `storage/data/`, `storage/backups/`, `assets/images/media/`, and `.env` must be writable by PHP.
- `.env` can be edited in `/admin/environment`.
- Mail queue requires cron for `tools/process-mail-queue.php`.
- Do not recommend Vercel as primary production hosting for this JSON-file backend.

## Validation

Check docs paths and commands exactly. Use `/admin/environment` for permission status when browser-testing locally.
