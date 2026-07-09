# API DOX

## Purpose

Owns the JSON API entrypoint used by local smoke checks and lightweight frontend consumers.

## Ownership

- `index.php`: dispatches read-only API responses backed by existing PHP services and MySQL data.

## Local Contracts

- Keep API behavior backed by existing services and MySQL storage.
- Do not add a second application runtime or SPA API layer.

## Work Guidance

- Keep responses valid JSON and aligned with public route behavior.

## Verification

- `php -l api/index.php`
- `php tools/smoke-local.php`
- `php tests/run.php`

## Child DOX Index

