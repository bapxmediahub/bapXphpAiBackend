## Read each metric in its own scope

Agent Monitoring separates Support and Enterprise activity. Request errors, provider-attempt errors and tool-call errors have different denominators. A retry is a provider attempt, not another customer conversation. Timing includes mean, median and 95th percentile for the displayed operational window.

## Inspect a conversation

Recent conversation rows show completed turns and unfinished requests. Expand execution evidence or use the authorized JSON export to inspect the trace. **Produced** means the server produced a response; it is not proof the browser received it. Unknown provider usage and source freshness are not zero.

Unfinished work may be running or interrupted. Check its evidence before retrying any side effect. Keep exported conversations private.

## Understand quality and feedback

The offline golden fixture score checks specific known decisions. A passing score is not universal answer accuracy, customer satisfaction or a search-ranking measurement. The review queue flags negative feedback, failed guardrails and unfinished work from the bounded recent sample.

Only explicit positive customer confirmation marks an AI answer resolved. Unrated, negative and escalated cases stay distinct. Follow the human support queue for case ownership and response work; the sample is not an all-time backlog.
