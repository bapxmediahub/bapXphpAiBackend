---
name: rea-wp-plugin-development
description: Reconstruct native module lifecycle and security using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P12, P14, P17, P36, X01, X02, P13, SK01, SK03, SK05, SK06, SK08]
---

# Reconstruct native module lifecycle and security

Adapted from [wordpress/wp-plugin-development](../../upstream/wordpress/skills/wp-plugin-development/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream plugin architecture separates bootstrap, settings, permissions, migrations, scheduled tasks and cleanup. We adopt lifecycle contracts as first-party modules, not a plugin host or arbitrary hook interpreter.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P12` — Fine-grained staff roles and delegated API identities.
- `P14` — Site settings, branding and module controls.
- `P17` — Persistent job scheduling and operator job health.
- `P36` — Safe multi-client provisioning and deployment separation.
- `X01` — Install/update arbitrary themes and plugins.
- `X02` — Execute arbitrary theme/plugin PHP in browser.

- `P13` — Personal-data export/erasure and consent governance.
- `SK01` — Separate reporting OAuth grant and property discovery.
- `SK03` — Google tags and verification.
- `SK05` — Tag Manager container and consent governance.
- `SK06` — AdSense and Ads reporting.
- `SK08` — Reader Revenue Manager and Tag Gateway.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-includes/capabilities.php`
- `wordpress`: `wp-admin/options-general.php`
- `wordpress`: `wp-includes/cron.php`
- `wordpress`: `wp-admin/network/sites.php`
- `wordpress`: `wp-admin/menu.php`

Additional pinned anchors for these native extensions:
- `wordpress`: `wp-admin/includes/privacy-tools.php`
- `sitekit`: `includes/Core/Authentication/Authentication.php`
- `sitekit`: `includes/Core/Authentication/Verification.php`
- `sitekit`: `includes/Modules/Tag_Manager.php`
- `sitekit`: `includes/Modules/AdSense.php`
- `sitekit`: `includes/Modules/Reader_Revenue_Manager.php`

## Native implementation workflow
Locate existing module/setting and canonical schema; trace initialization, default/disabled behavior, authorized settings save, CSRF, validation, side effects and cleanup. Read upstream lifecycle and settings security guidance, then map to existing controller/service and DatabaseService. Preserve current settings on failures; use revision/idempotency guards and explicit owner enablement. Review migrations and backups; no arbitrary PHP execution, plugin activation, new daemon or production schema change without scope.

Native Site Kit-style modules: independently gate Google sign-in versus reporting OAuth, verify scopes/redirect URI and account/property binding, distinguish saved tags from grants and consent. Ads, AdSense, Tag Gateway and reader-revenue modules require actual scope and supported APIs; do not add paid features. Personal-data export/erasure needs explicit authority, retention policy and tests; no raw export or automatic deletion.

Inspect these native entrypoints or contracts first:
- `app/Services/SettingsService.php`
- `app/Services/SchemaService.php`
- `app/Services/DatabaseService.php`
- `app/Services/MailQueueService.php`

- `app/Controllers/AccountController.php`
- `app/Controllers/AdminGoogleReportingController.php`
- `integrations/google-site-kit/GoogleSiteKitClient.php`
- `views/admin/integrations.php`

## Acceptance and failure modes
Disabled module, permission denial, invalid settings, repeated save, rollback and current-site compatibility; verify durable due work and correct cleanup separately. No feature counted from an empty class or route.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/site-profile-browser.cjs`
- `tests/mail-delivery-concurrency.php`
- `tests/campaign-form-http.php`

- `docs/modules/auth.md`
- `tests/google-reporting.php`
- `docs/pages/integrations.md`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-plugin-development/SKILL.md)
- [references/lifecycle.md](../../upstream/wordpress/skills/wp-plugin-development/references/lifecycle.md)
- [references/security.md](../../upstream/wordpress/skills/wp-plugin-development/references/security.md)
- [references/data-and-cron.md](../../upstream/wordpress/skills/wp-plugin-development/references/data-and-cron.md)
- [references/settings-api.md](../../upstream/wordpress/skills/wp-plugin-development/references/settings-api.md)
