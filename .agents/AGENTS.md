# Agent Skills DOX

## Purpose

Owns repo-local agent skills. This is the only skill tree kept in the project.

## Ownership

- `skills/`: task-specific skill contracts used by agents working in this repo.

## Local Contracts

- Do not recreate `.codex/skills` or `.claude/skills`.
- Keep skill guidance business-agnostic and aligned with root `AGENTS.md`.
- Update the matching skill when a durable workflow rule changes.

## Work Guidance

- Skills should point to the DOX chain and concrete repo checks, not duplicate large docs.

## Verification

- `php tests/run.php` when skills are referenced by tests or README.

## Child DOX Index

- `skills/AGENTS.md`: individual repo skill files.

