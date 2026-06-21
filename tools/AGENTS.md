# Tools DOX

## Purpose

Owns maintenance scripts, project-map generation/validation, local smoke checks, and mail queue tooling.

## Ownership

- `generate-project-map.php`: writes `docs/systematic-map.mmd`, including shared navigation-to-route relationships.
- `validate-project-map.php`: verifies the committed systematic map is fresh.
- `smoke-local.php`: starts a disposable local PHP server and checks key routes/API behavior.
- Other scripts must have one clear concern.

## Local Contracts

- One tool per concern. Extend an existing tool when it already owns the workflow.
- The project map has one artifact only: `docs/systematic-map.mmd`.
- Tool output should be deterministic enough for CI and agent verification.

## Work Guidance

- Keep tools runnable from the repo root with `php tools/name.php`.
- Do not bake customer-specific remote production URLs into local tools.
- Smoke checks should verify real routes in this repo, not copied routes from another project.

## Verification

- `php -l tools/changed-tool.php`
- `php tools/generate-project-map.php`
- `php tools/validate-project-map.php`
- `php tools/smoke-local.php`

## Child DOX Index
