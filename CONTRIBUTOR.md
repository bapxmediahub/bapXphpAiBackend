---
title: Contributing
description: Verified contribution workflow for Sri Panchami Spiritual.
category: root
---

# Contributing

Read `CLAUDE.md`, then use `README.md` and the generated indexes to locate the
smallest owning source. Do not introduce parallel implementations or local copies of
hosted MySQL data.

## Workflow

```bash
./bapXphp map
./bapXphp schema list
git switch -c fix/issue-number-description
# make the smallest source-grounded change
./bapXphp update
./bapXphp ci
git diff --check
```

Push `codex/**`, `fix/**`, or `feat/**` and review the automatically created PR when
repository permissions allow it. Never commit directly to deployment `main`.

## Application agents

The product contains exactly two AI surfaces:

1. Customer support: `POST /support/ask` → `SupportController` → `SupportBotService`
   with user-scoped `AgentContextService` data.
2. Owner/admin chat: `POST /admin/agent/ask` → `AdminController::agentAsk()`.

Both are PHP application features configured through Admin → Integrations. Do not add
agent orchestration, alternate public agent APIs, or browser runtimes to this repository.

## Evidence

Bug reports and PRs must cite the exact route/path/symbol, reproduction, observed
result, expected result, and smallest passing acceptance check. Generated indexes help
locate sources but never replace verification against original code and runtime behavior.
