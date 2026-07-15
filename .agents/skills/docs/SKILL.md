---
type: skill
name: docs
description: Use when editing README, docs, project-map docs, or agent-facing instructions.
---
# Docs

- Follow the root `AGENTS.md` repository contract.
- `docs/systematic-map.mmd` is the single project wiring map artifact. `docs/map.mmd` is the content/documentation mindmap. `map.mmd` (root) is the code dependency graph with edges + gaps.
- Do not recreate `docs/PROJECT_MAP.md`, `docs/project-map.json`, or `docs/project-map.mmd`.
- Regenerate the systematic map with `php tools/generate-project-map.php` after route, service, view, schema, storage, tool, or integration changes.
- Regenerate both maps with `bapXphp update` after root `AGENTS.md`, skill, or documentation changes.
- Never hand-edit generated Mermaid. Fix deterministic scan/render inputs, then regenerate through the tool.
- Use the map like a source index: follow affected nodes to the actual files and verify route, page, schema, storage, and navigation behavior before documenting completion.
- Search existing docs and code before adding a file; a gap node is not automatic permission to scaffold one.
- Validate with `php tools/validate-project-map.php`.
- Keep durable docs concise, current, and aligned with the PHP/JSON shared-hosting architecture.
