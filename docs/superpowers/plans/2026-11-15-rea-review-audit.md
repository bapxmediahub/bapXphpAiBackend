# REA evidence and post-CI reviewer repair

Goal: deliver a source-pinned, REA-backed missing-capability inventory and repair the missing explicit post-CI reviewer request path without misreporting quota-blocked or absent reviewers as successful.

Architecture: preserve the dirty #241 worktree; work from origin/main in a separate branch. Keep external reference code outside the repository; commit only provenance, selected citations, findings and reproduction commands. A trusted-default-branch workflow requests installed reviewers once per current PR SHA after the existing CI succeeds. It does not execute PR code with privileged credentials, automatically merge PRs, buy credits, install apps or repurpose unrelated secrets.

Constraints: native PHP app stays unchanged; one private JSON file per chat is retained. Review requests use a dedicated optional real-user REVIEW_REQUEST_TOKEN because Sourcery ignores bot comments; missing token is an explicit block. No paid service or secret is provisioned by this patch.

## Tasks
- [x] Verify REA command help, source imports, bounded JavaScript analysis and one credential-free public browser scenario.
- [x] Compare pinned DeepSeek Harness, MIT-core Langfuse/Chatwoot, Bagisto PHP and OpenHands frontend patterns with main, remote draft and local unfinished code.
- [x] Write failing tests for stale CI, forks, missing/human-only credentials, current-head bot receipts, quotas and duplicate requests.
- [x] Add CodeRabbit draft-review configuration validated against its current official schema.
- [x] Add trusted post-CI workflow and tested dispatcher; preserve exact bot commands and record requested vs reviewed separately.
- [x] Include bapX/** in branch-PR automation and document native-app prerequisites and quota handling.
- [ ] Test, regenerate maps, commit/push one isolated PR; probe real review requests and retain truthful results.

## Review focus
A successful CI run is not a successful review. A posted comment is not a completed review. Stale head SHA, fork code, forged marker comments and bot-authored Sourcery commands must not be accepted. Existing quota warnings must not cause blind repeat requests. Historical-reference/static REA facts must not be described as proof of runtime behavior; unselected/unsupported sources remain unknown.

Verification recorded: 30/30 reviewer-policy tests; 162/162 PHP tests; full repository CI and official CodeRabbit-schema validation passed. Report holds 27 gaps, 23 pinned source anchors and ten known REA evidence outputs. Operational activation remains blocked on default-branch merge, native app settings/entitlements and the optional dedicated human token; no completion is inferred from a trigger request.
