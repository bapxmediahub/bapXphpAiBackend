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
