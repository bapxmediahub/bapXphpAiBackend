---
type: doc
title: Engineering Guide
description: Runtime data is remote MySQL data accessed through DatabaseService. .env supplies database connectivity; secrets stay in MySQL secrets collection.
category: role
---
# Engineering Guide

Runtime customer, admin, payment, booking, and address data is remote MySQL data accessed through `DatabaseService`. `.env` supplies direct database connectivity; application secrets remain in the remote MySQL `secrets` collection. Markdown and YAML are reserved for documentation, Help-category blog content, and media metadata; JSON fixtures are import-only.

Use `bapXphp map` and `bapXphp schema list` for orientation, `bapXphp update` after source or documentation changes, and `bapXphp ci` before every PR and merge.

## Development Test Customer

Authenticated browser checks use one fixed customer in remote MySQL: ID `dev_test_customer`, email `test.user@bapx.dev`, role `customer`.

Run `bapXphp dev:user --generate` once. It creates the password in ignored, mode-restricted `.env.test-user` and upserts the fixed account. Later runs use `bapXphp dev:user`; the command is idempotent and updates the same record through the authenticated remote mutation endpoint. Use `bapXphp dev:user --dry-run` to inspect the target without changing data. Never put the password in source, documentation, issues, logs, or commits.
