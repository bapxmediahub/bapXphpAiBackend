# JSON Schema Registry

`storage/schema/collections.json` is the database schema for this PHP/JSON backend.

Use it before changing:

- JSON collection fields
- admin editable fields
- media fields
- owner fields
- support assistant context fields
- collection names or file paths

## Important Keys

- `file`: JSON file under `storage/data`.
- `primary_key`: record identifier.
- `owner_field`: field used to filter user-owned data.
- `admin_managed`: whether the owner admin can manage this collection.
- `admin_fields`: fields exposed in admin resource forms.
- `media_fields`: fields that should use the media library picker.
- `agent_context`: fields safe to include in model/support context for the owning user.
- `fields`: type hints for agents and admin tooling.

## Agent Rule

When a data shape changes, update schema first, then code, then docs/tests. Do not infer new JSON fields only from templates or PHP arrays.
