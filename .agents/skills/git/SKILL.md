---
type: skill
name: git
description: Use plain Git for local history, branches, pushes, and Hostinger deployment checkouts.
allowed-tools: Bash(git:*)
---

# Git

Git is the only repository executable required on Hostinger. Do not require
GitHub CLI (`gh`) on the hosted server.

## Boundaries

- Use plain `git` for status, diff, branches, commits, fetch, pull, and push.
- Use `bapXphp` only for project-owned operations such as tests, maps, schema,
  database access, browser automation, hooks, and AI configuration.
- GitHub issues, handoff comments, PR creation, review routing, and merge
  coordination belong to GitHub Actions or the GitHub web interface.
- Never wrap ordinary Git commands in `bapXphp`.
- Never force-push or discard divergent customer-fork commits.

## Local Workflow

```bash
git status --short --branch
git switch -c fix/issue-123-description
git diff --check
git add path/to/intended-file
git commit -m "fix: describe the change"
git push -u origin HEAD
```

Pushing an eligible feature/fix branch lets the repository workflow create or
update the PR. CI and reviewer handoffs run in GitHub Actions.

## Hostinger

```bash
git status --short --branch
git fetch origin
git pull --ff-only origin main
git rev-parse HEAD
```

Hostinger hPanel normally performs the pull automatically. Manual SSH Git
commands are recovery and diagnosis tools only.
