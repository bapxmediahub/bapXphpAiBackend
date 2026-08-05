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
