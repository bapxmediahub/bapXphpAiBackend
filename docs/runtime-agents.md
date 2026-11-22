---
type: doc
title: Developer Harness and Runtime Business Agent Boundaries
description: Separate private developer skills from the role-scoped live PHP business-agent Markdown and its evidence hashes.
category: docs
---

# `.agents/` is development; `.bapX/` is business runtime

`AGENTS.md` and `.agents/skills/*/SKILL.md` guide external coding harnesses
(Codex, DeepSeek Harness, Z-Code, Antigravity and IDE agents). They describe
repository navigation, tests, Git and deployment. They are **never prompt
inputs for a storefront customer or the live Enterprise Agent**.

`.bapX/` is the live business instruction tree, loaded by the existing
`AgentInstructionService`. It contains client-specific business rules without
Git, code-generation or developer CLI instructions:

```text
.bapX/
  supportagent.md
  enterpriseagent.md
  bapXcl/
    skill.md
    support/
      catalog.md
      policy-and-tickets.md
    enterprise/
      reporting.md
```

## Exact loading contract

| PHP surface | Required files | Permission |
|---|---|---|
| `support` | `supportagent.md`, shared `bapXcl/skill.md`, two listed `support/` files | Guest or authenticated customer, with private-data scope enforced by the PHP tools |
| `admin` | `enterpriseagent.md`, shared base, the two support skills, `enterprise/reporting.md` | Server-authenticated `role=admin` or strict boolean `is_admin=true` |

The public Support path always selects `support`. The authenticated admin
controller selects `admin`; it does not trust a browser field or model text
to choose that surface. The loader supports only those two enums and a fixed
file manifest. Adding a Markdown file does **not** silently grant a new skill.
A new runtime module requires a reviewed manifest change and tests.

`forSurface()` returns the selected prompt text. `manifestForSurface()` returns
only version, surface, relative paths, per-document SHA-256 and the bundle hash.
It is a PHP service API, **not a public route**. Required documents must be
UTF-8, nonempty, regular files no larger than 12 KiB each or 48 KiB per bundle.
Missing files, symlinks in the path, outside-root resolution and unauthorized
admin access fail closed. The service does not fall back to `AGENTS.md`,
generated indexes, arbitrary URLs, model-supplied paths or recursive globbing.

The trace fingerprints the **actual loaded Markdown bundle**, not just the PHP
loader file. Deterministic local answers which never load a model prompt leave
that trace field unset rather than inventing a loaded instruction. Offline
evaluations record both role manifests and include their content hashes in the
implementation fingerprint; the explicit admin actor there is a local test
fixture, not a production browser privilege grant.

## Security and storage boundaries

Both instruction trees and root `AGENTS.md` are denied to direct HTTP access
before PHP's development server serves static files, and by the production
root/directory `.htaccess` rules. The local routing guard also checks encoded
path forms. These rules complement—not replace—deployment server configuration.

This is application-level file selection, **not a new OS sandbox**. It prevents
Support from selecting Enterprise files through this loader; it does not make
all prompt injection or privilege escalation impossible. `AgentCapabilityPolicy`,
scoped tools, account ownership, CSRF, output handling and tests remain required.
Documents are instructions/evidence, never authority to expand a tool allowlist.

MySQL remains authoritative for business records. Each support/admin chat still
uses one private JSON file under `storage/agent-conversations/`; neither runtime
instruction tree changes that storage contract. No shell, arbitrary SQL or
unrestricted file read/write tool is added to either model.

## Client forks and maintenance

Reuse the developer workflow contracts; customize reviewed `.bapX/` business
rules for each client. Do not hardcode stale prices, stock, refund promises or
retired booking actions into generic skills. Exact current facts come from
approved application data. The generated developer index lists runtime file
**references** with type `runtime_instruction`, separately from developer
`skill`/`agentfile` entries, without duplicating prompt bodies. Live agents do
not consume that index as instructions.

Run `php tests/run.php`, `php cli/agent-eval.php`, `./bapXphp update` and
`./bapXphp ci`. Verify both role manifests, unauthorized/path/symlink denial,
actual prompt content hashes, ordinary Support and admin flows, and HTTP denial
for both trees before releasing.

## Next client implementation checklist

See [client-runtime-adaptation.md](client-runtime-adaptation.md) for the exact
reuse/customization boundaries. `.bapX/` isolation is implemented; automatic
multi-client provisioning is not. PHP policy constants, model-prompt branding,
mail templates and per-client data/secrets need explicit review as well as
Markdown changes. Never copy live customer records or credentials into a fork.

## Offline developer contract generation

`cli/agent-contracts.php` runs only in CLI and selects an explicit synthetic
admin role to enumerate the protected Enterprise manifest. It emits paths and
content hashes, never runtime prompt bodies. This does not grant production
browser access. Uncaught CLI exceptions fail with a nonzero exit code and a
bounded class/file diagnostic; they cannot masquerade as successful HTML output.

## Provider calls and owner how-to questions

Support resolves named product comparisons before calling a provider. Two or three
exact published name/slug matches are kept in the customer's mention order, with
current catalogue prices, descriptions and listed specifications. Missing, hidden
or ambiguous choices require clarification; the resolver never fills a missing
named choice with an unrelated item of the same type. Explicit generic type
comparisons may still use clearly labelled catalogue examples. The same resolver
feeds exact product identities to public A2UI, so the text and cards agree.

Enterprise Chat can read a fixed operator guide through `operator_guide`. The server validates the topic and owner session, reads the same Markdown as Admin Help, and records its source link. The explicit `/help` command and questions about drafting an article can take this path without a provider call. A question about the number of drafts still needs a business-data lookup.

The task guide in Enterprise Chat comes from `EnterpriseToolRegistry::capabilities`, filtered through the same actor policy as tool execution. **What can I ask?** is a native disclosure with editable question starters. `/capabilities` and a direct “What can you do?” question return that guide without a provider call or business lookup. It describes available tasks and explicit limits; it does not grant access or claim that a report ran.

`commerce_report` is an Enterprise-only read tool over the existing `StoreStatsService` helpers. It accepts the fixed `products`, `stock`, `customers`, and `coupons` report names, rows limited to 1–10 (default 6), and inclusive dated reports of at most 366 days through today. Stock rejects dates because it is a current status snapshot. Customer output is anonymous order-frequency groups; customer identities never enter this tool result. The actual result, rather than model text, defines metrics, source, scope and bounded saved report evidence. See [agent observability](agent-observability.md) for the private evidence projection.

Product net values allocate recorded refunds across lines; coupon values are associated order totals, not causal attribution; customer repeat means within the selected period. No profit, on-hand quantities, lifetime retention or live booking is implied. `.bapX/bapXcl/enterprise/reporting.md` supplies these meanings to the model without extending Support or the public MCP tool surface.

The application executes each allowed function and sends its result back using the originating provider's call format. Model text is never authority to grant a capability. Google documents the hosted Gemma identifiers and native function calling. OpenAI recommends clear tool schemas and doing known work in application code. Those contracts apply to their respective clients. [Google AI Studio function calling](https://aistudio.google.com/docs/function-calling) · [Hosted Gemma models](https://gemma.ai/deployment/gemini-api/) · [OpenAI function calling](https://developers.openai.com/api/docs/guides/function-calling)

Time-limited retries are for transient transport or service failure. Authentication and invalid-request responses need configuration or request corrections. The owner can inspect per-attempt evidence in Monitoring to distinguish service responses from requests that reached the local timeout without an HTTP response. [Google API troubleshooting](https://aistudio.google.com/docs/troubleshooting) · [OpenAI error codes](https://developers.openai.com/api/docs/guides/error-codes)
