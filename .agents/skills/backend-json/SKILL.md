---
name: backend-json
description: Use when editing PHP controllers, services, JSON persistence, auth, support assistant context, wallet, orders, reviews, media, or audit behavior.
---

# Backend JSON

- Follow root `AGENTS.md`, then the nearest child `AGENTS.md` for every touched path.
- Keep route -> controller -> service -> JSON-store boundaries.
- Use `JsonStoreService`, `ResourceService`, and existing services instead of ad hoc storage writes.
- Keep assistant/customer context filtered through `AgentContextService` or equivalent user-specific filtering.
- Validate changed PHP with `php -l`, then run `php tests/run.php`.
