---
type: doc
title: Deployment Hostinger
description: Guide for Hostinger Git auto-deploy setup and production configuration.
category: docs
---
# Deployment Guide - Hostinger

## Deployment Steps

1. In Hostinger hPanel, open **Advanced → Git** and connect the GitHub repository to `/public_html`.
2. Enable **Auto Deployment** for the production **Branch** (`main`) so merged commits deploy automatically.
3. Upload the current `main` build to `/public_html` with Git or FTP when doing a manual recovery deploy.
4. Keep `.env`, `index.php`, `.htaccess`, `api/`, `app/`, `assets/`, `integrations/`, `storage/`, `cli/`, and `views/` together.
5. Configure `BAPX_MYSQL_HOST`, `BAPX_MYSQL_PORT`, `BAPX_MYSQL_DB`, `BAPX_MYSQL_USER`, and `BAPX_MYSQL_PASS` in `.env` for direct hosted MySQL access.
6. Configure application secrets from Admin -> Integrations; they are stored in remote MySQL, not `.env`.
7. Verify SMTP and the owner notification address in Admin → Integrations. Transactional
   mail is attempted in the request that creates it; no legacy mail-worker script is
   required. SMTP acceptance is not inbox receipt.
8. Inspect Admin → Email Outbox before processing delayed mail. An approved operator
   can run `./bapXphp mail:process` from the checkout to process at most **25 due pending**
   records. It does not retry failed, accepted, interrupted or uncertain attempts.
   Do not schedule bulk processing or replay historical mail without reviewing the
   recipients, event identities, provider limits and acceptance evidence first.
9. Smoke test public pages, account redirects, admin login, API endpoints, checkout
   configuration and the mail queue. See `docs/email-setup.md` for the transport contract.

### Preserve uploaded media

New uploads are saved in `.sps-media` beside `public_html`, outside the Git deployment
checkout. Their metadata and original-image URLs are stored in MySQL `media_files`.
The existing `/assets/images/media/YYYY/MM/filename` URLs are served by the constrained
image handler in `index.php` when a file is not part of the checkout. Bundled images
continue to be served directly.

Back up both MySQL and the sibling `.sps-media` directory. Deploy code into
`public_html` only; do not replace or clear `.sps-media`. If the host prevents PHP
from creating the sibling directory, uploads report a storage-permission error and
the product form retains its fields. Existing legacy YAML is read-only compatibility
input and is never the destination for new metadata.

The hosted shell requires `git` and PHP. It does **not** require GitHub CLI
(`gh`). hPanel auto-deploy performs the normal production pull. For manual
diagnosis or recovery:

```bash
git status --short --branch
git fetch origin
git pull --ff-only origin main
git rev-parse HEAD
```

Do not run issue, PR, or review conversations from Hostinger. Those
belong to GitHub Actions and the GitHub web interface.

### Preserve private agent sessions and evaluation artifacts

The agent runtime stores **one JSON file per chat** under
`storage/agent-conversations/<id>.json`, not in MySQL. The
folder is ignored by Git, must be writable by PHP, protected from static
HTTP access, and backed up independently of business data. Keep it across
Hostinger code deployments; do not deploy a replacement checkout that erases
untracked JSON sessions. Check the session count/IDs and permissions before
and after a staging release. Restoring only MySQL will not restore chats.

`storage/agent-evals/` holds optional data-free local golden
experiment snapshots. Run `php cli/agent-eval.php` to compare
the committed fixture baseline offline; no model API or paid service is needed.
Live acceptance must still test authenticated chat feedback/history,
actor isolation, trace exports, admin dashboards, and 375px views.

## Release revision receipt and rollback gate

A green PR check or successful merge is **not** evidence that Hostinger finished pulling the new code. Before a release, capture the expected `main` merge SHA and independently back up MySQL, the sibling `.sps-media` directory, and private `storage/agent-conversations/` JSON (including ownership/permissions). Keep `.env` outside change review. Record the existing Hostinger checkout SHA and previous known-working release before initiating deployment.

After Hostinger Git auto-deploy or an approved manual `git pull --ff-only origin main`, verify `git rev-parse HEAD` on the actual deployed checkout equals the expected merge SHA. Do not run force reset, prune customer files, apply schema migration, or re-send stored payments/email to repair a failed deploy. Record live URL, verified SHA, local IST time, health result and the exact human operator. Without shell access, use the deployed `/is-sha-valid?sha=<expected-sha>` endpoint and require its actual `git_head` to match. If neither source establishes the deployed checkout, **mark the SHA unverified**, even if public content appears correct.

For the shared knowledge release, check `/`, `/shop`, `/blog`, `/api/knowledge/contact`, `/api/knowledge/services?limit=4`, `/api/knowledge/articles?q=guide&limit=1`, `/api/knowledge/products?limit=1`, the served `/assets/shop-webmcp.js`, `/assets/public-products-webmcp.js`, `/assets/public-knowledge-webmcp.js`, `/assets/public-business-webmcp.js` and `/assets/vendor/webmcp/polyfill-0.1.1.js`, PHP response correctness and private `/storage/agent-conversations/` URL denial. Validate a signed-in owner session, customer Support, chat history continuity and narrow phone layout without mutating live records. A browser without `document.modelContext` cannot prove native WebMCP invocation; record browser capability separately.

If checks fail, stop promotion and compare the actual Hostinger checkout and server logs with the expected SHA. Roll back to the last known-working code revision **only after checking data compatibility**, preserving the media directory, database and private chats; recheck those paths after recovery. Record unresolved acceptance on the PR/issue rather than closing it because CI passed.

## Live commit verification API

Do not infer deployment from GitHub's `main` branch or from public page assets alone. The
deployed PHP application now provides two read-only aliases:

- `GET /is-sha-valid?sha=<40-lowercase-hex-Git-SHA>`
- `GET /api/is-sha-valid?sha=<40-lowercase-hex-Git-SHA>`

These endpoints read **this Hostinger checkout's actual `.git/HEAD`** (including
detached HEAD, loose refs and `packed-refs`) without invoking Git through the public
PHP runtime, without MySQL and without returning paths or credentials. They disable
HTTP caching and indexing. A response has one of three meanings:

| HTTP status | `status` | Meaning |
| --- | --- | --- |
| 200 | `match` | `match: true`, `deployed_sha` equals the requested Git SHA and `source: git_head` |
| 200 | `mismatch` | `match: false`, Hostinger is still running a different checkout |
| 503 | `unavailable` | `.git/HEAD` cannot be read reliably; **do not claim a deployment** |
| 400 | `invalid` | Missing, uppercase or malformed SHA, or unexpected query parameters |

Example after a successful merge:

```bash
expected=$(git rev-parse HEAD)     # Run in the trusted checkout before release
curl --fail-with-body -H 'Cache-Control: no-cache' \
  "https://sripanchamispiritual.com/is-sha-valid?sha=$expected"
```

The response should show `status: match`, `match: true`, the same
`deployed_sha`, and `source: git_head`. The current GitHub main commit is **not**
a substitute for the running site's Git metadata. Match alone proves which
checkout that PHP process sees; cart, checkout, mail, private chat and mobile UI
still require independent smoke tests. Browser/cache or load-balancer differences
should be checked across actual served instances as applicable.

`.github/workflows/verify-hostinger-deploy.yml` now runs as a **separate
post-deployment evidence workflow** after a successful `Validate application`
push to `main`. It retries this live endpoint with the exact merge SHA,
reports a verified or failed/unknown result, and can also be rerun manually with
`workflow_dispatch` and an explicit full SHA. After a successful SHA match, a **separate dependent job** runs Chromium at
320/375/390 pixels, checks the visible Sri Panchami Spiritual brand, top cart and
absence of the unwanted bottom bar, then performs an isolated real add-to-cart /
decrement test with a disposable guest session. The SHA verification job can be
green independently of a blocked or failed cart acceptance job. No checkout, payment,
customer login or mail is performed. The test attempts to undo the guest cart change
even if an assertion fails. Neither job changes the independent code-CI result.

### HTTP 429 on the guest cart acceptance

The first real post-deploy mobile verification on 22 November 2026 confirmed the
deployed SHA `07cdd3e67f3f7c0378350a8d4396014aedf25d3a`, but the GitHub-hosted
guest Chromium request to **POST /cart/add** returned **HTTP 429** without a JSON
content type. The storefront kept its count at zero and its floating cart hidden;
the request was **not confirmed as a successful addition**. The PHP cart
controller does not itself return HTTP 429, so check the hosting/CDN/WAF access
and rate-limit logs before attributing the failure to cart state or front-end CSS.

On Hostinger, inspect the access/security log for `POST /cart/add` around
`2026-11-22 09:10:37 UTC`, identify which rule generated 429, and compare
ordinary guest handset traffic against automated CI traffic. Do not disable
site-wide request protection or whitelist all GitHub-runner IPs to make CI green.
If protection is working as intended, report the automated guest-cart check as
**blocked by rate limit**, while keeping the SHA job green; run the real cart
acceptance from an authorized normal visitor session instead. If legitimate
customers are blocked, correct only the affected hosting rule.

The AJAX cart handler must accept a confirmed, validated JSON result before
altering counts or showing the floating tray. For HTTP 429 or a non-JSON
failure, it displays a clear retry message, leaves cart state unchanged and
does **not** automatically retry a non-idempotent add request.

**Hostinger caveat:** hPanel may copy the release files without including a
readable `.git` checkout, or PHP permissions may prevent reading `.git/HEAD`.
In that case `/is-sha-valid` returns **503**, not a fabricated match. On the
actual Hostinger hosting workspace, inspect `git rev-parse HEAD` and deployment
destination, and record the result in GitHub. To support archive/FTP deployments
without Git metadata, add an explicit **atomic post-deploy revision receipt**
written by a trusted deployment hook from the exact deployed artifact, with
expiration and stale-release detection; never put an assumed `main` SHA in a
committed PHP constant or pretend a static stale file proves deployment.

## Release workflow

1. Work from the linked issue on a branch. Run `./bapXphp update` for generated
   inventory changes and `./bapXphp ci` before opening the PR.
2. For a UI change, use the owner-authorized installed Playwright CLI on the
   connected development machine to exercise the actual browser at desktop and
   375 px. A built-in Browser is also suitable where available. Record the routes, actions, and results
   in the PR. Use local fixture data for submissions that would change live data.
3. Review the PR diff and resolve review findings. Check that GitHub validation
   passed for the commit under review. Open the PR Ready for Review, not draft.
   Rerun affected checks after fixes or conflicts.
4. Merge into `main` and check the main-branch validation run. Hostinger pulls
   `main` automatically. For UI releases, revisit the affected flows using the
   same real-browser workflow and verify a change specific to the release on the live site.
5. Record the merge commit, tested live URLs, observed results, and any steps
   that still need verification in the linked issue. Close it after its
   acceptance checks are verified.

## Repository Architecture

`bapxmediahub/bapXphpAiBackend` is the independent source repository and the
Hostinger deployment source. All issues, branches, PRs, reviews, and
customer-specific changes belong there. It is no longer a fork and has no
upstream synchronization workflow.

A reusable white-label PHP/AI backend package should be published later as a
separate repository and product. Do not reconnect this deployment repository
to `getwinharris/bapXphpAiBackend`.

## Production Logs

Production operational history belongs in remote MySQL `audit_events`. Use the existing dedicated admin monitoring/outbox views and `bapXphp logs`; there is no generic Audit Log menu. Local `server.log` and `storage/logs/` are ignored runtime artifacts and must never be committed. Use `bapXphp logs --local` only when diagnosing the local PHP server. Do not auto-commit hosted request or error logs: they may contain customer data and each log commit would retrigger deployment.

## Vercel

This application is built for normal PHP hosting, not Vercel. Vercel's official platform is oriented around static output and serverless functions; PHP requires a community runtime such as `vercel-php`, which is not the target architecture for this MySQL-backed `public_html` app. Use Hostinger or another PHP host for production.

## Hostinger Requirements

- Stable PHP 8.3 or newer and MySQL 8.4 or newer for the native platform target. The hosted database reports MariaDB 11.8. CI also exercises admin list queries on MariaDB 11.8. Use portable JSON_EXTRACT and JSON_UNQUOTE expressions when querying both engines.
- cURL, JSON, mbstring, PDO MySQL and OpenSSL extensions.
- Verify actual web PHP and configured database in Admin → System Health; CLI PHP can differ.
- A MariaDB-backed legacy deployment is not MySQL8.4-compliant and needs an independently approved migration, not an automatic code-release cutover.
- `mod_rewrite` enabled.
- Writable `storage/` directory.
- OpenSSL and cURL PHP extensions for encrypted settings and payment/OAuth calls.

## Architecture Notes

- Frontend: PHP-rendered templates.
- Backend: PHP controllers, services, and JSON API endpoints.
- Database: direct hosted MySQL via `.env`, with `APP_URL/remotedb` as the developer/agent fallback.
- Build step: none.
- Email: canonical hosted queue rows are atomically claimed for in-request SMTP attempts. Optional bounded due-pending CLI processing is separate; uncertain/accepted sends are never blindly replayed.

## Directory Structure on Hostinger

```text
/public_html/
  .env
  .htaccess
  index.php
  api/
  app/
  assets/
  docs/
  integrations/
  storage/
  cli/
  views/
```

## Troubleshooting

- 500 error: check PHP version, `.htaccess`, and PHP error logs.
- Data not saving: run `bapXphp db status`, then verify the `BAPX_MYSQL_*` values and hosted database permissions.
- Admin blocked: verify the admin account in remote MySQL and the current Admin -> Settings configuration.
- Razorpay disabled: add live key ID and secret in admin integrations.
- Google login not working: verify the Google Cloud Console has the correct callback URL (`https://sripanchamispiritual.com/auth/google/callback`).
- Emails not sending: check Admin → Integrations → Send a Test Email. It reports the exact SMTP error. For Hostinger use `smtp.hostinger.com`, port 465 (SSL) or 587 (TLS), the full email address as username, and a From Email matching that mailbox.

## Runtime readiness

Run `./bapXphp runtime:check --json --strict` in the intended deployment
environment for a nonzero result on any unmet or unknown requirement. Use the
private **System Health** page for the actual web process. No secrets, raw
connection errors or private paths are printed. A mismatched legacy server is
reported without disabling existing checkout. Read `runtime-requirements.md`
before planning hosting changes; the command is not a migration or backup test.

## Correlated application failures

Dynamic PHP requests expose an `X-Request-Id`; an unhandled failure returns the
same reference in a safe no-store500 response. Search server diagnostics by that
reference, class and source location rather than displaying raw exceptions to
owners or customers. Do not assume a generic error is a DB outage or proof that
an order/mail/action was rolled back. Inspect known operation evidence before
retrying. Static-server/startup failures and previously flushed headers remain
outside this application boundary. See `request-failures.md` and retain normal
read-only live acceptance after releases; never deploy an intentional crash probe.

### Agent UI and deployment timing

Hostinger hPanel's **Advanced → Git → Auto Deployment** is asynchronous. After a green `main` run, the separate SHA-verification workflow now waits **60 seconds** before its bounded polling of `/is-sha-valid?sha=<merge_sha>`; a mismatch/503 stays unverified, not a deployment success. Run independent mobile cart acceptance only after the SHA gate passes. For A2UI releases, confirm the live `GET /api/knowledge/a2ui?view=products&limit=1` JSON envelope, `assets/a2ui-public-renderer.js` and `assets/public-a2ui-webmcp.js`, and then the official WebMCP browser-tool discovery/execution. A2UI access from an unrelated ChatGPT chat requires a separate connector, not the website's WebMCP script. See [A2UI contract](a2ui.md).

After the A2UI release's post-merge SHA verification, the `webmcp_acceptance` job independently checks the live landing page for `search_public_products`, `search_public_articles`, `get_public_contact`, `list_public_services` and `present_public_knowledge`, then verifies the additional `list_shop_results` tool on `/shop`. Separate official-polyfill and native headed-Chrome runs check discovery, read-only execution and safe A2UI rendering. Native Chrome before 159 may omit discovered output schemas; actual output is still validated. The native run requires the real browser API, so it cannot silently pass using the polyfill. Record that browser evidence separately from a direct ChatGPT MCP connector (not installed by WebMCP alone).
