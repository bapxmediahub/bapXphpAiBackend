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
