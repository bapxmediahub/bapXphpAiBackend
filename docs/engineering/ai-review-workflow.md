---
type: doc
title: AI Review Evidence and Post-CI Coordination
description: Safe current-commit reviewer coordination with explicit quota, installation and user-token prerequisites.
category: docs
---

# AI reviews: delivery is not approval

The repository's validation job and third-party AI reviewers are separate
systems. A successful `validate` check is not proof that Codex, Sourcery or
CodeRabbit reviewed the current commit. A trigger comment is a request, not a
completed review. A `COMMENTED` or `CHANGES_REQUESTED` review is not approval.

## Observed failure causes, 15 November 2026

- Five of the six existing open PRs are drafts. Sourcery and CodeRabbit skip
  drafts by default unless their respective settings enable draft review.
- #240 has a completed Codex receipt for its current head. Codex is therefore
  not universally disconnected. #243 and #245 explicitly report exhausted
  code-review usage; editing workflow YAML cannot remove that quota.
- Before this change there was no explicit post-successful-CI reviewer
  coordinator. The branch-PR workflow also omitted the existing `bapX/**`
  naming convention.
- Standalone `@coderabbitai review` and `@sourcery-ai review` comments were
  posted on #245 by the authenticated owner. Until the corresponding vendor
  publishes a receipt, their state is **requested / completion unverified**.
- Installed-app inspection was not authorized by the available GitHub token.
  Missing responses cannot prove an app is uninstalled. No app, subscription,
  credit purchase or credential replacement has been performed.

## Implementation

`.github/workflows/ai-review-evidence.yml` handles completed
`Validate application` PR workflows and an explicit manual audit/request.
It checks out only trusted default-branch coordinator code. It does not
checkout or execute PR-head code with privileged credentials and never
merges, approves or changes draft state automatically.

`.github/scripts/review-automation.cjs` validates repository, open PR, exact
head SHA and successful current-head CI. It checks vendor-authenticated formal
review receipts, Codex state metadata and genuine vendor check runs. Old-head
receipts and contributor-authored imitations cannot certify the new head.
The policy is covered by `tests/review-automation.test.cjs`.

Each missing reviewer gets a separate literal command comment, with a
provider/PR/SHA idempotency claim in a different comment. The claim is written
before the command; an interrupted claim is **uncertain**, not assumed sent or
reviewed, and is not blindly duplicated. The current head is checked again
before each request. Quota warnings impose a bounded, visible cooldown instead
of endlessly requesting the same blocked review.

The coordinator publishes an evidence summary with distinct states: current
head receipt, in progress, requested but unverified, provider-blocked, or
configuration-blocked. Failed dispatch never becomes a green approval claim.

## Activation prerequisites

1. Merge the reviewed workflow into the default branch; `workflow_run` uses
   that trusted version. A workflow file sitting only on a feature branch is
   not an active repository-wide trigger.
2. Authorize the desired native reviewer apps for this repository using their
   official settings. The repository cannot install itself into a vendor
   account, bypass quotas, or manufacture reviewer access.
3. CodeRabbit's `.coderabbit.yaml` enables draft/incremental review and visible
   review/commit status. It was validated against the vendor's official schema.
   Free-tier enablement does not promise every feature is free for every account.
4. Sourcery's repository/organization review settings must enable review,
   include drafts when intended, and not exclude the base branch/paths/labels.
   Those are vendor settings, not an invented `.sourcery.yml` schema.
5. Optional post-CI **command** dispatch requires a dedicated repository secret
   `REVIEW_REQUEST_TOKEN` belonging to an authorized real user. Restrict it to
   this repository and the minimum metadata/comment permissions it needs.
   Sourcery ignores bot-authored comments, so neither `GITHUB_TOKEN` nor an
   arbitrary GitHub App token is a working substitute for its trigger author.
   The default workflow token is used only for metadata and status notes.
6. No such dedicated token was configured during this change. An unrelated
   existing `FORK_SYNC_TOKEN` must never be reused, printed or copied. Obtain
   the dedicated token through the owner's normal credential-approval process.

The native reviewer apps may run without this optional dispatcher once their
own settings are correct. For strict **after-CI** coordination, enable the
workflow and dedicated user credential, then verify actual vendor receipts.
Avoid duplicate native/command triggers; the coordinator skips observed
current-head reviews or reviews already in progress.

## Testing and operation

```bash
node --test tests/review-automation.test.cjs
./bapXphp update
./bapXphp ci
```

After activation, manual audit can be invoked from GitHub Actions using the
`Coordinate AI review evidence` workflow with a PR number and `mode=audit`.
It reads evidence and posts a status note without triggering vendor commands.
`mode=request` requires successful current-head CI and the dedicated user token.
Inspect the workflow summary and vendor's own current-SHA result. Do not treat
successful coordinator execution as completion of a vendor review.

Before retrying an interrupted claim, inspect the actual command comment,
vendor status and quota. Remove only the relevant trusted claim when it is
proved not delivered; never suppress or erase code-review findings to make a
PR look green. Human review and the customer's release verification remain
mandatory.

## Official references

- [Codex GitHub reviews](https://developers.openai.com/codex/integrations/github/)
- [Sourcery commands and bot-author behavior](https://docs.sourcery.ai/reviews/commands/)
- [Sourcery draft and repository settings](https://docs.sourcery.ai/reviews/configure/)
- [CodeRabbit configuration](https://docs.coderabbit.ai/reference/configuration)
- [CodeRabbit machine-readable schema](https://coderabbit.ai/integrations/schema.v2.json)
