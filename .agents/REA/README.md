# REA developer reference skills

These are **developer-only source research guides**, not business-agent skills or
WordPress/WooCommerce runtime dependencies. Read the repository's AGENTS.md
first. Native PHP, MySQL, Markdown/JSON and vanilla JS remain authoritative.

## Layout and provenance

- `upstream/wordpress/` and `upstream/woocommerce/`: byte-for-byte selected
  reference snapshots, including every original skill, its bundled references
  and scripts, licenses, docs, shared reference data and evaluation scenarios.
  Do not invoke the original scripts, installers, WP commands, workflows or
  evaluation prompts against bapX. Upstream source repositories are not remotes
  of this project. Packaging/installation automation and upstream CI are excluded.
- `skills/rea-*/SKILL.md`: 22 native adaptations, one for each imported skill.
  They reconstruct the reference behavior, map to the existing REA obligations,
  identify native entrypoints and test contracts, and explain the intentional
  deviations. Do not turn these into runtime prompts or public tool resources.
- `skill-map.json`: exact upstream commit pins, GPL-2.0-or-later notices, selected
  file hashes, REA static import receipts, skill-to-capability mapping, native
  source and test paths. Imported skills are guidance, not proof of actual
  upstream runtime behavior. Read the pinned application source behind the
  relevant finding before implementing.

The original GPL-2.0-or-later license and copyright notices are preserved at
`upstream/wordpress/LICENSE` and `upstream/woocommerce/LICENSE`. Modified
adaptation documents in `skills/` are marked as adapted and distributed under
GPL-2.0-or-later. No external runtime or executable plugin is installed.

## Route before coding

```sh
./bapXphp skills
./bapXphp skill rea-wordpress-router
php cli/rea-skills.php --for P23
./bapXphp skill rea-abilities-api-implement
php cli/rea-skills.php --check
```

The normal `.agents/skills/` skills remain the canonical native engineering
procedures. Use the relevant REA guide in addition to, not instead of, them.
The capability register and current GitHub issues determine what is actually
implemented, partial, missing or excluded. The adapted skill maps a behavior
to the right investigation and acceptance steps; it does not change percentages.

## Routing index

| Native skill | Use for |
|---|---|
| rea-wordpress-router | Task classification, source-to-feature routing |
| rea-wp-project-triage | Repository, version and environment evidence |
| rea-blueprint | Ordered declarative configuration, deferred provisioning |
| rea-wp-env | Reproducible isolated native development environments |
| rea-wp-playground | Native browser fixtures, WebMCP and exact-SHA acceptance |
| rea-wp-block-development | Content schema, rendering and revision migration |
| rea-wp-block-themes | Layout precedence, design settings and template parts |
| rea-wp-patterns | Reusable composition and responsive presets |
| rea-wp-interactivity-api | Scoped UI state, A2UI lifecycle and actions |
| rea-wpds | Design tokens, accessible native components and brand fidelity |
| rea-wp-plugin-development | Built-in module lifecycle, permissions and settings |
| rea-wp-plugin-directory-guidelines | Provenance, license and release review |
| rea-wp-abilities-api | Domain capabilities and transport projections |
| rea-wp-abilities-audit | Pre-implementation source and permission audit |
| rea-wp-abilities-verify | Adversarial schema, permission and effect checks |
| rea-wp-ability-development | Typed input/output and error contracts |
| rea-wp-rest-api | Native HTTP/MCP grants, methods and responses |
| rea-wp-performance | Measured latency, storage, provider and reporting behavior |
| rea-wp-phpstan | Native types, lint and static-analysis boundaries |
| rea-wp-wpcli-and-ops | Safe CLI, queues and deployment operations |
| rea-abilities-api-implement | Commerce domain implementation and verified projections |
| rea-woocommerce-stale-pr-audit | Exact-head cohort audit and regression causation |

## Maintain the collection with REA findings

1. Read current GitHub issue, native implementation, register row and pinned
   upstream application source; use the matching adapted skill(s) to define
   the behavior contract and test plan. Do not copy a reference runtime.
2. When a finding, scope or source anchor changes, run
   `php cli/rea-skills.php --for <capability-id>`. Revisit each mapped native
   guide and update specific instructions/anchors if needed. Record reviewed
   IDs, concrete behavior and rationale in the issue/PR.
   `php cli/rea-skills.php --affected` lists changed guides or findings.
   After reviewing a specific guide, record it with
   `php cli/rea-skills.php --record rea-wp-performance --reason "Reviewed the changed provider and PageSpeed failure contracts and updated the guide."`
   This changes only the skill-review receipt; it never promotes capability status.
   A test passing does not
   justify rewriting a partial finding as complete.
3. For upstream refresh, fetch both repositories separately outside this repo,
   pin the exact reviewed commit, inspect the license and full changed skill
   files, import the selected reference tree with REA, and reconcile the native
   adaptations and manifest in one scoped PR. Never execute upstream installers
   or overwrite adapted instructions with an upstream update. Retain prior pin
   history in Git.
4. Run the skill inventory check, capability register review and normal CI.
   The offline skill check rejects upstream-byte drift, unmapped or duplicate
   adaptations, unmapped/unknown capability IDs, missing referenced paths and
   stale guide or finding-review fingerprints. It is a
   structural integrity gate, not an AI quality or feature-completion oracle.

See [REA engineering workflow](../../docs/research/rea-engineering-workflow.md)
and [capability report](../../docs/research/capability-progress.md).
