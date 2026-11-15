---
type: doc
title: Agent Evidence Implementation Follow-through
description: Scoped implementation from the REA findings, with observable execution timing and pre-execution private JSON checkpoints.
category: docs
---

# Scope and verification

Continue the preserved #241 work rather than replacing it. Close the immediate
trace and persistence defects found by REA: verified effective tool scope,
actual span start/end events, safe provider usage, a private JSON checkpoint
before model/tool execution, and actor rejection before side effects. Keep
one JSON file per chat and retain completed/pending evidence across failure.
Do not auto-replay an uncertain side effect or call a produced response delivered.

Add regression tests first, wire both existing controllers/services, expand
versioned golden tests, update the operational documentation and skills, then
verify authenticated desktop/375px flows with the existing test fixture and
remote business data. Product inventory/cart/fulfillment expansion remains
separate from these runtime repairs; research is not implementation proof.

Release checks remain ordered: #245, #247, #249, then #241; #250 requires a
successful disposable-media browser deletion with database evidence. #240 is
left for the human team. External reviewer quotas/settings do not become a
false pass and no paid dependency is introduced.
