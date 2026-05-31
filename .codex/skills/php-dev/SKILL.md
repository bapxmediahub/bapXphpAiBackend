---
name: php-dev
description: Use this skill set when contributing to this PHP/JSON agent-ready monorepo. It covers backend JSON primitives, schema, admin UI, PHP frontend, deployment, and docs.
---

# PHP Dev

Use this skill set for work inside this repository.

Choose the narrowest child skill:

- `backend-json`
- `schema`
- `admin-ui`
- `frontend-php`
- `deployment`
- `docs`

## Core Rules

1. Treat the repo as a full-stack PHP/JSON product base.
2. Keep JSON storage and schema readable to agents.
3. Use services for backend behavior and templates for UI.
4. Keep admin pages as the owner control plane.
5. Validate with focused PHP checks and browser workflow checks.
6. Keep the skill guidance reusable across projects assembled on this backend; do not hard-code one customer's business model into the skills.
7. When a code change creates a durable workflow rule, update the narrow child skill so future agents follow it without repeating the same discovery.
8. Before finishing, search the changed workflow for placeholders, dead buttons, stale labels, duplicated fallbacks, and incomplete wiring.

## Finish Rules

- State what changed.
- State which workflow was tested.
- State any remaining gap honestly.
