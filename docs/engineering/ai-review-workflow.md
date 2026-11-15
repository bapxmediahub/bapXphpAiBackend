---
type: doc
title: AI Review Evidence Workflow
description: Trusted post-CI review requests with current-head receipts, explicit quota blocks and no unsafe token fallback.
category: docs
---

# AI review evidence: CI is not a review

The existing `Validate application` workflow validates code and generated maps.
It does not itself run Codex, Sourcery or CodeRabbit. Review requests, provider
acknowledgements, completed reviews, approval and deployment are separate events.
This patch is staged for review; it is not active until merged to the default branch.

## Source-verified causes on 15 November 2026

- Main had no explicit post-successful-CI reviewer dispatch workflow.
- The branch-PR workflow omitted `bapX/**`, used by the admin/agent work.
- Five active PRs were drafts. Native reviewer defaults can skip drafts.
- Codex completed the current head of #240, but its response to a user-authored
  review request on #245 explicitly reports exhausted code-review usage. #243
  already had the same quota failure. These are not successful reviews or
  evidence of actionable code findings.
- Sourcery documents that bot-authored command comments are ignored. A mention
  posted with the Actions bot does not repair that integration.
- A manual human request was sent to all three providers on #245. Codex reported
  the quota block; no current-head Sourcery/CodeRabbit completion was observed.
  Lack of a response does not prove an app is uninstalled.
- The inspected connection can push but cannot administer the repository.
  No dedicated `REVIEW_REQUEST_TOKEN` secret was present. Unrelated secrets
  must never be reused for this job.

## Implemented repository controls

`.coderabbit.yaml` explicitly enables draft and incremental review, preserves
review-status reporting, and supplies PHP/security/375px UI instructions. It
was validated against the current official CodeRabbit JSON schema. It cannot
install the app, change its subscription or grant repository access.

`.github/workflows/ai-review-evidence.yml` listens for successful completion of
`Validate application` and supports an explicit manual metadata audit/request.
It checks out **only the trusted default branch**, never the PR head, does not
download PR artifacts, and never uses `pull_request_target`. Its job reads only
GitHub metadata. This avoids executing untrusted code with a write credential.

The dependency-free `.github/scripts/review-automation.cjs`:

1. Re-fetches authoritative CI and PR metadata, requiring an open same-repo PR,
   the exact current head SHA, a successful `ci.yml` pull-request run, and the
   expected workflow name.
2. Separately recognizes current-head formal review receipts, known Codex
   structured receipts, in-progress native checks, and recent provider quota
   messages. `COMMENTED` or `CHANGES_REQUESTED` is not approval.
3. Requests missing providers only with an explicitly configured real-user
   `REVIEW_REQUEST_TOKEN`. The token is restricted to this repository and its
   purpose; it is not read from project files or logged.
4. Claims each provider/current SHA with a separate trusted marker comment, then
   posts the provider command as its own literal comment:
   `@codex review`, `@sourcery-ai review`, or `@coderabbitai review`.
   Current-head markers prevent repeat dispatch after an interrupted batch. A claim
   without a delivery update is labeled delivery unknown, not requested/reviewed.
5. Rechecks PR state/head immediately before each command. Forks, stale CI,
   unknown workflow results, forged markers and bot actors cannot trigger it.
6. Uses the normal Actions token only for a clearly labeled status note, never
   to impersonate a human review request. Workflow concurrency serializes manual
   and CI-triggered runs. No auto-merge, branch protection, installation or
   subscription change occurs.

A 24-hour quota-warning cooldown avoids repeatedly requesting a blocked provider.
It does not predict when a provider's entitlement resets; later retries remain
subject to provider policy. Posted comments are labeled **requested, not reviewed**.
A successful coordinator job means metadata coordination ran, not that providers
approved the PR. Do not make this job alone a required approval signal.

## Native-app activation and remaining prerequisites

For native automatic review, a repository administrator must enable the installed
reviewer app for this repository, the desired base branches and draft policy.
Codex auto-review is managed in its own settings; API/ChatGPT access does not
remove code-review quotas. CodeRabbit must have sufficient existing entitlement.
Sourcery draft triggers require its dashboard setting, and explicit command
requests need a human author. No paid seat or credits are purchased by this patch.

For the post-CI command path, an administrator can create a **dedicated,
least-privilege real-user** token with access to this repo's PR comments, store it
as the `REVIEW_REQUEST_TOKEN` Actions secret, and validate its identity. The
workflow intentionally does not silently fall back to `GITHUB_TOKEN` or an
unrelated existing secret. Without this prerequisite it displays the block.
Do not paste token values in issues, logs, terminal output or source files.

The coordinator workflow must first be reviewed and merged to the default branch.
Then run its manual `audit` mode for an existing PR, inspect exact-head receipts,
and use `request` mode only for eligible missing reviews. A new successful CI
run will use the same path. External setup/quota blocks remain outside repo code.

## Verification

```sh
node --test tests/review-automation.test.cjs
./bapXphp update
./bapXphp ci
```

Tests cover stale/failed CI, forks, closed PRs, real-user requirement, forged
receipts/markers, current vs stale reviews, quotas, partially dispatched batches,
head races, secret-safe HTTP errors and privileged-workflow checkout safety.
Node is CI-only tooling; PHP hosting has no new Node runtime dependency.

## Official references

- [Codex GitHub review](https://developers.openai.com/codex/integrations/github/)
- [Sourcery review commands and bot-author limitation](https://docs.sourcery.ai/reviews/commands/)
- [Sourcery review settings](https://docs.sourcery.ai/reviews/configure/)
- [CodeRabbit configuration reference](https://docs.coderabbit.ai/reference/configuration)
- [CodeRabbit schema](https://coderabbit.ai/integrations/schema.v2.json)
- [GitHub workflow_run security](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#workflow_run)

## Ready-for-review default

The branch-PR workflow now explicitly uses `draft: false`, including `bapX/**`
and `feature/**` implementation branches. This enables review without pretending
that incomplete work passed validation. Current-head CI, source review and
affected browser/database tests still gate merge. Existing drafts are marked
ready only when the owner asks; no code silently bypasses failed checks.

## Status-note publication failures

The first activated workflow correctly classified quota and missing-token blocks,
but failed while posting its evidence note. The coordinator now explicitly has
PR-comment write scope, preserves a permission-denied audit in the workflow
summary, and reports `not_published` with the HTTP status instead of implying a
comment exists. It still does not suppress server/transport failures or disclose
upstream response bodies/tokens. A manual read-only audit can inspect a merged
PR's exact head, but can never request a reviewer on a closed PR. This permits
post-release verification without manufacturing another PR or approval.
