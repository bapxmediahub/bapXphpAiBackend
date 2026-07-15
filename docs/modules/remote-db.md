---
title: Remote Database Operations
description: The application connects directly to hosted MySQL with the BAPX_MYSQL_* values in .env.
category: module
---

# Remote Database Operations

The application connects directly to hosted MySQL with the `BAPX_MYSQL_*` values in `.env`. If direct MySQL is unavailable from a developer machine, `DatabaseService` and `bapXphp db` use `APP_URL/remotedb`; production currently resolves that fallback to `https://sripanchamispiritual.com/remotedb`.

`/remotedb` accepts read queries for diagnostics. Record mutations require the owner-configured `remote_db_token` and use explicit `upsert`, `delete`, or `replace` actions against declared schema collections. Application secrets remain in the MySQL `secrets` collection and are never writable through the public endpoint.

Set the token in Admin -> Integrations. The CLI can load it through owner-authenticated hosting credentials in ignored `.env.mysql`, or use `BAPX_REMOTE_DB_TOKEN` when explicitly supplied:

```bash
bapXphp db status
bapXphp db upsert products '{"id":"prod-example","slug":"example"}'
bapXphp db delete products prod-example
```

Use `bapXphp db hosted` for owner-authorized SQL, including protected `secrets` maintenance. Do not use `db raw` for mutations. Product image imports use authenticated record operations when direct MySQL is unavailable.
