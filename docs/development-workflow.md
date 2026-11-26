---
type: doc
title: Evidence-led Development and Documentation Review
description: GitHub-only issue tracking, bounded source discovery, independent fixes, explicit documentation review and post-merge live acceptance.
category: docs
---

# One issue, one verified change, one release record

GitHub issues/comments are the only backlog, implementation plan and acceptance
record. `docs/` holds durable architecture, contracts, how-to instructions and
source-pinned research—not issue status pages or task checklists. Keep historical
research dated; current completion belongs to the relevant GitHub issue.
The owner-required [REA capability register](research/capability-progress.md)
is a versioned evidence coverage projection, not a second backlog. Follow the
[REA engineering workflow](research/rea-engineering-workflow.md) for affected
capability IDs, reviewed hashes, generated percentages and post-merge proof.

## Discover before changing

Read `AGENTS.md`, then the discovery block of `index.yaml`, then exact route,
service or resource matches. Open the original source; a generated index proves
that a file exists, not that its feature works. If an index disagrees, inspect and
fix its generator rather than merely editing or trusting the output. The local
blog source is `content/blog/posts/*.md` through `BlogService`; the legacy
`GitHubDocService` file does not establish an active remote-content workflow.

### Reading generated relationship evidence

Root `map.mmd` and `docs/systematic-map.mmd` are generated from the same
ProjectMapService inventory. They distinguish registered handlers, declared
service/related-view associations, literal source references and collection
references. A related view can be a redirect destination, fallback or resource
template. Check the exact registered method and path in the code maps and
indexes, including distinct paths such as `GET /admin` and `GET /admin/`. Explicit
`response: json` metadata is retained in `docs/project-index.json` and `index.yaml`
without adding an HTML view.

The bounded source scanner reads PHP tokens in controller, service and integration
files. It records named-class `extends`, literal `new` and static class references,
including `::class`; it resolves namespace imports and aliases without autoloading
or executing the classes. Each deduplicated source relationship in the project
index points to the original file and first matching line. Comments, strings and
unused imports do not establish relationships. Scan input is limited to 2 MiB
per PHP file and an oversized file fails generation.

This is a discovery aid, not a runtime call graph. Variable class names, injected
objects, anonymous/nested class bodies, trait propagation and function-only
entrypoints are outside the scanner's coverage. A literal reference does not
establish successful autoload, a reachable branch, a method invocation, permissions
or a working integration. `unreferenced_services` means no route/shared declaration
or incoming reference was found within that coverage; inspect the original code
before calling it unused. Collection links combine a bounded textual scan and
explicit overrides and do not establish read/write direction. The project index's
`relationship_provenance` explains these limits; knowledge-router relations use
`declared_service` and `collection_reference` rather than claiming calls or writes.

Read `gaps` in `docs/project-index.json` or the gap nodes in `map.mmd`. For a
route warning, open the handler and follow its service calls. An admin audit
warning reports a missing declaration. Check whether the operation changes
durable data or performs an external action, and trace where any audit event is
written before correcting route metadata or runtime code. For an unreferenced
service, check entry points outside the bounded scan. Record each confirmed
defect against the affected source and add a focused regression when the
existing suite cannot detect it.

Fix the route registry, source or generator that caused the discrepancy and
regenerate with `./bapXphp update`. Use `./bapXphp map:val` to check and report
the root and systematic code maps together. Run `./bapXphp ci` to also check the
documentation map, JSON and YAML indexes, documentation review receipts and
application tests. Use `php tests/project-map.php` for focused source,
inheritance, gateway, presenter and metadata regressions. These tests also run
through `tests/run.php`.

Read the relevant developer skill and current code, issue comments and merged
PRs. Use REA pinned-source evidence to form testable questions, not proof that
a feature is missing in the current revision. Public/admin instructions and
customer data must not be mixed into coding-harness context indiscriminately.

### Reference-driven implementation

Use the applicable installed development skills, then inspect pinned upstream
source, actual tool schemas, reference implementations and current official
documentation. For agent changes compare mainstream execution patterns and PHP
adaptations; for provider settings compare connection management and routing
rather than inventing a fixed model selector. Record the comparison and verified
gaps in GitHub. Reuse existing native services; do not install a framework merely
to copy a pattern. Keep .agents/ developer-only and .bapX/ business guidance
role-filtered across internal and external agent transports.

While a release gate is pending, advance a separate issue/worktree where possible.
Record independently observed failures and preserve unrelated changes. Never
treat successful lint, an offline fixture or a queued workflow as deployed work.

### Reusable capability acceptance

Read the binding white-label contract in AGENTS.md and
[client runtime adaptation](client-runtime-adaptation.md) before designing a
shared feature. Compare actual upstream source and skills, including WordPress
settings and theme configuration for owner-editable identity/appearance. Reuse
existing native services and canonical schema; do not create a parallel settings
system or build the deferred installer.

- Reconcile older REA findings against current source and open PRs; keep actual
  gaps and acceptance evidence in GitHub, not a second local backlog.
- Test distinct synthetic client profiles, owner settings persistence,
  module gating, credentials and data isolation, and reference-site regression.
  Separate local fixtures, real provider acceptance, independent review and
  exact deployed acceptance.
- Independently verify Knowledge RAG facts, Instruction RAG role/hash and Tool
  RAG authorization/discovery/execution. Never infer actual tool execution from
  model text or treat the developer index as business-agent authority.
- Regenerate MMD/JSON/YAML from source after schema/route changes. Committed
  developer knowledge must be byte-stable across APP_URL and BAPX_REMOTE_DB_URL
  changes. Review README execution diagrams against real code; static graph
  edges are not runtime call ordering.
- Close only verified scope, preserve blockers in issues and advance the next
  independent reviewed implementation instead of reporting a queued check as
  finished work.

### Provider and agent acceptance

Test the actual integration form at desktop and 375px. Exercise masked key
retention, explicit model IDs, independent enable switches, safe endpoint changes,
and saved configuration reload with isolated credentials. Check Chat Completions
requests and tool continuation against the official API contracts for Google,
OpenRouter, and the custom endpoint under review. Run
`php tests/ai-provider-transport.php` for loopback transport coverage and
`php tests/model-driven-agents.php` for both native chat controllers. Use
`php cli/agent-eval.php` for deterministic agent fixtures.

On the deployed revision, select each affected enabled provider and model and
verify a live text response and synthetic tool round trip separately. Exercise
the affected authenticated and guest flows through both agents with authorized
lookups. Use synthetic, non-private questions and record the deployed SHA,
provider/model, redacted status, timings and tool outcomes. Track rejected schema,
authentication, transport, quota and model-availability failures separately from
the quality of a completed answer.

## GitHub issue-first loop

Use `gh` on the authorized development machine; hosting requires Git/PHP only.

```bash
gh issue list --state all --search 'exact symptom or component'
gh issue view ISSUE --comments
gh issue comment ISSUE --body-file /tmp/evidence.md
# For a genuinely distinct finding:
gh issue create --title 'Observed defect' --body-file /tmp/reproduction.md
```

Record the exact source SHA, reproduction, expected/actual result, affected
paths, root cause where established and acceptance tests. Redact credentials,
customer records, cookies and provider bodies. If a test finds an unrelated
bug, create a separate issue immediately; do not silently expand the current PR.
Use a comment on an existing issue when its scope genuinely covers the finding.
Do not close an old issue merely because a similarly named file now exists.

Use an isolated branch/worktree. Write a failing regression, fix the cause,
verify the behavior and update only the affected durable documentation/skills.
Run the browser desktop/375px flows for UI changes and isolated socket/database
fixtures for mutations. Production reads are not authorization for destructive
customer actions, payment tests or historical mail replay.

Native WebMCP acceptance keeps separate page JavaScript, isolated-world and CDP
checks, alongside the official polyfill lane. The CDP diagnostic runs only in the
Chrome instance launched by CI, records its browser/protocol identity, verifies
the exact page registry and invokes only published contact information. Unknown
protocol shapes and unsupported domains fail explicitly. Mock correlation tests
do not replace the native browser run, and a passing CI browser does not certify
a managed external agent bridge or install a chat connector. See
[WebMCP verification boundaries](webmcp.md#verification-boundaries).

## PHP lint failure propagation

`./bapXphp lint <file>` checks that file, `./bapXphp lint <directory>` recursively
checks matching PHP paths, and `./bapXphp lint` checks the normal application,
API, test, CLI and view roots plus `index.php`. Quoted paths may contain spaces.
Parser and traversal failures return nonzero with the affected paths. Directory
scans filter normal success lines without using that filter as the failure
status, continue reporting other malformed files, and never emit a successful
scan summary after a failure. The `ci` command records `LINT FAILED`, runs its
other gates and finishes unsuccessfully when lint fails.

The required `node --test tests/cli-lint.test.cjs` regression uses native PHP and
copies the actual public CLI launchers into a temporary miniature project.
It checks explicit-file argument forwarding, directory/default aggregation and
an overall CI failure with every unrelated CI stage passing. Those unrelated
test/map/documentation/smoke stages are fixture scripts, so the check isolates
lint rather than depending on application databases or generated-file state.
No malformed fixture is written into the application checkout. The direct
parser-status precheck fails if an interpreter wrapper prints syntax errors
but returns zero; that environment cannot substitute for native PHP acceptance.

## Portable PHP release gates

`./bapXphp ci` runs `php tests/storefront-search.php` after the main PHP
test suite. The hosted validation job uses the same CLI gate. The fixture drives the
native search route and checks mixed product, article and temple pagination,
public visibility, Shop and Blog settings, malformed queries and the failure
path when module settings are unavailable. It uses disposable Markdown and a
loopback database bridge with synthetic records.

A failed fixture prints `STOREFRONT SEARCH FAILED`, leaves independent gates
running and makes the final CI status nonzero. The CLI regression in
`tests/cli-lint.test.cjs` exercises that failure with a valid PHP stand-in
that returns a nonzero exit status.

The same command explicitly runs the portable PHP fixtures for contact admission,
campaign booking and HTTP forms, media deletion, deployment revision, public
knowledge/discovery, remote carts and checkout, verified orders, MCP and A2UI
scope, owner commerce evidence, and documentation review. Each uses synthetic
records, temporary storage or isolated loopback transport. A missing or failing
fixture prints its name, fails the overall command and does not skip later
independent validation. Hosted CI invokes this shared gate once rather than
maintaining a second PHP-only list. Browser/SDK, provider transport and real
disposable MySQL tests remain explicit additional hosted gates; a local green
result alone is not their acceptance.


## Source-to-documentation review gate

`config/documentation.php` maps source areas to their durable guides/skills.
`cli/docs-check.php` hashes both sides and checks explicit review receipts in
`docs/source-contracts.json`. These receipts contain source/reference digests
and a rationale—**no issue status, task backlog or customer data**.

```bash
php cli/docs-check.php --list
# Open the affected source and guide files, correct any discrepancy, then:
php cli/docs-check.php --record content --reason 'Compared the renderer and blog routes with the guide; documented actual publication and image behavior.'
php cli/docs-check.php --check
./bapXphp update
./bapXphp ci
```

Repeat review recording after any further source/reference changes in that
area. A non-user-visible change may leave prose unchanged, but needs an honest
rationale. The command does not certify truth; it prevents an unnoticed source
change from inheriting a stale review. PR reviewers must still compare actual
behavior and prose. Do not mechanically acknowledge hashes to make CI green.
`config/documentation.php` can also declare explicit `dependencies` for a
review area. Shared feature files still have one first-match owner, but their
hashes also invalidate every dependent operator-guide review. The fixed help
manifest supplies the original guide paths and source dependencies; adding a
file to an index is not approval of its instructions. Dependency files must
exist and pass the same path/symlink checks as other reviewed sources.

`bapXphp update` regenerates inventories but deliberately **does not** approve
semantic documentation review. New files are covered by area patterns or the
foundation area; missing references and forbidden issue-ledger paths fail CI.

## Ready PR → merge → live test → close

Open a Ready for Review PR using `Refs #ISSUE`, not a draft. Include exact test
commands, doc-review rationale and any unresolved acceptance gates. Keep
independent fixes separate; resolve conflicts by preserving both behaviors and
regenerating derived files. Verify the checks for the exact head SHA.

Use `Refs` while deployment acceptance remains pending: GitHub closing keywords
such as `Fixes` can close an issue on default-branch merge, before live checks.
After the authorized merge and hosting deployment, verify the actual affected
live flow, record safe evidence on the original issue, and close only satisfied
acceptance criteria. A deploy failure becomes a fresh issue or a new comment
within the affected scope—not a fabricated pass.

```bash
gh pr create --base main --title 'Scoped fix' --body-file /tmp/pr.md
gh pr checks PR
gh pr merge PR --squash --match-head-commit EXACT_HEAD_SHA
# Perform and record live verification first:
gh issue comment ISSUE --body-file /tmp/live-evidence.md
gh issue close ISSUE --reason completed
```

CI success, a review request, a genuine current-head reviewer result and
production verification are separate evidence. Preserve provider quota/missing
credential blocks without pretending they are review approval. See
`engineering/ai-review-workflow.md` for the trusted metadata coordinator.

Reference: [GitHub issue/PR linking behavior](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/linking-a-pull-request-to-an-issue).

## JSON response mappings

A route that intentionally returns JSON can declare `response: json` in the
ProjectMapService registry without inventing an HTML template. Both registry
validation and the systematic scan use that explicit contract. A controller is
still required; an undeclared or misspelled response kind does not bypass the
missing-page gate. The signed payment webhook uses this mapping. Fixed atomic
database operations declare their actual private collection dependencies in the
existing explicit overrides because the generic CRUD scanner cannot infer them.
