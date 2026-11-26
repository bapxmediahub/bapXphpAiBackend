---
name: rea-wp-performance
description: Reconstruct measurable native performance and analytics using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P09, P17, P28, P29, SK02, SK04, CC04, DS05, MC01]
---

# Reconstruct measurable native performance and analytics

Adapted from [wordpress/wp-performance](../../upstream/wordpress/skills/wp-performance/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream performance guidance measures TTFB, SQL, autoload/cache, cron and remote HTTP before changing code. REA Site Kit PageSpeed classes and current transport traces distinguish lab/field, quota and actual data. Native page speed/reporting is partial.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P09` — Media pagination, alt/caption editing and image transforms.
- `P17` — Persistent job scheduling and operator job health.
- `P28` — Commerce reports and analytic comparison/drill-down.
- `P29` — Order attribution and trustworthy funnel denominators.
- `SK02` — GA4 and Search Console reports.
- `SK04` — PageSpeed lab/field data and quota recovery.
- `CC04` — Provider retries, holds, time budgets and latency.
- `DS05` — Tool/result evidence and telemetry failure isolation.

- `MC01` — Google Merchant catalogue readiness and authorized multi-channel YouTube Shopping.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-admin/includes/class-wp-list-table.php`
- `wordpress`: `wp-includes/cron.php`
- `woocommerce`: `src/Admin/API/Reports/Orders/Controller.php`
- `woocommerce`: `src/Internal/Orders/OrderAttributionController.php`
- `sitekit`: `includes/Modules/Analytics_4.php`
- `sitekit`: `includes/Modules/PageSpeed_Insights.php`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `deepseek`: `packages/session/session-telemetry/src/coordinator.ts`

Additional pinned source: Google for WooCommerce `src/API/YouTube/Connection.php`, `src/API/Site/Controllers/YouTube/AccountController.php` and `src/API/Google/Mapi/MerchantApiClient.php` at `e4c389b`. The authorized Connect Server and third-party link receipt are external dependencies, not a generic Woo REST endpoint.

## Native implementation workflow
Choose a reproducible exact URL/task, viewport, auth and source SHA; establish baseline request latency, SQL/remoteDB calls, asset weight, layout and provider spans. Identify dominant bottleneck from actual traces; use existing RuntimeHealthService, ImageOptimizerService, SiteTelemetryService and bounded query/queue tools. Inspect Site Kit reporting grants, separate cached result age, quota and no-data; do not invent analytics or install WP plugins/APMs. Preserve correct data and scope before tuning; no broad cache flush or hardcoded numbers.

Inspect these native entrypoints or contracts first:
- `app/Services/RuntimeHealthService.php`
- `app/Services/ImageOptimizerService.php`
- `app/Services/SiteTelemetryService.php`
- `integrations/google-site-kit/PageSpeedInsightsClient.php`
- `app/Services/AiClient.php`

## Acceptance and failure modes
Repeat same baseline/candidate conditions, label local vs live, cache state, actual measurements and failed provider results; verify no loss of correctness, privacy or mobile responsiveness. Never equate faster synthetic response with production speed or a successful real report.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/google-pagespeed.php`
- `tests/google-pagespeed-browser.cjs`
- `tests/ai-provider-transport.php`

Live #518 exhausted multiple initial provider attempts and then failed both continuation and alternate synthesis. Separate observed transport failure from application latency; do not expand retry budgets or add unconfigured/paid providers to force a success. Re-evaluate the actual working connection before a new real acceptance attempt.

For YouTube Shopping, use actual native product metadata, RSS, visible landing and checkout facts. Read Google Merchant data-source and direct invited-creator onboarding docs. An eligible creator may link an authorized data source/channel; a store platform must be approved and may submit repeated actual channel IDs only under that authorized contract. Never impersonate WooCommerce, invent identifiers, channel names or a connected status, or join affiliate data to customer identities. Verify each account, item approval, link and actual tagging separately. A generated feed or schema is not approval. See `docs/google-sales-channels.md`.

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-performance/SKILL.md)
- [references/measurement.md](../../upstream/wordpress/skills/wp-performance/references/measurement.md)
- [references/database.md](../../upstream/wordpress/skills/wp-performance/references/database.md)
- [references/autoload-options.md](../../upstream/wordpress/skills/wp-performance/references/autoload-options.md)
- [references/http-api.md](../../upstream/wordpress/skills/wp-performance/references/http-api.md)
