---
type: doc
title: JSON Storage
description: Business records remain in MySQL; private agent conversations use one JSON file per chat.
category: docs
---

# Storage boundaries: hosted MySQL and private chat JSON

**Business data** (users, products, orders, tickets, GST records, settings, secrets,
media metadata and `audit_events`) remains in hosted MySQL through
`DatabaseService`. `storage/schema/collections.php` defines those collections.
Do not introduce local JSON mirrors of customer or admin business tables.

**Conversation history** is the deliberate exception: each Support or Enterprise
Agent chat is stored as one private file in
`storage/agent-conversations/<conversation-id>.json`. The source of truth for
these conversations is the file itself. There is **no** `agent_conversations`
MySQL table, no conversation mutation through `/remotedb`, and no optional
SQL snapshot required. Per-chat `.conversation-<id>.lock` files serialize same-chat writes; a shared
`.conversation.lock` compatibility barrier coordinates older deployment writers;
atomic file replacement ensures concurrent turns do not get lost.

Every chat document contains an actor type + authenticated ID or anonymous
guest token, timestamps, scrubbed questions/answers, turn/trace IDs, redacted
tool/model spans, basic evaluations and any customer feedback/resolution state.
App pages must not serve `/storage/` directly. App-specific auth checks are
required for history/extract/export, and files must stay private (0600 when
created). This is customer content: define retention, backups and secure storage
before releasing broadly. Code deploys must preserve the untracked conversation
directory.

## Non-business files

- Blog articles: `content/blog/posts/*.md` with YAML frontmatter.
- Blog categories: `content/blog/categories.yaml`.
- Product/media binaries: bundled `assets/images/` or persistent uploaded
  `.sps-media` next to the deployed checkout. Upload metadata remains MySQL
  `media_files`.
- Offline deterministic agent experiment snapshots, **only on explicit CLI
  request**: `storage/agent-evals/eval-<timestamp>-<fixture-hash>.json`. These
  contain test scores and version hashes, never customer messages.
- Optional one-time seed fixtures: `storage/data/*.json`; they are **not**
  runtime fallbacks for business data.

## Commands and provenance

```bash
./bapXphp db init           # Create business MySQL tables
./bapXphp db sync           # Compatibility alias for DB initialization
php cli/agent-eval.php      # Compare pure-PHP golden fixtures against baseline
php cli/agent-eval.php --write  # Write one offline experiment score JSON
php tests/agent-concurrency.php # Verify one JSON file per concurrent chat
```

`/remotedb` exists only for approved remote MySQL business operations. Chat
history and offline eval artifacts are filesystem responsibilities and do
not require separate paid services or new SQL collections.

## Runtime checkpoint schema (v2)

A request is first written as an actor-owned `pending_turns` entry in the same
conversation JSON. Its bounded execution trace is updated before and after
important operations. Only final atomic persistence removes the pending marker
and appends the completed turn. Unfinished entries remain inspectable after an
interruption; they are not automatic retry instructions. Metadata-only monitoring
shows pending counts and sanitized step summaries; authorized JSON export retains
the evidence without exposing storage files directly over HTTP.
