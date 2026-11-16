---
type: doc
title: Native Runtime Requirements and System Health
description: Read-only PHP and database compatibility diagnostics, private operator guidance, strict CI checking and safe migration boundaries.
category: docs
---

# Declared native platform baseline

The native CMS/commerce product targets **stable PHP 8.3 or newer** and
**MySQL 8.4 or newer**. Required PHP extensions are `curl`, `json`, `mbstring`,
`pdo_mysql` and `openssl`. Use a currently supported, patched release, not an
alpha/beta/release candidate. A minimum version is not an automatic promise
that every future major version is compatible.

The primary CI job uses PHP 8.3 with an isolated MySQL 8.4 service and the existing
real concurrency fixtures. Additional compatibility jobs run the repository and
agent regression checks on PHP 8.4 and 8.5. These jobs do not use production
credentials or copy business records.

## Read-only operator screen

Admin → Settings → **System Health** (`GET /admin/system-health`) reports the PHP
version/SAPI of the actual web request, the configured business database's
identified engine/version, and whether each required extension is loaded.
It uses the existing admin authentication, private no-store and noindex behavior.
There is no public phpinfo dump, arbitrary query input, credential list, write
operation or database-migration button. The page links to the existing Settings
operator guide and works without JavaScript at desktop and 375px widths.

`RuntimeHealthService::collect` reads only `VERSION()` and `@@version_comment`
through the existing `DatabaseService`. Raw exception text and arbitrary version
suffixes/comments are not rendered: output is bounded numeric versions and known
status/engine labels. Query failures and unknown vendors remain explicitly
unverified rather than green. The web shell still needs its normal authenticated
application dependencies; this is not a database-outage-independent recovery
console.

**MariaDB is not MySQL 8.4.** A larger-looking MariaDB version cannot satisfy the
engine requirement. MariaDB, other identified forks, unknown vendors, old
versions and prereleases receive distinct noncompliant/unavailable states.
Compatibility through a MySQL driver is not vendor equivalence.

## Developer and deployment command

```bash
./bapXphp runtime:check
./bapXphp runtime:check --json
./bapXphp runtime:check --json --strict
```

The first two modes report read-only findings with a normal command exit. The
strict mode exits 1 for any unmet or unavailable requirement; unknown flags
exit 2. The standalone `php cli/runtime-check.php` command is equivalent and is
CLI-only. In `BAPX_TEST_MODE=1`, database evidence stays unavailable; the test
harness does not invent a compatible DB or contact the live service.

A CLI check observes **that CLI PHP**, not the hosting web process. Use the actual
admin page to verify deployed web PHP. CI runs strict mode against its explicitly
isolated MySQL fixture; do not run a production cutover solely because CI is green.

## Existing deployment safety

An older or different-engine deployment may continue serving its current
workloads while the screen reports that it does not meet the declared native
target. This feature does not add a bootstrap kill switch, rewrite connection
settings or migrate records. An unmet target is not itself evidence of a site
outage. Do not relabel a mismatch as compatible merely to make a score green.

Changing the database requires a separately approved destination and migration:
server-side backup, private restore test, schema/collation/query/date compatibility,
concurrency and payment/refund parity, bounded downtime/write-freeze planning,
cutover verification and an executable rollback. Version checks do not prove any
of these. Preserve business data, provider secrets, persistent media and the
separate one-JSON-per-chat storage across code releases.

## Validation and reference boundaries

Unit tests cover version minima, prereleases, MariaDB/fork/unknown identification,
missing extensions, absent evidence, secret-shaped input suppression and strict
CLI status. Browser checks cover actual admin navigation/help, readable states,
no-JS, guest/customer denial and private diagnostics. No data mutation is needed.

The operator-facing status concept was compared with the exact supplied
WordPress 7.1.4 and WooCommerce 11.3.0 Site Health/Status source. This independent
PHP implementation does not execute or copy their runtime, install either
platform, or load a plugin/theme. Capability gaps and implementation plans belong
in GitHub issues, not in this durable behavior guide.

Primary lifecycle reference: https://www.php.net/supported-versions.php .
