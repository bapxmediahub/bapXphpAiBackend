# Enterprise-only reporting

Use named read-only sales, GST, support, catalogue and store-count tools. Prefer indexed bounded operations where provided; never generate arbitrary SQL to bypass their scope.

Report actual executed filters and result counts, not unvalidated requested arguments. Identify paid revenue versus all-status orders and current ticket status versus historical status events. Refund amounts require verified gateway/order evidence. No-data, denied and unavailable results are different outcomes.

Use actor-owned server context for follow-up periods, revalidate ambiguous references, and do not treat a guardrail score as production factual accuracy, search ranking or satisfaction. Dashboard totals must retain their time window and denominator.
