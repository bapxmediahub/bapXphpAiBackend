---
name: rea-wp-rest-api
description: Reconstruct native HTTP and REST capability contracts using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P12, P31, P32, P33, P34, MCP02, MCP03, MCP04, P11, P10]
---

# Reconstruct native HTTP and REST capability contracts

Adapted from [wordpress/wp-rest-api](../../upstream/wordpress/skills/wp-rest-api/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream route namespace, args schema, response fields, permission callbacks and discovery demonstrate transport contracts. bapX already has routes/controller services and scoped MCP; private HTTP parity is partial.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P12` — Fine-grained staff roles and delegated API identities.
- `P31` — Authenticated public REST integration API and revocable credentials.
- `P32` — Signed webhooks and retry-safe event delivery.
- `P33` — Shared typed capability catalogue and cross-transport schema parity.
- `P34` — Native MCP transport and scoped client management.
- `MCP02` — Public Streamable HTTP tools and resources.
- `MCP03` — MCP private prompt projection and authenticated remote tools.
- `MCP04` — Client lifecycle, session and transport observability.

- `P11` — Customer profile/address/order access.
- `P10` — Admin tables pagination, filters and safe bulk actions.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-includes/capabilities.php`
- `woocommerce`: `includes/admin/class-wc-admin-api-keys.php`
- `woocommerce`: `includes/class-wc-webhook.php`
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `woocommerce`: `src/Internal/MCP/McpEndpointRemoval.php`
- `wordpress-mcp`: `includes/Transport/HttpTransport.php`
- `wordpress-mcp`: `includes/Domain/Prompts/RegisterAbilityAsMcpPrompt.php`
- `wordpress-mcp`: `includes/Transport/Infrastructure/SessionManager.php`

Additional pinned anchors for these native extensions:
- `woocommerce`: `includes/class-wc-customer.php`
- `wordpress`: `wp-admin/includes/class-wp-list-table.php`

## Native implementation workflow
Locate route in ProjectMapService and actual dispatcher. Trace HTTP method, auth/session or scoped grant, CSRF for browser writes, strict args, owner isolation, service and response shape. Use bounded pagination and stable IDs; no arbitrary schema collection endpoint or private data in public discovery. Preserve existing endpoints for clients. Treat signed inbound gateway webhooks separately from outgoing subscriptions; retries and idempotent writes require durable claim and observed outcome.

Apply the same owner/customer scope and stable bounded pagination to profile/address/history and every admin list. Validate filters and bulk actions independently of UI state; stale/cross-actor references cannot mutate records.

Inspect these native entrypoints or contracts first:
- `app/Services/ProjectMapService.php`
- `app/Services/PublicMcpService.php`
- `app/Services/McpClientService.php`
- `app/Controllers/RemoteDbController.php`

- `app/Controllers/AccountController.php`
- `views/admin/list-controls.php`

## Acceptance and failure modes
Test actual router with missing/invalid grants, cross-actor IDs, malformed schemas, unsupported method, cache/no-store and duplicate writes. Use native MCP SDK/HTTP where applicable and document capability gaps; never add WP REST runtime.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/public-mcp-http.cjs`
- `tests/mcp-client-access.test.cjs`
- `tests/campaign-form-http.php`

- `tests/account-profile-browser.cjs`
- `tests/admin-list-pagination.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-rest-api/SKILL.md)
- [references/routes-and-endpoints.md](../../upstream/wordpress/skills/wp-rest-api/references/routes-and-endpoints.md)
- [references/authentication.md](../../upstream/wordpress/skills/wp-rest-api/references/authentication.md)
- [references/schema.md](../../upstream/wordpress/skills/wp-rest-api/references/schema.md)
- [references/discovery-and-params.md](../../upstream/wordpress/skills/wp-rest-api/references/discovery-and-params.md)
