# Agent Operating Guide

The binding contract follows this orientation.

Codex and ChatGPT Work: follow **Testing**. The owner explicitly authorized Playwright CLI on the connected Mac and remotedb verification for this release workflow.


Quick orientation:
- Skills: canonical project skills live in `.agents/skills/<name>/SKILL.md`
- Hooks: `.claude/hooks/`
- Schema (canonical): `storage/schema/collections.php`
- UI (canonical): `Design.md`
- Wiring: `docs/systematic-map.mmd`
- Local browser-test credentials: `.env.test-user` (gitignored; never copy its values into tracked files or logs)

Before any change: `./bapXphp map && ./bapXphp schema list`.
Before pushing to `main`: `./bapXphp ci`, and confirm it is green.

The product has exactly two PHP agent surfaces: customer support and owner/admin chat.
Inventory of what actually exists (check before claiming a feature):
`docs/project-index.json` — generated, committed, drift-checked by `./bapXphp ci`.

Queryable project knowledge: `index.yaml`. It points to the original blogs, images,
code, documentation, maps and hosted `/remotedb` collections; it does not duplicate
blog bodies or runtime database records. Read `discovery` first and query narrowly;
do not load the complete generated index into agent context. Use a three-hop budget:
entry instructions → exact index match → original source. Avoid broad `ls`, recursive
globs and Git history unless the indexed target is absent.

- Model facts, including the AI model in use, live in `AGENTS.md`. Verify against a provider's own docs before saying a model or field does not exist.


### Mandatory live-agent decision boundary

Every fresh free-form question to Support or Enterprise must use model inference.
The model interprets intent and selects Knowledge RAG, Instruction RAG and Tool
RAG resources as needed, inspects returned results, and composes the response.
PHP validates role, arguments, evidence and operations; it must never substitute
regex-selected answers or deterministic report shortcuts for model decisions.
Owner-reviewed draft requests, user-confirmed forms and replay of an already
completed turn are separate operations. Typed help and capability questions use
inference. Report unavailable inference as an error. Preserve role-scoped access, budgets and private
JSON conversations. Regression coverage must exercise both controllers through
model-selected tool calls, including simple counts and provider failure.

## Binding contract

This file is the single binding contract for coding agents in this repository.

### Documentation-first implementation (binding)

- Before changing any integration, identify the official provider documentation,
  maintained SDK or reference implementation for the exact API/version. Use
  Google AI, OpenAI, Vercel AI SDK and OpenRouter documentation for their
  respective model/tool contracts; use native PHP equivalents where needed.
- Prefer the smallest implementation of the documented pattern in the existing
  PHP/MySQL/vanilla-JS architecture. No new framework, scheduler, queue,
  background service, provider or paid dependency merely to reproduce a feature
  documented by the current provider.
- Record the specific source URL, contract being implemented, local adaptation
  and executable regression in the GitHub issue/PR and relevant documentation.
  Distinguish official defaults from bapX product choices; do not claim universal
  compatibility from one scripted response.
- For integration defects, reproduce the documented minimal request first.
  Then test the actual feature, role boundaries, and deployment against the
  configured provider. A green fixture or CI run is not live model acceptance.
  Never change credentials, enable paid models or fabricate successful answers
  to make an acceptance test pass.


## Repository

- `bapxmediahub/bapXphpAiBackend` is the only working repository and the Hostinger
  deployment source. Issues, branches, PRs and releases all live there.
- The repository is independent and unforked. Do not add an upstream remote or
  synchronise from any other copy.
- Canonical project skills live in `.agents/skills/`. Hooks live in `.claude/hooks/`.
- `.agents/` and this `AGENTS.md` are **developer/coding harness instructions only**.
  Live business-agent instructions are fixed role-allowlisted Markdown in `.bapX/`,
  loaded only by `AgentInstructionService`. Support cannot load Enterprise files;
  do not turn developer skills or generated indexes into live prompt context.
  See `docs/runtime-agents.md`. Both private trees are denied to direct HTTP access.
- The product has exactly two PHP agent surfaces: customer support and owner/admin chat.

## White-label product contract (binding)

- Maintain a reusable native PHP/MySQL/vanilla-JS core for separately deployed
  bapX customers. Sri Panchami is a reference deployment, not the framework's
  generic identity. Preserve its verified behavior during migration.
- Framework code owns stable routes, validation, schema, authorization, service
  contracts and neutral defaults. Client identity, domain, contact, logos,
  favicon, palette, content, policies, catalogue, integration configuration and
  business-specific instructions belong in approved deployment configuration,
  content or owner-managed records. Do not add new client literals in shared
  services/templates. Track existing exceptions; never copy another client's
  live records or change its identity as a side effect.
- Expand existing Settings, Appearance and Integrations, not duplicate stores.
  Keep credentials encrypted/private and separate DB, media, conversation
  storage and secret material per client.
- Defer `/install` and package setup (DB access, identity, appearance, unique
  owner credentials, SMTP, AI, Google OAuth and other integrations) until
  current capability and defect work is verified. Do not build it now.
- Reusable means tests with two distinct synthetic business profiles, authorized
  owner edit/save/reload, module gating, cross-client isolation and live
  reference-deployment regression. A schema field or green fixture alone does
  not certify a feature or an installation.
- Keep Knowledge, Instruction and Tool RAG distinct in the native bounded
  agent loop. .bapX supplies scoped business guidance; .agents and generated
  project indexes are development-only. Record retrieved facts, activated
  instruction hashes and actual executed tool results separately.
- Generate Mermaid/YAML/JSON from canonical code and schema, independently of
  deployment configuration. Reference the configured data transport, never
  publish a resolved runtime client URL or record. Review README diagrams and
  durable documentation against actual source after regeneration.

## Work order

### Navigation budget

For repository questions, use at most three discovery hops before answering:

1. Read this file and only the `discovery`, `query_examples`, and `summary` block at
   the top of `index.yaml`.
2. Narrow-search `index.yaml` or `docs/project-index.json` for the exact route,
   concept, filename, collection, class, or skill.
3. Open the returned original source and answer with its path/symbol.

Do not run broad directory listings, recursive globs, Git history, or read the whole
generated index when the indexed path answers the question. Broaden only when a
target is absent, and state that absence as a finding.

1. `./bapXphp map` and `./bapXphp schema list` before proposing any change.
2. Read this file.
3. **Check `docs/project-index.json` before claiming any feature exists.** It is the
   generated, committed inventory of every route, controller, service, view and
   collection. An index entry proves a resource was inventoried, not that the feature is
   working. If absent, inspect narrowly and verify index freshness before adding anything. Read `docs/systematic-map.mmd` for the wiring diagram.
   Use root `index.yaml` to query original project concepts and relationships,
   including blogs, images and hosted `/remotedb` collections. Read its `discovery`
   section first and query narrowly; never load the entire generated file into context.
4. Search existing GitHub issues with `gh` on the development machine, then create
   or comment on the correctly scoped evidence-backed issue before implementation.
   Record source SHA, reproduction, expected/actual result, affected paths and
   acceptance checks. An unrelated bug found while testing gets a separate issue;
   a related finding belongs in the existing issue comments. **No issue ledgers,
   acceptance-status pages or execution plans in `docs/`.** Store plans/status
   in GitHub; durable docs explain implemented behavior and repeatable procedures.
   Read-only diagnosis need not create a tracker unless a new actionable bug is found.
5. Read the matching `.agents/skills/<name>/SKILL.md`.
6. Read `storage/schema/collections.php` for schema and `Design.md` for UI.
7. Inspect existing implementations before creating any file, route, service, view,
   collection or navigation item.

## Architecture

- **Frontend:** PHP templates in `views/`, following `Design.md`, with native JavaScript for editing, feedback and recovery. PHP owns validation, permissions and persistence. The frontend runs without a build step.
- **Backend:** controllers and services in `app/`. Route → controller → service →
  MySQL via `DatabaseService`.
- **Schema:** `storage/schema/collections.php` is canonical. Update it before changing
  any collection shape.
- **Runtime store:** hosted MySQL only for admin-editable records. Do not create local
  product, category, consultant, order, user, setting, media-metadata or secret copies.
  Blog posts are Markdown with YAML frontmatter in `content/blog/posts/`; image
  binaries remain files. New uploaded binaries live in the domain-local
  `.sps-media` directory beside the code checkout and retain public
  `/assets/images/media/…` URLs. `MediaService` writes metadata to hosted MySQL
  `media_files`; the shipped legacy media catalogue is an offline migration input. Installation-specific YAML remains read-only until its owner verifies migration. See `./bapXphp media:reconcile` and `docs/json-storage.md`.
  Authored campaign download bytes live in the private sibling directory
  `.sps-downloads`. The hosted page revision owns metadata. Serve bytes only
  through the PHP entitlement check, and preserve the directory on deployment.
- **Agents:** exactly two pure-PHP application surfaces — customer support and
  owner/admin chat. Support context uses `AgentContextService` and
  `SupportToolRegistry`; owner queries use `EnterpriseToolRegistry` with
  allowlisted, read-only `AgentToolRegistry` operations. `AgentCapabilityPolicy`
  enforces actor permissions; model instructions are not authorization. Site
  mutations still require an explicit owner-reviewed form.
- **Private chat exception to MySQL:** each support/admin conversation is one
  `storage/agent-conversations/<32-hex-id>.json` file, never a SQL table.
  `AgentConversationService` owns persistence, access and feedback; `AgentTraceService`
  records sanitized correlated steps inside that same file. MySQL remains the
  authority for business records, support tickets, metadata and operational audit
  events. See `docs/agent-observability.md` and `docs/json-storage.md`.

## Environment

- Native platform target: stable **PHP >=8.3** and **MySQL >=8.4** with cURL,
  JSON, mbstring, PDO MySQL and OpenSSL. CI validates PHP8.3/MySQL8.4 plus
  PHP8.4/8.5 regression compatibility. Use `./bapXphp runtime:check --json`
  and the private System Health screen to inspect actual environments.
- A MariaDB version is not MySQL compatibility proof. Report a mismatch;
  never silently migrate a customer database or break the running site merely
  to enforce a new baseline. See `docs/runtime-requirements.md`.

- The remote endpoint is `/remotedb`, all lowercase. `DatabaseService::remoteCall()`
  must throw on transport, non-2xx and invalid-response failures; never convert a DB
  outage into an empty catalogue.
- `.env` holds only `APP_NAME`, `APP_URL`, the `BAPX_MYSQL_*` values and
  `BAPX_REMOTE_DB_URL`. Every other secret belongs in the MySQL `secrets` table via
  Admin → Integrations.

## External reference and gap-discovery requirement

- Before designing a new capability or repairing an unfamiliar subsystem, inspect
  relevant installed skills, reference projects' actual source and their SKILL.md
  files, plus current official provider/protocol documentation. Compare mainstream
  implementations (OpenAI Agents SDK, Google ADK, Anthropic skills/tool discovery,
  Vercel AI SDK, LangChain) with applicable PHP equivalents (Neuron, Prism, Maestro).
  For provider management compare Open WebUI connections and LiteLLM routing.
  Record exact source URLs/revisions, observed differences, and acceptance criteria
  on the existing GitHub issue before implementation. Never invent API fields or
  assume feature parity from README marketing or generated indexes.
- Adopt missing mechanisms, not entire external frameworks. Preserve native PHP,
  vanilla JS, MySQL and low-cost shared-hosting constraints; no new paid service,
  embeddings, vector store or always-running agent process.
- Treat .agents/ as developer guidance only. .bapX/ is the canonical business-agent
  knowledge, instruction and tool guidance source for Support, Enterprise, WebMCP
  and HTTP MCP, with separate authorized projections. Do not expose private files,
  credentials, arbitrary SQL or developer instructions through public transports.
  Compare current code with these goals; do not claim unimplemented projections.
- When reference research or real testing reveals another reproducible gap,
  search existing issues first, update the appropriate tracker or create a
  distinct issue with evidence and priority. Continue independent verified work
  while CI, reviews or hosting are pending; do not confuse a local patch or PR
  with a completed production fix.

## Model facts you must not guess

Your training has a cutoff and this project uses models released after it. **Search the
web before asserting that a model, endpoint or API field does not exist.** Saying "that
model name looks wrong" without checking sends the owner chasing a configuration problem
that is not there.

- The default Google provider uses its two hosted Gemma 4 models:
  `gemma-4-31b-it` is primary and `gemma-4-26b-a4b-it` is the automatic fallback for a transient failure. OpenRouter Free is the final fallback when enabled and configured. Each eligible model receives three attempts for recoverable inference failures before switching; the synchronous fallback loop repeats from the first eligible model for a second bounded cycle. Stop on success or budget exhaustion. Use documented exponential backoff (1s, 2s, then 4s before switching, plus jitter), honor Retry-After, and never blindly retry terminal errors or a held model; once a tool exchange begins, retry only its identical inference payload on the same model, never re-execute completed tools. Google does not publish a hosted Gemma 4 27B identifier.
  Proof: <https://ai.google.dev/gemma/docs/core/gemma_on_gemini_api#supported-models>.
- It is a **reasoning model**. Its reply may contain the model thinking out loud, either
  as parts flagged `"thought": true` or as prose before the answer. Read the answer with
  `AiClient::answerFromParts()`; never take `parts[0]`, which is usually the reasoning.
  Reference: <https://github.com/google-gemini/cookbook/issues/1198>.
- Gemma 4 on the Gemini API supports `MINIMAL` and `HIGH` through
  `generationConfig.thinkingConfig.thinkingLevel`. `AiClient` uses `MINIMAL` and
  still filters any reasoning from the returned text. Reference:
  <https://ai.google.dev/gemma/docs/core/gemma_on_gemini_api#thinking>.

The owner configures independent Google, OpenRouter and compatible provider
connections in Admin → Integrations. Google and OpenRouter model IDs are
editable; Google models can be disabled individually. OpenRouter defaults to
openrouter/free and off. The existing encrypted key per connection is preserved
on blank input. Use the ordered enabled/configured provider plan in SecretService,
with one shared deadline and no cross-provider replay after a tool exchange.
Google uses parametersJsonSchema for canonical JSON Schema; do not weaken
server-side input validation to satisfy a provider wire format.
See docs/pages/integrations.md and https://ai.google.dev/api/generate-content#FunctionDeclaration .

The general rule: when a fact about an external service decides what you change, verify
it against that service's own documentation and record the URL beside the code.

## Testing

This is a web app and a PWA. **Verify in a browser, not the terminal** — desktop and
375px mobile. Terminal checks cover generators, schema and tests only.

Use an actual browser for UI verification. For the connected-Mac workflow the
owner explicitly selected the installed **Playwright CLI**, with real admin and
dedicated development-customer logins, desktop and 375px tests. A built-in Browser
is also suitable where available. Do not substitute terminal HTTP requests for UI
flows. Use authenticated **remotedb** reads to verify business records. Keep test
credentials, browser storage state, customer page text and screenshots out of Git
and public logs; assert counts/booleans rather than dumping private page bodies.

After route, schema, controller, view or workflow changes, run `./bapXphp update`
to regenerate `map.mmd`, `docs/systematic-map.mmd`, and the indexes. Fix stale
expectations to match the agreed behavior; never weaken validation to hide a defect.
Cross-review source and durable docs/skills before the PR. Run `php cli/docs-check.php --list`,
inspect affected originals, correct prose and explicitly record each affected area's
review rationale; `--check` rejects stale source/reference hashes. Regenerating
maps does not approve documentation truth. See `docs/development-workflow.md`.
Run `./bapXphp ci` before the PR, plus `php cli/agent-eval.php` after
agent/tool/prompt changes and `php tests/agent-concurrency.php` for chat
storage changes. The offline golden suite is deterministic and has an explicit
baseline; passing it does **not** certify live factual accuracy or customer
resolution. Verify GitHub checks before merging. After
deployment, repeat the affected flows in the live Browser and attach the evidence
to the original issues. A merge or an SMTP acceptance message alone does not prove
deployment or inbox delivery.

Local browser-test credentials and the fixed customer test ID are stored in
`.env.test-user`. The file is gitignored; read it locally when authentication is
required, and never copy its credential values into tracked files, command output,
test reports or logs.

```bash
php -S 127.0.0.1:8811 index.php    # local server
./bapXphp ci                        # lint, tests, maps, smoke
```

Exercise the changed JavaScript controls with keyboard and pointer input, and confirm visible state after the action. Check computed styles as well as screenshots: an author rule like
`display:flex` silently overrides `[hidden]` and breaks JS filters with no error.

## Rules

- Extend existing controllers, services and views. No parallel implementations.
- Use `Design.md` and the relevant UI skills as defaults. Adapt spacing, shadows,
  and motion when browser evidence shows a clearer result for the task. Verify
  readability, keyboard focus, mobile reachability, and real actions.
- When changing a reusable UI pattern, update `Design.md` and the affected UI
  skills in the same PR. Update these agent instructions when the working
  agreement changes. Capture before and after screens. For motion, also
  inspect intermediate frames during real scrolling.
- Shared motion follows `docs/site-motion.md`: native scrolling, readable no-JS
  frames and dynamic reduced-motion support. Coding references such as Scrollcraft
  and Stageshow stay outside runtime business-agent prompt selection.
- Keep any layered homepage handoff bounded. Include short mobile viewports,
  keyboard return to the outgoing section and fragment navigation in browser
  checks whenever the handoff changes. For consecutive sections, verify each
  transition in both scroll directions with measured section positions.
- Admin mutations are auditable via `AuditLogService`, which must never break the
  operation it records.
- Secrets are never committed and never placed in `.env`.
- Before pushing to `main`: run `./bapXphp ci` and confirm it is green. `main` deploys
  to production, so a red build can still ship.
- Branch, push, open a PR. Never commit directly to `main`.

## Code Review Rules

- Flag unsafe side effects or retries that can duplicate an already-created order,
  refund or support ticket. Business success and audit delivery are separate;
  retain explicit idempotency and ownership checks before privileged work.
- Preserve PHP/MySQL business boundaries and customer isolation. Agent chat
  persistence, when introduced by its own reviewed feature PR, is one private
  JSON file per chat, not a new unrestricted SQL/file-write tool.
- CI success, a bot mention and a completed exact-head review are different
  evidence. Never approve from a quota error, stale receipt or privileged
  workflow executing PR code. See `docs/engineering/ai-review-workflow.md`.

## Issue closure and release order

Work one independently testable issue per Ready-for-Review PR. Use `Refs #N` while
live acceptance is pending; do not auto-close an issue at merge if its production
checks remain unverified. After exact-head CI, merge, verify actual live behavior,
attach evidence with `gh issue comment`, then close only fulfilled scope. For provider changes verify saved-key presence without exposing values, persisted enable/model settings, exact wire schema, real text and tool calls separately, and both live agent surfaces. A successful connectivity probe is not proof of tool execution or customer resolution. Never
mark provider quota, a sent review command or SMTP acceptance as verified success.
