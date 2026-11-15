# Operational evidence, retrieval quality and client adaptation

Goal: verify #22/#25/#251 against current code and live evidence, implement observed defects, and document a safe next-client `.bapX/` workflow without falsely closing external acceptance gates.

Constraints: pure PHP; current MySQL business store; one JSON per conversation; no new paid service; no live payment, fake paid order, credential reset or historical bulk email replay. The recorded owner decision to keep the admin password remains binding until explicitly changed. PRs are ready for review, never draft; merge only after tests/CI and required browser checks.

## Confirmed baseline
- Main 77fb7dc; local suite 202/202 passed.
- #22 production read: one app_settings row, bcrypt, no plaintext credential. Rotation was explicitly deferred.
- #25 immediate SMTP sending exists. Four order confirmations have legacy sent status; no receipt in the connected Gmail search and no order belongs to the authorized development customer. Gateway is live, so no paid-flow claim or synthetic paid mutation.
- Most historical mail failures classify as provider-limit errors. Do not replay backlog.
- Code defect: post-DATA acceptance can be misclassified as failure on QUIT or audit write, and mail status writes replace whole collections. Add durable per-row claim/outcome and separate acceptance from inbox receipt.
- #251 still uses first-20-product fallback for unmatched noncollection product requests and incomplete provider/tool denominators. Improve exact retrieval evidence and representative deterministic fixtures; keep open wherever acceptance remains unproved.

## Tasks
- [x] Write red tests for SMTP header boundaries, acceptance/QUIT behavior, per-row delivery state and concurrent claim guards.
- [x] Implement bounded native SMTP acceptance receipts, explicit unknown outcome, per-row mail delivery claim/complete and idempotent paid-order mail identities. Test against isolated socket/MySQL fixtures, never production backlog.
- [x] Expose honest outbox health and failure reasons; update email documentation including pending-only processing and receipt limitations.
- [x] Add deterministic public catalogue/no-match/ambiguity selection and source references, broaden golden cases, and add p50 plus provider/tool rate denominators in existing monitoring.
- [x] Review exact runtime-agents.md and add source-grounded client-fork checklist: approved business rules, isolated DB/secrets/storage, no copied customer data, fixed file-role manifest, before/after hash/fixture checks.
- [ ] Run local, CI and browser verification; update issues with exact completed/pending acceptance evidence. Do not close #22 without rotation consent, #25 without actual order receipt, or #251 with unmet acceptance.

Verification before PR: local PHP regressions pass (212 tests), golden dataset
27/27, four loopback SMTP protocol cases and four desktop/mobile browser cases.
The browser found a real spaced product slug; preservation of spaced/Unicode
public slugs and encoded evidence is now regression-tested. Historical mail
backlog and production credentials remain unchanged. CI-only MySQL concurrent
claim tests and final deployed checks must pass before merge/closure claims.
