---
type: doc
title: Developer Harness and Runtime Business Agent Boundaries
description: Separate private developer skills from the role-scoped live PHP business-agent Markdown and its evidence hashes.
category: docs
---

# `.agents/` is development; `.bapX/` is business runtime

`AGENTS.md` and `.agents/skills/*/SKILL.md` guide external coding harnesses
(Codex, DeepSeek Harness, Z-Code, Antigravity and IDE agents). They describe
repository navigation, tests, Git and deployment. They are **never prompt
inputs for a storefront customer or the live Enterprise Agent**.

`.bapX/` is the live business instruction tree, loaded by the existing
`AgentInstructionService`. It contains client-specific business rules without
Git, code-generation or developer CLI instructions:

```text
.bapX/
  supportagent.md
  enterpriseagent.md
  bapXcl/
    skill.md
    support/
      catalog.md
      policy-and-tickets.md
    enterprise/
      reporting.md
```

## Exact loading contract and selective activation

| PHP surface | Required files | Permission |
|---|---|---|
| `support` | `supportagent.md`, shared `bapXcl/skill.md`, two listed `support/` files | Guest or authenticated customer, with private-data scope enforced by the PHP tools |
| `admin` | `enterpriseagent.md`, shared base, the two support skills, `enterprise/reporting.md` | Server-authenticated `role=admin` or strict boolean `is_admin=true` |

The public Support path always selects `support`. The authenticated admin
controller selects `admin`; it does not trust a browser field or model text
to choose that surface. The loader supports only those two enums and a fixed
file manifest. Adding a Markdown file does **not** silently grant a new skill.
A new runtime module requires a reviewed manifest change and tests.

The fixed file manifest above defines the complete allowed set, not the initial
model context. The live model path uses RuntimeAgentToolRegistry, loading only
the role constitution and shared contract initially, plus the names and
descriptions of the allowed task skills. The model may call read_runtime_skill
with a catalogue identifier; related business lookups also load their matching
guidance automatically. This adapts the Agent Skills progressive-disclosure
pattern, with no arbitrary filesystem selector and no public transport
registration. Actual loaded paths and content hashes accumulate in the trace.
Business declarations use the same authenticated allowlist. A bounded lexical
match against tool names, descriptions and parameter names seeds up to three
business tools per turn. The `tool_search` meta-tool can enable up to four
matching declarations per search, with eight active business tools total; an
unknown exact identifier never activates a substitute. Both provider loops
refresh declarations each round while retaining correlated tool-call history.
The initial declarations cover common wording for the published shop total,
delivery charges, and total orders. Support can use the public catalogue or
shipping policy directly. Enterprise can request `order_count` with `all_time`
to count dated orders across all statuses. A model-selected direct call returns
the matching task guidance with its result in the same tool exchange.
Discovery returns metadata, not records. The full role-filtered tool metadata
catalogue is visible to the model; lexical seeding only prioritizes declarations.
A fresh free-form turn has no server-selected business lookup or answer bypass.
Trace spans record safe activation names and counts, never the raw search text.
The old forSurface() method remains the full-bundle offline/legacy contract.
 `manifestForSurface()` returns
only version, surface, relative paths, per-document SHA-256 and the bundle hash.
It is a PHP service API, **not a public route**. Required documents must be
UTF-8, nonempty, regular files no larger than 12 KiB each or 48 KiB per bundle.
Missing files, symlinks in the path, outside-root resolution and unauthorized
admin access fail closed. The service does not fall back to `AGENTS.md`,
generated indexes, arbitrary URLs, model-supplied paths or recursive globbing.

The trace fingerprints the **actual loaded Markdown bundle**, not just the PHP
loader file. Explicit UI commands which never load an instruction document leave
that trace field unset rather than inventing a loaded instruction. Offline
evaluations record both role manifests and include their content hashes in the
implementation fingerprint; the explicit admin actor there is a local test
fixture, not a production browser privilege grant.

The shared registry adds the published deployment display name as a JSON data
value. Both agents can retrieve the corresponding public contact projection,
which also feeds the storefront and external MCP. Owner credentials, private
settings and developer instructions remain outside that projection.

## Provider availability

Both agents use the shared AiClient synchronous fallback loop. Ordinary transient
failures never reorder the next request. A recorded quota or positive provider
retry delay can temporarily exclude that model; disabled/unconfigured models
are not called. Explicit diagnostics retain actual transfers. Tool exchanges
stay on their originating model. Owner capability and help questions use the
model, including messages typed as `/help` or `/capabilities`. The authenticated
Help page and the task disclosure give the owner direct access to the guidance.
See [agent observability](agent-observability.md) for evidence and budgets.

## Native processing loop: three RAG layers

See the README processing diagram. Every fresh free-form question uses the shared bounded
AiClient loop through RuntimeAgentToolRegistry. The model interprets the question,
chooses retrieval and tools, examines results, and synthesizes the response.
PHP handles authorization, typed input validation, deterministic calculations,
execution and presentation. It does not select a canned answer.

1. Knowledge RAG: retrieve actor-owned context, approved public facts, current
   business-service results and bounded published article bodies selected by the model. Revalidate identities and
   scope. General authorized MD/JSON excerpts and flexible schema-aware queries
   remain separate work.
2. Instruction RAG: load the role constitution and shared contract; discover
   fixed skills associated with the current request's eligible tool names and
   activate an allowed document by ID. Record its
   actual path/hash. Never use developer rules as runtime business guidance.
3. Tool RAG: rank capabilities allowed by the actor and public module settings,
   activate bounded declarations
   and refresh them each provider round. An explicit Enterprise `/help`
   command advertises the owner-only `operator_guide` in the first round. The
   model chooses the guide topic and writes the answer using the returned
   content. Validate each call before invoking the existing service and return
   its structured result or explicit failure.

Both discovery layers use the same eligible business tools. Disabling Shop
removes public catalogue and browser-cart capabilities from Support, including
the standalone catalogue skill. Disabling Blog removes published article
capabilities from both agents. Contact and enquiry guidance remain available.
If the public module settings are unreadable, capabilities that depend on
them are withheld for the request. Owner product status and reporting use the
owner reporting skill and remain available to the owner independently of
storefront visibility. Skill discovery follows the limited callable subset
pattern from [OpenAI's function calling guide](https://developers.openai.com/api/docs/guides/function-calling)
using the application's local PHP registry.

The public WebMCP and HTTP MCP surfaces retain separate authorization. A shared
authorized .bapX projection is the target, not an existing permission to read
private files. Installer work is deferred until core verification is complete.

## Security and storage boundaries

Both instruction trees and root `AGENTS.md` are denied to direct HTTP access
before PHP's development server serves static files, and by the production
root/directory `.htaccess` rules. The local routing guard also checks encoded
path forms. These rules complement—not replace—deployment server configuration.

This is application-level file selection, **not a new OS sandbox**. It prevents
Support from selecting Enterprise files through this loader; it does not make
all prompt injection or privilege escalation impossible. `AgentCapabilityPolicy`,
scoped tools, account ownership, CSRF, output handling and tests remain required.
Documents are instructions/evidence, never authority to expand a tool allowlist.

MySQL remains authoritative for business records. Each support/admin chat still
uses one private JSON file under `storage/agent-conversations/`; neither runtime
instruction tree changes that storage contract. No shell, arbitrary SQL or
unrestricted file read/write tool is added to either model.

## Client forks and maintenance

Reuse the developer workflow contracts; customize reviewed `.bapX/` business
rules for each client. Do not hardcode stale prices, stock, refund promises or
retired booking actions into generic skills. Exact current facts come from
approved application data. The generated developer index lists runtime file
**references** with type `runtime_instruction`, separately from developer
`skill`/`agentfile` entries, without duplicating prompt bodies. Live agents do not consume the generated developer index as instructions. The role-filtered runtime catalogue binds the existing .bapX concepts to executable business tools.

Run `php tests/run.php`, `php cli/agent-eval.php`, `./bapXphp update` and
`./bapXphp ci`. Verify both role manifests, unauthorized/path/symlink denial,
actual prompt content hashes, ordinary Support and admin flows, and HTTP denial
for both trees before releasing.

## Next client implementation checklist

See [client-runtime-adaptation.md](client-runtime-adaptation.md) for the exact
reuse/customization boundaries. `.bapX/` isolation is implemented; automatic
multi-client provisioning is not. PHP policy constants, model-prompt branding,
mail templates and per-client data/secrets need explicit review as well as
Markdown changes. Never copy live customer records or credentials into a fork.

## Offline developer contract generation

`cli/agent-contracts.php` runs only in CLI and selects an explicit synthetic
admin role to enumerate the protected Enterprise manifest. It emits paths and
content hashes, never runtime prompt bodies. This does not grant production
browser access. Uncaught CLI exceptions fail with a nonzero exit code and a
bounded class/file diagnostic; they cannot masquerade as successful HTML output.

## Configured provider routing

Admin → Integrations independently enables Google, OpenRouter and the custom
OpenAI-compatible connection. SecretService supplies ordered configured model
IDs. Disabled or unconfigured connections are not hidden fallbacks. AiClient
uses one bounded deadline, preserves correlated tool history and never switches
provider after a tool exchange begins. Google uses parametersJsonSchema on the
wire. Text response and synthetic tool challenge are separate diagnostics; an
offline fixture or HTTP status does not establish successful live inference.
See [Integrations](pages/integrations.md).

## Provider-neutral model decisions and tool execution

The native PHP runtime implements the function-calling pattern documented by OpenAI:
send authorized tool schemas, receive model-selected calls, validate and execute
them, correlate each result with its original call, and invoke the model again.
Google uses its native function declaration and function response format;
OpenRouter and configured OpenAI-compatible connections use Chat Completions.
Both adapters use the same PHP execution gate and role-scoped registries.

Both native chat controllers build an `AgentModelTurn` from authorized inputs.
Fixed application guidance and the response-format instruction use Gemma REST
`systemInstruction`, or the Chat Completions instruction role. Direct OpenAI
uses `developer` for the recognized GPT-5, GPT-6, o1, o3 and o4 families.
Other Chat Completions connections and legacy direct OpenAI models use
`system`. The endpoint and model remain determined by the enabled provider plan.

The adapters send bounded successful history as chronological `user` and
`model` (Gemma) or `assistant` (Chat Completions) messages. The current
`user` message contains the question and a `reference_data` object for
server-derived context. Prior product identities, report scope, site identity
and owner-resolved attachments stay in data fields. Referents help resolve a
follow-up and require an authorized current lookup for current business facts.
Earlier tool calls and internal reasoning are absent from saved dialogue.
Tool-call correlation and provider continuation fields apply within the
current request only. Text-only and synthetic diagnostic calls continue to
use their existing one-shot prompt path.
[Gemma system instructions and multi-turn requests](https://gemma.ai/deployment/gemini-api/#system-instructions) ·
[OpenAI conversation state](https://developers.openai.com/api/docs/guides/conversation-state#manually-manage-conversation-state) ·
[OpenAI Chat Completions roles](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create).

A batch can contain multiple calls. Its call identifiers must be valid and unique
before any member executes. The advertised declaration snapshot controls the
current round; `tool_search` activates new schemas only on subsequent model
rounds. An invalid or unauthorized request returns a safe tool error for the
model to handle. Explicit incomplete finishes and tool calls at the final-answer
boundary are errors, not successful answers. Where returned, compatible-provider
continuation fields are retained in bounded request-local wire history on the
same locked provider connection; they are not rendered or persisted.

OpenAI-compatible text endpoints are not automatically tool-capable models.
Each enabled model needs a successful live tool round trip. The portable
`tool_search` and `read_runtime_skill` functions are application-owned, not the
OpenAI Responses API built-ins. Responses-only models and hosted tools need
a separate protocol adapter; this revision does not claim that support.
Do not force strict schemas or provider-specific optional flags universally.

References: [OpenAI function calling](https://developers.openai.com/api/docs/guides/function-calling),
[OpenAI tool search](https://developers.openai.com/api/docs/guides/tools-tool-search),
[OpenAI Chat Completions to Responses migration](https://developers.openai.com/api/docs/guides/migrate-to-responses),
[DeepSeek thinking-mode tool calls](https://api-docs.deepseek.com/guides/thinking_mode/),
[OpenRouter tool calling](https://openrouter.ai/blog/tutorials/tool-calling/).

Support retrieves product selections through model-selected catalogue and exact
product-detail tools. Missing, hidden or ambiguous identities must not be filled
with unrelated products. Previous displayed slugs are referents, not current facts.
Public A2UI follows actual retrieved identities rather than question regexes.
Enterprise uses `operator_guide` when the model needs instructions about an
admin task. The task disclosure lists approved owner operations and lets the
owner fill the composer with a question. Chat submissions use the model-led flow.

The public STDIO MCP policy contract retains its original four keys. Native
Support can also retrieve domestic/international delivery estimates and retired
consultation policy. Native-only additions do not expand the public MCP surface.

`commerce_report` is an Enterprise-only read tool over the existing `StoreStatsService` helpers. It accepts the fixed `products`, `stock`, `customers`, and `coupons` report names, rows limited to 1–10 (default 6), and inclusive dated reports of at most 366 days through today. Stock rejects dates because it is a current status snapshot. Customer output is anonymous order-frequency groups; customer identities never enter this tool result. The actual result, rather than model text, defines metrics, source, scope and bounded saved report evidence. See [agent observability](agent-observability.md) for the private evidence projection.

Product net values allocate recorded refunds across lines; coupon values are associated order totals, not causal attribution; customer repeat means within the selected period. No profit, on-hand quantities, lifetime retention or live booking is implied. `.bapX/bapXcl/enterprise/reporting.md` supplies these meanings to the model without extending Support or the public MCP tool surface.

The application executes each allowed function and sends its result back using the originating provider's call format. Model text is never authority to grant a capability. Google documents the hosted Gemma identifiers and native function calling. OpenAI recommends clear tool schemas and doing known work in application code. Those contracts apply to their respective clients. [Google AI Studio function calling](https://aistudio.google.com/docs/function-calling) · [Hosted Gemma models](https://gemma.ai/deployment/gemini-api/) · [OpenAI function calling](https://developers.openai.com/api/docs/guides/function-calling)

The default Google-first order is gemma-4-31b-it, gemma-4-26b-a4b-it,
then openrouter/free when enabled and configured. The preferred connection and
its model list are editable; OpenRouter is the final enabled connection.
For each recoverable inference failure, send the identical payload up to three
times on the same model, with 1s then 2s backoff plus jitter; after the third failure wait 4s plus jitter before advancing. If all eligible
models fail, restart from the first eligible model for a second cycle. Stop
immediately on successful inference. Explicit authentication, invalid-request,
missing-model, TLS, explicit daily/zero quota, quota without a short retry hint, and long Retry-After failures do not get blind
retries. An observed HTTP 401 excludes the models sharing that connection's
credential for the rest of the turn. Other connections can still recover before
a tool exchange begins. The owner error names that connection's key field for
401 or its permissions for 403. Each request has a 110-second inference
deadline and at most 32 transfers; both browsers wait up to 120 seconds. The trace enforces a
115-second total execution limit and the existing observed-token budget.
Provider holds are scoped to the configured API key, endpoint and model. A replacement
key starts eligible, and delayed responses under the previous key update only that
key's health entry. The local version-2 health file stores HMAC identities and expiry
metadata. Unscoped legacy entries are ignored. Each transfer rechecks current provider holds.
A short hold can expire within the turn's remaining budget. Longer holds skip
the candidate before recording a transfer. Tool exchanges remain on their original
model, and an explicit integration diagnostic bypasses these hold checks.
The deadline or budget can stop a cycle early. Once tool exchange starts,
three-attempt recovery resends only the correlated inference request to the
same model, never repeating completed PHP tools or switching the exchange
to another provider. A failed exchange is an explicit error, not a canned
business answer. [Google API troubleshooting](https://ai.google.dev/gemini-api/docs/troubleshooting)
· [OpenAI error codes](https://developers.openai.com/api/docs/guides/error-codes)
· [OpenAI SDK retries](https://github.com/openai/openai-python#retries)
· [Vercel AI SDK settings](https://ai-sdk.dev/docs/ai-sdk-core/settings)
· [OpenRouter Retry-After](https://openrouter.ai/docs/api_reference/errors-and-debugging)

## Model-driven Support and Enterprise tools

Both model surfaces reuse AiClient's bounded provider loop and provider fallback
rules. Support tool declarations hide private account lookups from guests and
never expose owner reports. Current public catalogue totals, configured
category membership and all-time published bestseller ranking are retrieved
from the actual store services when the model selects the corresponding tool.
Support uses previous answers to resolve references. The current turn's
successful `public_catalogue_summary` must support recognizable numeric
whole-shop claims in the proposed reply. The application withholds missing
or conflicting claims and links to the Shop when it is available. This check covers common
English and Tamil sentence forms. It does not parse unrestricted prose,
numbers written as words, or arbitrary category counts.
Support retrieves orders and tickets only for the signed-in customer's account.
Either a guest or a signed-in customer may read their current browser-session
cart while Shop is enabled. Cart access does not establish an account or a
completed order.
Enterprise can separately count all dated orders or request paid-sales reports.
Both roles can search published article titles, summaries and bodies, then
read an exact public slug with optional topic keywords. A targeted lookup
selects a bounded passage from the article's matching section. A slug-only
lookup returns the opening. PHP applies the same Blog and publication gates to
both calls. Retrieved passage text is evidence for the current tool exchange.
A provider failure is an error; a failed query does not become an empty catalogue
or invented ranking. A model-selected contact form proposal never submits a form.
This slice does not add autonomous workers, embeddings, a vector database,
arbitrary SQL, or another production runtime.

References: https://prismphp.com/core-concepts/tools-function-calling/ ,
https://docs.neuron-ai.dev/agent/tools ,
https://github.com/agentskills/agentskills/blob/main/docs/client-implementation/adding-skills-support.mdx .
These patterns are implemented natively; no external PHP package is installed.

### Provider error evidence and retry timing

Google REST responses may include typed google.rpc.RetryInfo and QuotaFailure
details. Decode the documented ProtoJSON duration, rounding up to milliseconds,
and use the larger of the body delay and Retry-After header. A known daily or
explicit zero quota is not retried within the same turn. A short positive
provider delay (up to the existing eight-second inline policy) can be retried
inside the bounded loop. Longer delays hold that model; other eligible models
retain their saved order. Across turns a positive provider delay sets model
eligibility up to the 24-hour local cap. Without a positive delay, the
application keeps its existing fallback intervals. The stored expiry rounds
up to the next second. Ordinary transient observations do not extend an
active provider hold. Do not infer a daily quota reset time. Provider/model
holds are deployment-local and explicit diagnostics can bypass them. Already advertised functions may be called directly; tool_search remains
available for non-advertised authorized functions. No deterministic answer path.

See [Google RetryInfo](https://github.com/googleapis/googleapis/blob/master/google/rpc/error_details.proto),
[ProtoJSON](https://protobuf.dev/programming-guides/json/) and
[HTTP Retry-After](https://www.rfc-editor.org/rfc/rfc9110.html#name-retry-after).
