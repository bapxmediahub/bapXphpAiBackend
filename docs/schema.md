# Schema Registry

`storage/schema/collections.php` is the database schema contract for this PHP/MySQL backend.

Use it before changing:

- collection fields
- admin editable fields
- media fields
- owner fields
- support assistant context fields
- collection names or table structures

## Important Keys

- `primary_key`: record identifier.
- `owner_field`: field used to filter user-owned data.
- `admin_managed`: whether the owner admin can manage this collection.
- `admin_fields`: fields exposed in admin resource forms.
- `media_fields`: fields that should use the media library picker.
- `agent_context`: fields safe to include in model/support context for the owning user.
- `fields`: type hints for agents and admin tooling.

## Agent Rule

When a data shape changes, update schema first, then code, then docs/tests. Do not infer new fields only from templates or PHP arrays.

## Remote Database Query Endpoint

### `/remotedb`

A secure API endpoint for running read-only SQL queries remotely. Useful for the `bapXphp` CLI to query the live database from a development environment.

**Endpoint:** `POST https://yoursite.com/remotedb`

**Request Body:**
```json
{
  "token": "your-secret-token",
  "query": "SELECT * FROM products LIMIT 10",
  "params": []
}
```

**Response:**
```json
{
  "success": true,
  "data": [...]
}
```

**Security:**
- Only `SELECT`, `SHOW`, `DESCRIBE`, and `EXPLAIN` queries are allowed.
- Authentication via `remote_db_token` stored in the `secrets` table.
- Set the token via Admin → Secrets or `bapXphp db raw "UPDATE secrets SET remote_db_token = 'your-token' WHERE id = 'secrets-primary'"`.

**Client Token:**
Generate a secure token:
```bash
openssl rand -hex 32
```

**Usage with bapXphp:**
The endpoint allows remote DB access when direct MySQL connection is unavailable. Use it for debugging or data exploration in production.
