Refs #<!-- existing issue number -->

## Scope and evidence
Describe the observed defect, source SHA, root cause and exact issue scope.
Unrelated findings must have their own GitHub issue, not a TODO document.

## Code / documentation cross-review
- [ ] Read the affected original code, not only generated indexes.
- [ ] Update implemented-behavior guides, developer skills and user help where relevant.
- [ ] Record affected `php cli/docs-check.php --record AREA --reason ...` receipts after review.
- [ ] Run `./bapXphp update`, `./bapXphp ci` and relevant isolated tests.

## Verification
Exact commands and results, browser desktop/375px flows, security/data boundaries.
List limitations honestly; no credentials or customer records.

## Release acceptance
- [ ] Ready for review, not draft; exact-head checks and findings examined.
- [ ] Live verification after merge is recorded on the originating issue.

Do not use closing keywords before live acceptance. A check/mention/SMTP reply
is not proof of a completed vendor review, customer resolution or inbox receipt.
