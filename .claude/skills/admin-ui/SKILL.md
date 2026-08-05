---
name: admin-ui
description: Use when editing owner/admin pages, CRUD forms, media library, environment editor, permissions, audit log, integrations, or admin navigation.
---
# Admin UI

- Follow the root `AGENTS.md` repository contract and its app/view/storage area rules.
- Keep owner/admin UI PHP-template based.
- Admin mutations should route through controllers/services and remain auditable.
- Use schema-driven resource fields and the media library for product, temple, and astrologer media.
- Treat consultants/astrologers as admin-managed public profiles, not application login accounts. Do not create or display provider credentials unless authentication is explicitly added as a separate product change.
- Validate with `php tests/run.php`; use a browser workflow for changed admin pages.

## Admin Panel Agent

The interface at `/admin/agent` sends a prompt plus a bounded site summary to the
configured AI endpoint. Treat it as a read-only assistant unless controller code
explicitly implements and authorizes a mutation. It does not currently edit blogs,
run the CLI, or trigger deployments.

### Implementation Pattern

The admin-agent flow:
1. `GET /admin/agent` calls `AdminController::agent()` and renders the page.
2. `POST /admin/agent/ask` calls `AdminController::agentAsk()`, which builds aggregate counts directly with
   `DatabaseService`; it does not use `AgentContextService`.
3. `SecretService` supplies `agent_api_key`, `agent_model`, and `api_endpoint`.
4. The controller returns JSON; JavaScript in `views/admin/agent.php` renders the response.

### Agent Permissions

Keep context aggregate, allowlisted and purpose-specific. The current top-customer
email summary is a privacy gap and must be removed or anonymized; do not describe it
as safe merely because the route is admin-only. Do not expose passwords, secrets,
full user lists, payment data, or unrelated customer records. A future write
operation needs an explicit controller/service contract, authorization, confirmation,
CSRF protection and audit event; a prompt alone is never authorization.

## AI Integration Secrets

Configured in Admin → Integrations with these fields stored in MySQL `secrets` table:

| Key | Purpose |
|-----|---------|
| `agent_api_key` | Authentication key |
| `agent_model` | Model ID; defaults to `gemma-4-31b-it` |
| `api_endpoint` | Full provider API URL |

## Testing

Log into `/admin` and click through the page you changed, including its form submit and delete paths. Admin forms post several independent forms to one action, so confirm a partial submit does not blank the fields belonging to the other forms. Then `./bapXphp test`.

## Keeping this skill current

Update when an admin route, sidebar group, or CRUD form contract changes. The sidebar is gated by `SettingsService::modules()`; re-check both the on and off states.
