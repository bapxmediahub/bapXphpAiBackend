# Remote Database Operations

`/remotedb` accepts read queries for diagnostics. Record mutations require the owner-configured `remote_db_token` and use explicit `upsert`, `delete`, or `replace` actions against declared schema collections. The `secrets` collection is never available through this endpoint.

Set the token in Admin -> Integrations, then expose it to the hosting shell as `BAPX_REMOTE_DB_TOKEN`. The CLI reports only whether a token is configured:

```bash
bapXphp db remote-status
bapXphp db upsert products '{"id":"prod-example","slug":"example"}'
bapXphp db delete products prod-example
```

Do not use `db raw` for mutations. Product image imports use this same path when direct MySQL is unavailable.
