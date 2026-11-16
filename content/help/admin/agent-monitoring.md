## Read each metric in its own scope

Agent Monitoring separates Support and Enterprise activity. Request errors, provider-attempt errors and tool-call errors have different denominators. A retry is a provider attempt, not another customer conversation. Timing includes mean, median and 95th percentile for the displayed operational window.

## Inspect a conversation

Recent conversation rows show completed turns and unfinished requests. Expand execution evidence or use the authorized JSON export to inspect the trace. **Produced** means the server produced a response; it is not proof the browser received it. Unknown provider usage and source freshness are not zero.

Unfinished work may be running or interrupted. Check its evidence before retrying any side effect. Keep exported conversations private.

## Understand quality and feedback

The offline golden fixture score checks specific known decisions. A passing score is not universal answer accuracy, customer satisfaction or a search-ranking measurement. The review queue flags negative feedback, failed guardrails and unfinished work from the bounded recent sample.

Only explicit positive customer confirmation marks an AI answer resolved. Unrated, negative and escalated cases stay distinct. Follow the human support queue for case ownership and response work; the sample is not an all-time backlog.

## Record an answer-quality review

Select **Review answers** beside a conversation, or **Review recorded answer** in the flagged queue. Choose the completed turn, then compare the request and answer against the expandable recorded sources. Unknown freshness or missing tool data is not proof of correctness.

Assess **Factual accuracy**, **Support from recorded sources**, **Answers the actual request**, and **Policy and privacy boundaries**. Choose **Not assessed** when evidence is missing. Select the evidence used and any correction/missing-information reasons, then explain the decision without passwords or private customer details.

Declare how the review was authored. Use **Personally checked by a staff member** only when you actually compared the response and evidence and can attest to that. AI-assisted assessments and synthetic tests must use their own options; they are not independent human-quality labels. Select **Save answer review** to append the decision. If the page reports that another review or evidence changed, reload and inspect the latest history before saving again.

The decision history preserves earlier reviews, and your label does not rewrite the response, customer feedback or ticket status. Human, AI-assisted and test counts can overlap for a turn. The recent review sample is not a production accuracy score, and a staff pass cannot turn an unrated customer conversation into confirmed resolution. Corrections to content or business rules still use the normal authorized workspace and testing workflow; this form does not retrain or publish anything.
