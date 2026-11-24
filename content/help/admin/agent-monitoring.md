## Read each metric in its own scope

Agent Monitoring separates Support and Enterprise activity. Request errors, provider-attempt errors and tool-call errors have different denominators. A retry is a provider attempt, not another customer conversation. Timing includes mean, median and 95th percentile for the displayed operational window.

## Diagnose a failed answer

Expand **Recent runs** and read the provider attempts in order. **Transfer timed out** with cURL 28 means that the application reached its transfer timeout. **No HTTP status** means the application recorded no response status. A first-byte timing, when available, tells you when the first response data arrived. Check the host connection and elapsed time before deciding where the delay occurred.

**Upstream service error** means an HTTP 5xx response arrived. Check the provider status page for that time. For HTTP 429, check rate limits and quota before retrying. **HTTP success** can still accompany an empty answer or a failed application check. Inspect the run outcome and tool results as well as the transfer result.

**Local request deadline reached** and **Local provider budget reached** mean the application stopped additional work. The conversation trace records the limit where available. A denied attempt adds no provider error. Check unfinished ticket actions before retrying a conversation.

For a provider support case, record the timestamp, model, HTTP status and observed timing. The displayed run reference belongs to this application. OpenAI uses its own response header, `x-request-id`, which this monitor does not collect. A timeout can occur before that header arrives. [OpenAI request debugging](https://developers.openai.com/api/reference/overview#debugging-requests) · [OpenAI error codes](https://developers.openai.com/api/docs/guides/error-codes) · [Google Gemini troubleshooting](https://ai.google.dev/gemini-api/docs/troubleshooting)

## Inspect a conversation

Recent conversation rows show completed turns and unfinished requests. Expand execution evidence or use the authorized JSON export to inspect the trace. **Produced** means the server produced a response; it is not proof the browser received it. Unknown provider usage and source freshness are not zero.

Unfinished work may be running or interrupted. Check its evidence before retrying any side effect. Keep exported conversations private.

## Understand quality and feedback

The offline golden fixture score checks specific known decisions. A passing score is not universal answer accuracy, customer satisfaction or a search-ranking measurement. The review queue flags negative feedback, failed guardrails and unfinished work from the bounded recent sample.

Only explicit positive customer confirmation marks an AI answer resolved. Unrated, negative and escalated cases stay distinct. Follow the human support queue for case ownership and response work; the sample is not an all-time backlog.

Open a row under **Support outcomes by topic** to see how many chats requested more help, escalated, received confirmation, or await feedback. Each Support chat in the latest 40 conversations contributes one current state under its latest completed answer’s topic. Unclassified includes chats with no recorded topic.

## Record an answer-quality review

Select **Review answers** beside a conversation, or **Review recorded answer** in the flagged queue. Choose the completed turn, then compare the request and answer against the expandable recorded sources. Unknown freshness or missing tool data is not proof of correctness.

Assess **Factual accuracy**, **Support from recorded sources**, **Answers the actual request**, and **Policy and privacy boundaries**. Choose **Not assessed** when evidence is missing. Select the evidence used and any correction/missing-information reasons, then explain the decision without passwords or private customer details.

Declare how the review was authored. Use **Personally checked by a staff member** only when you actually compared the response and evidence and can attest to that. AI-assisted assessments and synthetic tests must use their own options; they are not independent human-quality labels. Select **Save answer review** to append the decision. If the page reports that another review or evidence changed, reload and inspect the latest history before saving again.

The decision history preserves earlier reviews, and your label does not rewrite the response, customer feedback or ticket status. Human, AI-assisted and test counts can overlap for a turn. The recent review sample is not a production accuracy score, and a staff pass cannot turn an unrated customer conversation into confirmed resolution. Corrections to content or business rules still use the normal authorized workspace and testing workflow; this form does not retrain or publish anything.

In **Conversation audit & evaluations**, expand **Failed tool calls** to find a run reference from chat and open its exact turn. This list uses the private chat record and remains available if the operational audit write failed. It shows up to five completed turns with tool errors in each of the latest 40 conversations.

A temporary provider cooldown is a local routing outcome after an observed transient transport failure. It is not a new failed transfer. Use Integrations for an explicit model diagnostic; the diagnostic bypasses cooldown. Request-rejection hints are bounded indications to inspect tool schemas/history or model settings, not confirmed diagnoses. Never retry an interrupted business action simply because a model was unavailable.
