---
type: doc
title: Schema Registry
description: storage/schema/collections.php is the database schema for this PHP/MySQL backend.
category: docs
---
# Schema Registry

`storage/schema/collections.php` is the database schema for this PHP/MySQL backend.

Use it before changing:

- collection fields
- admin editable fields
- media fields
- owner fields
- support assistant context fields
- collection names

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


The `settings` table stores the public header selection in its own
`header_menu` row. Its ordered `header_menu` list contains approved page
IDs. Authored targets use `pg:<slug>` and rendering checks their current
publication before showing a link. `menu_revision` guards concurrent menu
updates on the database host. Ordinary settings use `app_settings` and legacy
fallback selection excludes menu and page records. The existing JSON-backed table needs no migration.
