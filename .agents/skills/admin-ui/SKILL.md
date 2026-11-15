---
name: admin-ui
description: Use when editing owner/admin pages, CRUD forms, media library, environment editor, permissions, audit log, integrations, or admin navigation.
---
# Admin UI

- Follow the root `AGENTS.md` repository contract and its app/view/storage area rules.
- Keep owner/admin UI PHP-template based.
- Admin mutations should route through controllers/services and remain auditable.
- Use schema-driven resource fields and the media library for product, temple, and blog media.
- Astrologer profile management and portraits are retired in admin as well as publicly. Do not restore their navigation, upload category, or bundled assets. Existing historical session and financial records remain owner-accessible; they do not authorize reintroducing profile management.
- Validate with `php tests/run.php`; use a browser workflow for changed admin pages.

## Admin Panel Agent

The interface at `/admin/agent` is the Enterprise Agent chat workspace.
The same navigation group links to `/admin/agent/monitoring` and the AI Support Tickets queue. Treat it as read-only unless controller code explicitly
implements and authorizes a mutation. Draft cards are editable proposals and must say
that nothing changes until the owner reviews and confirms.

### Implementation Pattern

The admin-agent flow:
1. `GET /admin/agent` calls `AdminController::agent()` and renders the page.
2. `POST /admin/agent/ask` calls `AdminController::agentAsk()`, with admin-only `EnterpriseToolRegistry` named read-only queries, local deterministic aggregates and server-owned follow-up report dates.
3. `SecretService` supplies provider/model settings. `AiClient` handles provider failover and bounded tools.
4. `AgentConversationService` saves a single private JSON file per chat. The `History` and `New chat` controls use authenticated `/admin/agent/history` routes to reopen only this admin's sessions; the frontend never reads the raw storage files.
5. The controller returns JSON; `views/admin/agent.php` safely renders replies, source links, drafts, traces and history.

The response may include a secret-safe `run` summary: route, total elapsed time,
allowlisted tool name/status/duration, and model attempt/status/duration. Never include
prompts, provider bodies, secrets, customer identities, or model reasoning in this trace.

### Agent Permissions

Keep context aggregate, allowlisted and purpose-specific. Do not include customer identifiers in aggregate summaries; exact order or customer lookups must be explicitly requested by the owner. Do not expose passwords, secrets,
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

For reports and agents, verify desktop and exact 375px separately. A mobile workspace is
not a squeezed desktop: remove side rails, keep the scoped-context summary visible, keep
the composer above the safe area, and prevent horizontal tables.

## Keeping this skill current

Update when an admin route, sidebar group, or CRUD form contract changes. The sidebar is gated by `SettingsService::modules()`; re-check both the on and off states.

## Agent monitoring and quality

`/admin/agent/monitoring` displays last-24-hour metadata-only
operational run counts, provider/tool failure timings and a clearly bounded
recent sample of conversation feedback states. The offline PHP golden test
score with its fixture hash is a regression score, **not** live model accuracy.
A customer must explicitly vote positive before a chat is recorded as
`confirmed_resolved`; silence is `awaiting_feedback`.
Admin session exports require authenticated access. Always test New Chat,
History, feedback and evaluation cards at 1280px and exactly 375px; inspect
JavaScript page errors and hidden-state computed styles.

## Execution evidence contract

For both agent surfaces, prepare the actor-owned private JSON request before
executing tools/providers. Persist actual span boundaries through
`AgentTraceService`; never backfill timestamps or call a produced response
delivered. Use effective result scope instead of requested filter arguments.
Unfinished requests are visible in Agent Monitoring and must not be blindly
replayed. Keep provider tokens/usage unavailable when absent, and distinguish
14-case offline regression scores from real customer satisfaction/accuracy.

## History and support queue controls

History supports bounded Load more and explicit owner deletion; pending actions
cannot be deleted. Keep export/deletion auditing enforced server-side. Ticket
forms must submit the observed version, allowlisted action and CSRF; display
priority/work state and preserve keyboard-accessible Manage controls. Do not call
a saved reply sent/delivered unless mail transport evidence exists.

## Developer/runtime instruction separation

This `.agents/` skill is for repository coding tools only. Live business
agent prompt text lives under `.bapX/`, selected by `AgentInstructionService`
with a fixed role allowlist. Update `docs/runtime-agents.md`, instruction
content fingerprints and isolation tests whenever that contract changes.
Never route a model-supplied path or developer index into live instructions.
