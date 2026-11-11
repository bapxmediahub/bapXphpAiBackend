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
  or the GitHub API/web interface.
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

Pushing `codex/**`, `fix/**`, or `feat/**` lets `branch-pr.yml` create a PR when
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
