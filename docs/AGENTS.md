# Docs DOX

## Purpose

Owns durable documentation and the single systematic project-map artifact.

## Ownership

- `systematic-map.mmd`: the only generated project-map artifact.
- `README.md` files and topic docs: human and agent-facing project guidance.
- Page and module docs: concise behavior notes for existing surfaces.

## Local Contracts

- Do not create `PROJECT_MAP.md`, `project-map.json`, `project-map.mmd`, or parallel map artifacts.
- Regenerate `systematic-map.mmd` with `php tools/generate-project-map.php`; do not hand-edit generated map output.
- Keep documentation aligned with the PHP/JSON shared-hosting architecture.

## Work Guidance

- Document stable contracts, not diary entries.
- Remove stale map references and contradictory workflow instructions immediately.
- Keep docs concise enough for agents to read before editing.

## Verification

- `php tools/generate-project-map.php`
- `php tools/validate-project-map.php`
- `php tests/run.php` when README or agent workflow text changes.

## Child DOX Index

- `pages/`: page-specific behavior notes.
- `modules/`: module-specific behavior notes.

