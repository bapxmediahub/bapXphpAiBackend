# MySQL Storage

All runtime data is stored in MySQL tables, accessed through `DatabaseService`. The schema contract lives in `storage/schema/collections.php`. Agents should treat that file as the database contract before changing MySQL table shapes or admin forms.

## Collections

All 21 collections defined in `collections.php` map to MySQL tables. When running locally without MySQL, `DatabaseService::isRemote()` detects the unreachable database and proxies queries to a remote production endpoint via `/remotedb`.

## JSON Seed Data (CLI Only)

JSON files in `storage/data/` exist only for one-time seeding into MySQL:

```bash
bapXphp db init     # Create tables from collections.php
bapXphp db sync     # Push JSON seed data into MySQL
```

These JSON files are never used at runtime. All runtime reads and writes go through `DatabaseService` → MySQL.
