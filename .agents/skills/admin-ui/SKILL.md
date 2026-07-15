---
type: skill
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

## Admin Panel Agent (bapXcli)

The admin panel has an agent interface at `/admin/agent` that:

1. **Answers questions** about the site: user count, orders, revenue, products, consultations
2. **Creates/edits blog posts** via natural language prompts (delegates to `bapXphp write blog`)
3. **Reads MySQL data** through `DatabaseService` to answer queries
4. **Calls AI model** configured in Admin → Integrations (Google Gemini endpoint by default)
5. **Reads attachments** from `.agents/temp/` (screenshots, documents provided by the user)
6. **Triggers CI/CD** actions on the hosting server via `workflow_dispatch`

### Implementation Pattern

The agent controller:
1. Receives prompt via POST (JSON body with `prompt` field, optional `attachment` reference)
2. Builds context from MySQL: `DatabaseService` queries for users, orders, products, etc.
3. Calls AI API using SecretService (reads `ai_model_provider`, `ai_api_key`, `ai_model_name`, `ai_api_endpoint`)
4. Streams Markdown response back to the admin panel UI

### Agent Permissions

The admin agent has read access to:
- `users` — count, list
- `orders` — count, list, revenue totals
- `products` — list, details
- `astrologers` — list, details
- `appointments` — list, count
- `support_tickets` — list, count
- `audit_events` — recent events
- `settings` — public settings

Write/mutation operations require explicit user confirmation before execution.

## AI Integration Secrets

Configured in Admin → Integrations with these fields stored in MySQL `secrets` table:

| Key | Purpose |
|-----|---------|
| `ai_model_provider` | `"google"`, `"openai"`, or `"anthropic"` |
| `ai_model_name` | Model ID (e.g. `"gemini-2.5-flash"`) |
| `ai_api_endpoint` | Full API URL |
| `ai_api_key` | Authentication key |
