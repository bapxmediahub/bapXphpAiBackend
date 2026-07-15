---
type: doc
title: JSON Storage
description: All runtime data is stored in MySQL tables, accessed through DatabaseService. The schema contract lives in storage/schema/collections.php.
category: docs
---
# MySQL Storage

All runtime data is stored in MySQL tables, accessed through `DatabaseService`. The schema contract lives in `storage/schema/collections.php`. Agents should treat that file as the database contract before changing MySQL table shapes or admin forms.

## Collections

All collections defined in `collections.php` map to remote MySQL tables for runtime use. The local checkout is not a runtime database and must not be used as a fallback for customer, admin, payment, wallet, address, or consultation data. `DatabaseService` reaches the hosted database directly when configured, or uses the authenticated remote DB protocol for explicitly supported operations.

## JSON Seed Data (CLI Only)

JSON files in `storage/data/` are optional one-time import fixtures only:

```bash
bapXphp db init     # Create tables from collections.php
bapXphp db sync     # Push JSON seed data into MySQL
```

They are never runtime storage. All runtime reads and writes go through `DatabaseService` to remote MySQL. Blog/document content remains Markdown/YAML by design.
