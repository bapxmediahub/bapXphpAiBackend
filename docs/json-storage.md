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

The dashboard's storefront activity read aggregates two event types in MySQL.
It returns two counts through `DatabaseService` and the same authenticated
`/remotedb` bridge used by other database reads. The cutoff uses the offset in
the audit record's JSON `created_at`. Legacy absolute ISO or SQL dates without
an offset use the site's Asia/Kolkata time. Missing and invalid dates are skipped.

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
- Blog categories use article frontmatter and the standard labels in `BlogService::categories()`.
- Product/media binaries: bundled `assets/images/` or persistent uploaded
  `.sps-media` next to the deployed checkout. Upload metadata remains MySQL
  `media_files`.
- Offline deterministic agent experiment snapshots, **only on explicit CLI
  request**: `storage/agent-evals/eval-<timestamp>-<fixture-hash>.json`. These
  contain test scores and version hashes, never customer messages.
- Optional one-time seed fixtures: `storage/data/*.json`; they are **not**
  runtime fallbacks for business data.


## CI check for filesystem writes

`./bapXphp test` and `./bapXphp ci` inspect PHP tokens in the runtime entrypoint,
application, API, configuration, integrations and views. New direct filesystem
writers and changes to reviewed destination expressions fail the test with a
file and line. The existing writers are documented in
`tests/runtime-storage-boundary.php` alongside their approved operational paths.
Opening a file for reading and writing to memory or standard streams remain
available.

This is a source regression check. Review the origin of computed paths when
editing an existing writer. Keep new business persistence in `DatabaseService`;
for an approved filesystem feature, review its destination and update the test
and this contract in the same change.

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

## Staff review history in the same file

An authenticated, CSRF-protected answer review appends a bounded `reviews[]`
entry to the selected completed turn. It includes a random review ID, revision,
reviewer identity/time, original evidence hash, structured rubric/verdict/reasons,
selected evidence references, scrubbed note and declared authorship. Writes use
the same per-chat lock and atomic replacement as turns/feedback; stale revision
or changed evidence is rejected. No SQL table or second transcript JSON is used.
A maximum of 20 decisions per turn bounds growth. Original content/customer
feedback is never overwritten, and review time is separate from customer activity.

## Reconcile historical Media Library metadata

The shipped catalogue is archived in `storage/migrations/legacy-media/products.yaml` for migration. Runtime uploads, usage edits and revision-checked media metadata edits write to MySQL `media_files`. Installations with additional historical `storage/media.yaml` or `storage/media/*.yaml` files should check them from their own deployment checkout.

Run `./bapXphp media:reconcile` for a read-only comparison of source rows, unique IDs, missing rows, missing fields, conflicting ID/URL ownership and covered rows. Existing MySQL fields win, including empty descriptions or usage arrays. The tool accepts up to 250 historical rows and 1 MiB of source data, rejects unsupported YAML, and reports counts without item contents.

If rows need migration, run `./bapXphp media:reconcile --apply` from a checkout with direct access to the target MySQL. A serializable transaction adds missing rows, fills only absent fields, and verifies coverage before committing. A conflicting URL, ID or context blocks the whole batch. Run the read-only command again and compare `verified` with `unique_ids`. Repeat applies preserve the existing records. Neither mode changes binaries or removes source files. Keep installation-specific compatibility files until the owner has verified their own migration.
