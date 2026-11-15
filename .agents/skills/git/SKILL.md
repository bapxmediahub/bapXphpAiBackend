---
name: git
description: Use plain Git for local history, branches, pushes, and Hostinger deployment checkouts.
---

# Git

Git is the only repository executable required on Hostinger. Do not require
GitHub CLI (`gh`) on the hosted server.

## Boundaries

- Use plain `git` for status, diff, branches, commits, fetch, pull, and push.
- Use `bapXphp` only for project-owned operations such as tests, maps, indexes,
  schema, database access, hooks, and AI configuration.
- GitHub issues, PR creation, review, and merge coordination belong to GitHub Actions
  or the local `gh` CLI / GitHub API. Use `gh` on the authorized development machine; never require it on Hostinger.
- Never wrap ordinary Git commands in `bapXphp`.
- Never force-push or discard unrelated or divergent work.

## Local Workflow

```bash
git status --short --branch
git switch -c fix/issue-123-description
git diff --check
git add path/to/intended-file
git commit -m "fix: describe the change"
git push -u origin HEAD
```

Pushing `codex/**`, `fix/**`, `feat/**`, or `bapX/**` lets `branch-pr.yml` create a PR when
repository permissions allow it. Validation runs for pull requests and pushes to
`main`; verify the actual Action result.

## Hostinger

```bash
git status --short --branch
git fetch origin
git pull --ff-only origin main
git rev-parse HEAD
```

Hostinger hPanel normally performs the pull automatically. Manual SSH Git
commands are recovery and diagnosis tools only.

## Testing

`./bapXphp ci` before pushing. Branch, push, open a PR; never commit straight to `main`.

## Keeping this skill current

Update when the branch or review conventions change.

## Reviewer evidence

After current-head CI, inspect the vendor's actual current-commit receipt.
A green validate job or posted `@codex review`/`@sourcery-ai review`/
`@coderabbitai review` comment is not a completed review or merge approval.
Use the trusted default-branch coordinator described in
`docs/engineering/ai-review-workflow.md`; it never executes PR-head code
with review credentials. Preserve draft state and human release gates.
Respect usage limits and missing-app/user-token prerequisites; do not
reuse unrelated secrets or falsely report a blocked review as passed.

For new source-based comparisons, use `.agents/skills/rea-source-audit/SKILL.md`.
A review dispatch claim with unknown delivery must not be described as a completed
request. Inspect it before manual retry; preserve other agents' dirty worktrees.

## Pull-request readiness

Create new implementation PRs **ready for review**, not as drafts, as directed
by the owner. Keep failing checks, unresolved findings and incomplete runtime
verification visible in the PR description; ready-for-review is not merge
approval. Reconcile the latest main before merging and retain all independent
regressions. Never use blanket ours/theirs resolution for real code; regenerate
map/index outputs from the reconciled source.

## Issue-first release protocol

The authoritative issue/plan/acceptance record is GitHub, not `docs/`. Search first,
record evidence before fixing, and keep unrelated bugs in separate issues/PRs.
Create Ready-for-Review PRs with `Refs #N`; require exact-head CI and cross-reviewed
documentation receipts. After merge, test the deployed flow and record evidence
on that issue before closing it. See `docs/development-workflow.md`.
