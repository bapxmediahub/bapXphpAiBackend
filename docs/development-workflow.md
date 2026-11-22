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

## Discover before changing

Read `AGENTS.md`, then the discovery block of `index.yaml`, then exact route,
service or resource matches. Open the original source; a generated index proves
that a file exists, not that its feature works. If an index disagrees, inspect and
fix its generator rather than merely editing or trusting the output. The local
blog source is `content/blog/posts/*.md` through `BlogService`; the legacy
`GitHubDocService` file does not establish an active remote-content workflow.

Read the relevant developer skill and current code, issue comments and merged
PRs. Use REA pinned-source evidence to form testable questions, not proof that
a feature is missing in the current revision. Public/admin instructions and
customer data must not be mixed into coding-harness context indiscriminately.

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
