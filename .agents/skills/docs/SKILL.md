---
name: docs
description: Use when editing README, docs, project-map docs, or agent-facing instructions.
---
# Docs

- Follow the root `AGENTS.md` repository contract.
- `docs/systematic-map.mmd` is the systematic inventory, `docs/map.mmd` is the
  content/documentation mindmap, and root `map.mmd` is the code dependency graph.
  Root `index.yaml` is the generated concept/relationship router; it points to
  original resources and must not copy blog bodies or hosted records.
- Do not recreate `docs/PROJECT_MAP.md`, `docs/project-map.json`, or `docs/project-map.mmd`.
- Regenerate all committed maps and indexes with `./bapXphp update` after route,
  service, view, schema, storage, tool, skill, blog metadata, or documentation changes.
- Never hand-edit generated Mermaid. Fix deterministic scan/render inputs, then regenerate through the tool.
- Use the map like a source index: follow affected nodes to the actual files and verify route, page, schema, storage, and navigation behavior before documenting completion.
- Search existing docs and code before adding a file; a gap node is not automatic permission to scaffold one.
- Query `index.yaml` narrowly to locate original files; never load it wholesale or treat generated summaries as the source of truth. `docs/project-index.json` is the machine-readable code index.
- Validate with `./bapXphp ci`.
- Keep durable docs concise and aligned with the PHP/MySQL shared-hosting architecture and Markdown-file blog boundary.

## Testing

`./bapXphp update` to regenerate maps, then `./bapXphp ci`. Map validation alone is not enough: for each affected map path, open the real route, controller, service and rendered page.

## Keeping this skill current

Update whenever a route, controller, service or view is added or removed — the generated maps are committed and CI fails when they drift.

## Documentation-to-code drift gate

Do not claim MySQL is the only runtime store. It is canonical **for business
records**; agent chats are a deliberate file exception and each chat has one
private JSON in `storage/agent-conversations/`. Offline eval
snapshots are optional and contain only fixture scores. Review and update
`docs/agent-observability.md`, `docs/json-storage.md`,
`docs/architecture.md`, the agent portions of
`docs/agentic-monorepo.md` and the affected skills when adding or
changing a trace, feedback, route or tool. Verify that the route exists in
`ProjectMapService`, the class exists, the docs do not invent
features, and the regenerated `docs/project-index.json`,
`index.yaml` and maps pass `./bapXphp ci`.

## Execution evidence contract

For both agent surfaces, prepare the actor-owned private JSON request before
executing tools/providers. Persist actual span boundaries through
`AgentTraceService`; never backfill timestamps or call a produced response
delivered. Use effective result scope instead of requested filter arguments.
Unfinished requests are visible in Agent Monitoring and must not be blindly
replayed. Keep provider tokens/usage unavailable when absent, and distinguish
14-case offline regression scores from real customer satisfaction/accuracy.

Agent capability/instruction changes must regenerate `docs/agent-tool-contracts.md` through `php cli/agent-contracts.php`; `./bapXphp ci` fails if it differs from the executable PHP registry/policy.

## REA source comparison evidence

Record reference commit, file path, license boundary and content hash.
REA `import-reference-source` is historical-reference evidence with explicit
coverage limits, not proof of runtime behavior. Distinguish main, published
PR heads and unfinished local changes. Unsupported/unselected source files
are unknown, not proved absent. Runtime evidence must state the actual
scenario and privacy scope. Do not install reference products or copy paid
enterprise modules into this native-PHP app merely to close a feature list.
See `docs/research/2026-11-15-rea-agent-commerce-gaps.md`.

## Developer/runtime instruction separation

This `.agents/` skill is for repository coding tools only. Live business
agent prompt text lives under `.bapX/`, selected by `AgentInstructionService`
with a fixed role allowlist. Update `docs/runtime-agents.md`, instruction
content fingerprints and isolation tests whenever that contract changes.
Never route a model-supplied path or developer index into live instructions.
