---
type: doc
title: Client Runtime Adaptation Checklist
description: Source-grounded plan for reusing the PHP business-agent architecture without copying Sri Panchami data, policies or credentials into another client.
category: docs
---

# Adapting `.bapX/` for another client

This is an implementation checklist derived from `docs/runtime-agents.md` and
the current PHP code, not a claim that the application already offers automatic
multi-tenant provisioning. No next-client name, domain or approved business
policy was supplied; none has been invented or provisioned.

## Product contract and authority boundaries

The target is a separately installable and upgradeable white-label product.
Use the existing native owner controls before introducing configuration stores.
The current contract is not an installer or a claim of full portability.
Future /install will bootstrap DB access, unique owner credentials, identity,
logo/favicon, colors, SMTP, AI, Google OAuth and enabled integrations only
after core acceptance. Supported settings must remain editable by an authorized
owner after setup.

| Authority | Present source | Required reuse boundary |
|---|---|---|
| Deployment connection | Ignored .env and config/database.php | Separate origin, DB access and remote transport; never embed resolved deployment values in generated code knowledge |
| Non-secret owner settings | SettingsService app_settings/header_menu, authored pg: rows, canonical collections.php | Public Site Profile, logo/favicon/palette, modules, navigation, shipping/tax and authored pages |
| Integration credentials | SecretService encrypted app_secrets and scoped grants | Never copy client keys, SMTP/OAuth secrets or hashes into a package, fixture, public tool or index |
| Public identity/content | SiteProfileService, PublicKnowledgeService, SeoService, layouts and approved published content | Supply client-owned public facts and migrate approved editorial, legal and transactional copy independently |
| Agent guidance | .bapX, AgentInstructionService, scoped PHP registries | Keep business instructions, tool authorization and facts distinct; no arbitrary file/SQL tools |
| Storage | Client MySQL, private JSON conversations, blog Markdown, domain-local media | No shared customer records, private files or reference-deployment secrets |
| Developer contract | AGENTS.md, .agents, canonical schema, generated map.mmd, index.yaml and project-index.json | Shared implementation/source references, not prompt-body or runtime-data mirrors |

Reference: [WordPress global settings and styles](https://developer.wordpress.org/themes/core-concepts/global-settings-and-styles/)
separates core defaults, theme configuration and user overrides. Adapt that
hierarchy and owner-controlled settings to native PHP, not the WordPress plugin
runtime. Current migration gaps are tracked in GitHub.

## What can be reused

Keep external coding-harness contracts in `.agents/` and `AGENTS.md`. Preserve
the runtime loader's fixed Support/Enterprise manifests, role checks, bounded
UTF-8 document reads, symlink/outside-root denial and private HTTP protection.
Retain scoped business tools, CSRF/account isolation, request checkpoints and
one private JSON file per conversation. Preserve content hashes and the
before/after golden-evaluation baseline so policy changes are inspectable.

## What must be customized and verified

| Layer | Source to review | Required client decision |
|---|---|---|
| Support constitution | `.bapX/supportagent.md` | Client identity, allowed public topics, escalation boundaries and approved language |
| Enterprise constitution | `.bapX/enterpriseagent.md` | Authorized reports, staff roles and mutation approval process |
| Runtime skills | `.bapX/bapXcl/` and `AgentInstructionService` fixed manifest | Approved capabilities; new files alone must not grant access |
| Public facts and aliases | `AgentContextService`, actual client catalogue and `SupportBotService` | Names/aliases, collections, no-match behavior and exact source selection |
| Policies and commercial behavior | `SupportBotService::policies`, `OrderService` constants, checkout/tax/shipping services | Delivery, returns, tax jurisdiction and supported workflows |
| Brand and contact surfaces | `Design.md`, settings, views and mail templates | Logo, accessible palette, contact routes and sender identity |
| Infrastructure | database configuration, `SecretService`, deployment paths | Separate DB, secrets, storage, domain, providers and backup/retention policy |

**Changing only the Markdown is not enough.** The current Support model prompt,
shipping policy constants, fallback copy and several email/application strings
still name Sri Panchami or encode its business decisions. Treat these as explicit
adaptation points rather than asserting that the loader automatically rebrands
them. Do not loosen the role loader or add arbitrary model-selected file paths
to solve customization.

## Safe setup sequence

1. Create the new client repository/deployment only after its identity and scope
   are approved. Reuse code, not production records. Separate database account,
   secret encryption material, private conversation directory and media storage.
2. Do not copy `.env`, `.env.test-user`, browser cookies, admin password hashes,
   users, orders, tickets, mail queues, private chat JSON or customer media into
   the new client's runtime. Build explicitly synthetic fixtures instead.
3. Configure a unique admin credential through the supported secure setup and
   verify hashed-only login. Do not inherit a documented default password. Do
   not rotate another live client's credential as a side effect of this setup.
4. Map client-approved policies and tools to the fixed role manifests. A new
   service such as booking requires actual controller/schema/auth/UI work and
   regression tests; removing a prompt prohibition does not implement it.
5. Set a unique HTTPS `APP_URL` and `APP_NAME` in the client's deployment configuration.
   Use **Site Settings → Public Site Profile** for the business name, summary,
   About story, email, phone numbers and hours. The display name resolves from
   the saved public name, legacy SEO name, then configured app name. Reference
   contact defaults and hero/About artwork are scoped to the Sri Panchami
   origin. Use **Logo & Favicon** for this client's images and select its modules.
   Verify sender/recipient boundaries and payment test mode before any external
   test.
6. Create client-specific golden fixtures for exact catalogue membership,
   multilingual aliases, missing/ambiguous records, policy answers, denied
   customer access and owner report periods. Record the new instruction and
   source fingerprints; review any changed baseline rather than blessing it
   solely because tests run.
7. Test Support/Enterprise role isolation, JSON ownership, incomplete-action
   recovery, private-file HTTP denial, desktop/375px flows and backup/restore.
   Verify deployed content hashes and preserve runtime data across releases.
8. Verify real transactional email only to approved test recipients. SMTP
   acceptance and inbox receipt must remain separate evidence. Rollout does
   not authorize a marketing newsletter or historical queue replay.

## Upgrade and check an existing deployment

Back up its MySQL database, private conversation storage, approved content,
media and configuration before switching the checkout to a reviewed revision.
Keep the installation's origin, database account, encryption material and
runtime storage attached to that installation. The public profile fields live
inside the existing MySQL JSON-backed settings record. Independent settings
forms patch their own fields, and existing profile values are retained on
upgrade. The installed app keeps its original start path and scope.

New transactional emails use the published display name, help destination
and logo. Sender, Reply-To and owner recipient remain separately configured in
Integrations. Confirm the synthetic preview for each audience before sending
mail from a new deployment.

Verify the header and footer, About and Contact, canonical URLs, manifest names
and the public contact tool. Check a module in both states using navigation,
direct routes and sitemap results. Verify the working release before public
traffic. Use only the new client's approved catalogue, articles, legal pages,
mail wording and policies. Review or remove bundled reference content before
publishing that installation.

## Release evidence

Use `php cli/agent-eval.php --json`, `php tests/run.php`, the relevant isolated
DB/socket tests, `./bapXphp update` and `./bapXphp ci`. Retain a reviewed checklist
of the client's actual policy sources and scope decisions. No extra paid agent
framework or SQL transcript store is needed for this reuse pattern.
