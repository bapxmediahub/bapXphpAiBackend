---
name: admin-ui
description: Use when editing owner/admin pages, CRUD forms, media library, environment editor, permissions, audit log, integrations, or admin navigation.
---

# Admin UI

- Follow the root `AGENTS.md` repository contract and its app/view/storage area rules.
- Keep owner/admin UI PHP-template based.
- Admin mutations should route through controllers/services and remain auditable.
- Use schema-driven resource fields and the media library for product, temple, and astrologer media.
- Keep astrologer accounts admin-created; show temporary credentials only until the provider changes the initial password.
- Validate with `php tests/run.php`; use a browser workflow for changed admin pages.
