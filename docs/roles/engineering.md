# Engineering Guide

Runtime customer, admin, payment, booking, and address data is remote MySQL data accessed through `DatabaseService`. `.env` supplies direct database connectivity; application secrets remain in the remote MySQL `secrets` collection. Markdown and YAML are reserved for documentation, Help-category blog content, and media metadata; JSON fixtures are import-only.

Use `bapXphp map` and `bapXphp schema list` for orientation, `bapXphp update` after source or documentation changes, and `bapXphp ci` before every PR and merge.
