---
name: rea-wp-block-development
description: Reconstruct native content component contracts using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P03, P04, P05, UI01, UI04, UI05, P06, P07, P08, P16, CC07]
---

# Reconstruct native content component contracts

Adapted from [wordpress/wp-block-development](../../upstream/wordpress/skills/wp-block-development/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Block registration, serialized attributes, edit/save/render split, child composition and deprecation teach why editor schemas need stable versioned data. Native pages use bounded Markdown and typed page settings, not Gutenberg blocks. General reusable component hierarchy remains partial.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P03` — Posts draft/preview and public search.
- `P04` — Autosave, revisions, edit locking and scheduled publishing.
- `P05` — General pages, reusable layouts and content hierarchy.
- `UI01` — Validated structured surface and scoped renderer.
- `UI04` — Incremental component and data-model update lifecycle.
- `UI05` — Typed bidirectional action and form events.

- `P06` — Taxonomies, tags and hierarchical catalog organization.
- `P07` — Blog comments and product-review moderation.
- `P08` — Media CRUD and protected deletion.
- `P16` — Canonical/permalink and redirect governance.
- `CC07` — Model-backed editorial draft generation and truthful failure.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php`
- `wordpress`: `wp-includes/revision.php`
- `a2ui`: `specification/v1_0/json/agent_to_renderer.json`
- `a2ui`: `specification/v1_0/json/renderer_to_agent.json`

Additional pinned anchors for these native extensions:
- `wordpress`: `wp-admin/menu.php`
- `wordpress`: `wp-admin/menu.php`
- `wordpress`: `wp-admin/menu.php`
- `wordpress`: `wp-admin/options-permalink.php`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`

## Native implementation workflow
Trace current authoring form through revision CAS to authoritative Markdown/page record and public renderer. Map the upstream attribute defaults, validation, dynamic rendering and old-format recovery to native typed fields, safe rendering and explicit migrations. Keep publication separate from recovery; preserve existing content and approved design. For agent UI use A2UI validated catalog/surface contracts, not Gutenberg markup or arbitrary component code. Do not invent a block storage/runtime.

Native CMS extension: trace category hierarchy and slug/redirect collision, comment/review moderation roles and protected media references before proposing reusable blocks. Existing article generation must be actual provider output and explicit unpublished create-only save. Never invent comment moderation or full redirect parity from a page editor.

Inspect these native entrypoints or contracts first:
- `app/Services/AuthoredPageService.php`
- `app/Services/EditorDraftService.php`
- `app/Services/BlogService.php`
- `app/Services/PublicA2uiService.php`

- `app/Services/CategoryService.php`
- `storage/schema/collections.php`
- `app/Services/MediaService.php`
- `app/Services/SeoService.php`
- `app/Services/AgentDraftService.php`

## Acceptance and failure modes
Round-trip existing content, missing/unknown fields, stale revisions, conflict recovery, public escaped rendering and desktop/mobile editor behavior. Test old record readability and no unintended publishing; any new composable layout requires new source evidence and scoped issue.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/editor-server-recovery-browser.cjs`
- `tests/campaign-form-http.php`
- `tests/public-a2ui.php`

- `docs/modules/catalog.md`
- `tests/media-delete-integration.php`
- `docs/modules/content-and-seo.md`
- `tests/run.php`

For native editor recovery, retain the original expected recovery token.
After a database-confirmed deadlock rollback only, the owned autocommit CAS
may re-read and retry at most twice; a winning concurrent insert becomes a
conflict, not an upsert. Lost transport and ambient transactions must not be
replayed. Test both actual MySQL/MariaDB worker outcomes and deterministic
retry bounds. This is not an active WordPress edit-presence lease or a
production recovery receipt. Fit deterministic recovery identities to the
deployed primary key; preserve truncated and full-width legacy copies and refuse
ambiguous duplicates. Require actual stored-key read-back and width-specific
strict/permissive SQL fixtures.

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-block-development/SKILL.md)
- [references/attributes-and-serialization.md](../../upstream/wordpress/skills/wp-block-development/references/attributes-and-serialization.md)
- [references/deprecations.md](../../upstream/wordpress/skills/wp-block-development/references/deprecations.md)
- [references/dynamic-rendering.md](../../upstream/wordpress/skills/wp-block-development/references/dynamic-rendering.md)
