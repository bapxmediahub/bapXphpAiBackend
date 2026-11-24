---
name: admin-ui
description: Use when editing owner/admin pages, CRUD forms, media library, environment editor, permissions, audit log, integrations, or admin navigation.
---
# Admin UI

- Follow the root `AGENTS.md` repository contract and its app/view/storage area rules.
- Keep owner/admin UI PHP-template based.
- Tune elevation, spacing and motion to the operator's task. Use visible
  shadows when they help distinguish summaries, drawers, or action panels.
  Keep table reading and focus steady. Verify dense and narrow layouts in the
  browser, and update this skill and `Design.md` when the pattern is reusable.
- Admin mutations should route through controllers/services and remain auditable.
- Media metadata editing is owner-only, bounded, revision-checked and limited to existing MySQL uploads; never replace binaries or enable bundled/legacy editing.
- Media bulk removal uses named per-upload checkboxes and a separate owner review. Confirm only the recorded selection, consume its token once, and apply the single-upload reference checks and audit to each item. Test cancel, partial results, and 375 px without JavaScript.
- Logo & Favicon uses persistent Media Library uploads and the saved settings for previews. Test size and format rejection, a failed write, and successful reload with disposable brand profiles. Production verification reads the existing brand without replacing it.
- Use schema-driven resource fields and the media library for product, temple, and blog media.
- Astrologer profile management and portraits are retired in admin as well as publicly. Do not restore their navigation, upload category, or bundled assets. Existing historical session and financial records remain owner-accessible; they do not authorize reintroducing profile management.
- Validate with `php tests/run.php`; use a browser workflow for changed admin pages.

## Navigation and Users directory

Keep decorative sidebar icons aligned with visible link labels and preserve
active states and module visibility. The user directory is owner-only and
matches order history by checkout email. Show full-directory segment counts
separately from filtered result totals. Keep name/email search visible and
secondary controls in the native More filters disclosure. Keep the active-count
badge and reset visible when the disclosure closes. Verify combined criteria
and pagination without JavaScript, native keyboard expansion, IST dates and
stacked order history at 375px with synthetic accounts. Latest-order status uses
the most recent dated match. Keep older and undated orders in history.

The Public Site Profile is a native Site Settings form for published fields.
Keep its save and validation independent of SMTP, credentials, appearance and
module controls. Test valid and rejected saves and keyboard feedback at 375px
with JavaScript disabled, using synthetic owner installations.

## Admin Panel Agent

The interface at `/admin/agent` is the Enterprise Agent chat workspace.
The same navigation group links to `/admin/agent/monitoring` and the AI Support Tickets queue. Treat it as read-only unless controller code explicitly
implements and authorizes a mutation. Draft cards are editable proposals and must say
that nothing changes until the owner reviews and confirms.

Test rapid history switching, New chat during a pending read, keyboard mention selection mid-sentence, and successful and rejected inline uploads. Verify the draft and active conversation after each action. Use isolated owner sessions and fixture media for these checks.

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

## OpenRouter Free in existing AI settings

Keep the OpenRouter enable checkbox and masked API key adjacent to Google API Key.
The saved key exists only in encrypted `app_secrets` (hosted MySQL); blank
password means preserve, and disable retains it. A successful Test AI Connection
must say which model actually answered, especially after a Google fallback.
Test this owner-only form using Chromium Playwright at 1280px and 375px, and
assert the password never appears in HTML or screenshots. Do not create a new
frontend, local secret copy or separate provider client.

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

Keep admin action labels beside their decorative icons. In the Users directory,
show the applied filters below the toolbar. Each removal keeps the other criteria
and returns to the first results page. Verify removal by keyboard and touch,
including long search values and navigation with JavaScript disabled.

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
versioned offline regression scores from real customer satisfaction/accuracy.

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

## Operator help and section guidance

Keep the stable Help & Docs sidebar/topbar links and `Help for this page`
context in the existing layout. `AdminHelpService` lists fixed operator files;
never turn `/admin/docs/{slug}` into arbitrary file/URL loading. Match actual
labels, routes, permissions and limitations before changing a guide. Check
both its source metadata and `operator-help` documentation-review dependencies.
Public help belongs in the existing published Help blog category, not private
operator text. Test search/categories/anchors/task links with no JavaScript,
1280px and 375px, plus guest/customer denial and raw/encoded file-path denial.

System Health is a native read-only Settings surface, protected by the same
admin gate and no-store policy. Unknown/mismatched states must remain visible;
no fake all-green score, migration button or public phpinfo endpoint. Verify
real web PHP separately from developer CLI and test the 375px/no-JS guidance.

## Email preview checks

Use the actual Email → Templates view at desktop/375px and no-JS. The srcdoc
iframe stays sandboxed without scripts; do not relax it to silence a test harness
probe. Check long references, recipient-role controls, exact input CSRF, invalid
keys and rejected sends. No click on the diagnostic send button is part of
ordinary preview QA. An authorized sample uses one consumed request token and
must be correlated with the intended inbox; owner and customer are distinct.
Keep the delivery timing control native. Show the scheduled sample eligibility
time and pending state before reporting any SMTP result. Verify the timing
selector, one-use confirmation and Outbox state with a disposable browser fixture.

The answer-review screen is an authenticated native form with evidence selectors,
pass/fail/not-assessed rubric and explicit human/assisted/test provenance. Never
render private text as HTML, assume a missing source is a pass, or present a
staff label as customer satisfaction. Verify no-JS, mobile, CSRF, role denial,
stale revision and unchanged original JSON evidence after a synthetic QA save.

## JavaScript editor interactions

Use native controls and keep server validation, CSRF and revision checks in
PHP. Store only the editable fields needed for recovery, scoped to the signed-in
owner and article. Use text for status messages and escape any preview content
inserted into HTML. Keep restored browser drafts distinct from server saves.
After changing the editor, verify mode selection, keyboard focus, reload and
restore, a conflicting revision, and save acknowledgement in a local fixture
at desktop and 375px. Keep private drafts and screenshots out of Git.

In Products, the catalogue query and pagination execute on MySQL. Keep the edit-by-ID and unsaved validation-draft paths independent of list filters. CSV confirmation uses the current session's reviewed file digest, CSRF and a create-only database operation. Test the editable and paged list alongside upload, preview, row errors and confirmation.

## Structured Enterprise answers

Use the private `AgentAnswerPresentationService` only after the existing admin authorization and tool flow. Render its answer sections, recorded aggregate facts and approved source navigation as the primary response with `{scope:"admin"}`. Keep the complete original answer as fallback and keep draft writes in the existing review form. Project at most two fact groups from completed allowlisted tool evidence, with recorded source/date/observation context; never parse model claims into metrics. Restore those facts from saved tools/trace spans without new retrieval. Owner aggregate questions must not trigger public catalogue lookups. Keep public WebMCP/MCP Apps on the default public renderer scope. Verify live response, saved-history presentation, unsupported-input fallback and 375px layout in `tests/agent-history-browser.cjs`; read `docs/a2ui.md` for budgets and trust boundaries.

For `commerce_report`, consume only the `AgentTraceService::safeTool()` projection in recorded `evidence.report`. Render one latest report with at most six rows, fixed labels, calculation basis, effective dates or current-stock snapshot, observed time and shown/total coverage. Customer reports contain fixed frequency bands, never identities. Give the recorded primary metric clear hierarchy without adding decorative charts or invented trends. Preserve report-only evidence in history even when `evidence.facts` is empty. The complete accepted answer, actual request error state and existing reviewed draft form remain authoritative. Layout follows the card container width; verify narrow containers on desktop as well as the 375px viewport.
