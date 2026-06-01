---
name: admin-ui
description: Use when editing owner/admin pages, CRUD forms, media library, environment editor, permissions, audit log, integrations, or admin navigation.
---

# Admin UI

## Scope

- `views/admin/**`
- `views/layouts/admin.php`
- `app/Controllers/AdminController.php`
- `assets/css/band.css`

## Rules

- Admin is the owner control plane for small hosting.
- CRUD forms must preserve existing JSON fields when only visible fields are edited.
- Media fields should use the media library picker and upload controls.
- Environment variables and storage permissions belong in `/admin/environment`.
- Every admin route must require `AuthService`.
- Keep UI dense, direct, and operational. Avoid marketing sections in admin.
- Admin controls must be real: no placeholder buttons, no disconnected upload fields, and no resource links that bypass authentication.
- When admin changes affect schema, media, environment, or permissions, update the matching backend/schema skill if the rule is reusable for future projects.

## Validation

Use the browser for changed admin workflows: login, open page, click buttons, submit guarded forms, verify redirect/result.
