# Docs DOX

## Purpose

Owns durable documentation and the single systematic project-map artifact.

## Ownership

- `systematic-map.mmd`: the single generated project-map artifact (routes, controllers, services, views).
- `KnowledgeMap.mmd`: generated mindmap of docs, AGENTS.md files, CLI commands, skills, and architecture (regenerated via `bapXphp docsmap` or `php tools/generate-docs-map.php`).
- `README.md` files and topic docs: human and agent-facing project guidance.
- Page and module docs: concise behavior notes for existing surfaces.

## Local Contracts

- Do not create `PROJECT_MAP.md`, `project-map.json`, `project-map.mmd`, or parallel map artifacts (exception: `KnowledgeMap.mmd` is a separate generated artifact, not a parallel project map).
- Regenerate `systematic-map.mmd` with `php tools/generate-project-map.php`; do not hand-edit generated map output.
- Regenerate `KnowledgeMap.mmd` with `bash tools/bapXphp docsmap` or `php tools/generate-docs-map.php`.
- Treat the map as a derived source index: documentation claims must be checked against the primary files connected by its edges.
- Keep documentation aligned with the PHP/JSON shared-hosting architecture.

## Work Guidance

- Document stable contracts, not diary entries.
- Remove stale map references and contradictory workflow instructions immediately.
- Keep docs concise enough for agents to read before editing.

## Verification

- `php tools/generate-project-map.php`
- `php tools/validate-project-map.php`
- `bash tools/bapXphp docsmap` or `php tools/generate-docs-map.php` when AGENTS.md, skills, or docs change.
- `php tests/run.php` when README or agent workflow text changes.

## Child DOX Index

- `pages/`: page-specific behavior notes.
- `modules/`: module-specific behavior notes, including authenticated consultation communication.
