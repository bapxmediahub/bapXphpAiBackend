---
type: doc
title: Native Agent Quality Acceptance Evidence
description: Source-specific implementation progress and remaining closure gates for issue 251, without confusing passing guardrails with live accuracy.
category: docs
---

# Issue #251 acceptance ledger

This ledger describes the code in this revision. It is not a blanket closure of
issue #251 or the wider REA feature list.

| Acceptance area | Implemented evidence | Remaining limitation |
|---|---|---|
| Retrieval/intent | Exact public aliases, actual collection membership, known Tamil aliases, explicit browse/no-match/clarification decisions, account/policy routing | Not arbitrary-language semantic retrieval; some enterprise queries still scan PHP arrays |
| Correlated evidence | Actual span boundaries, role instruction hashes, effective tool filters/result hashes, public product refs/update dates and local source-link checks in the same JSON turn | Unknown source timestamps remain unknown; not every private record/claim has revision-level lineage |
| Outcomes/human review | Explicit feedback, confirmed resolution vs unrated/negative, idempotent handoff, staff assignment/reply/reopen, bounded flagged-chat review queue | No silently inferred assumed resolution or abandonment; no structured per-answer human adjudication workflow |
| Evaluations | 27 versioned exact decision/source fixtures, existing owner/security/GST/concurrency regressions, implementation/manifest fingerprints | Not a model benchmark; broad factual correctness and representative multilingual/claim-level human labels remain unverified |
| Monitoring | 24-hour mean/P50/P95; request errors, provider-attempt and tool-call error denominators; recent flagged-chat topic/reason with authorized JSON links | Recent-40 conversation sample is not a site-wide longitudinal quality report |
| Engineering gates | Native PHP, no paid judge or new SQL conversation table; isolation, interrupted-action and browser checks | Release and production QA must be recorded for the exact merged revision |

Do not close the issue merely because a no-secret or nonempty-response check
passes. Remaining work includes representative human-reviewed answer datasets,
structured adjudication/knowledge-gap follow-through, fuller freshness lineage
and domain-specific aggregate-query scalability. Future outcome inference must
be explicitly defined and separately labeled; it must not turn silence into
confirmed customer success.
