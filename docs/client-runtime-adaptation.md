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
5. Replace identity/contact/domain and provider configuration at the sources in
   the table. Verify sender/recipient boundaries and payment test mode before
   any external test. Never use a real payment to simulate setup success.
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

## Release evidence

Use `php cli/agent-eval.php --json`, `php tests/run.php`, the relevant isolated
DB/socket tests, `./bapXphp update` and `./bapXphp ci`. Retain a reviewed checklist
of the client's actual policy sources and scope decisions. No extra paid agent
framework or SQL transcript store is needed for this reuse pattern.
