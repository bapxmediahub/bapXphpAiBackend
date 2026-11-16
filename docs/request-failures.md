---
type: doc
title: Correlated Request Failures and Response Integrity
description: Native PHP error responses discard partial output, preserve honest operation uncertainty and provide a safe request reference.
category: docs
---

# An error page must not look like a successful operation

`app/bootstrap.php` installs `RequestFailureService` before dynamic request
session/environment work. The service holds application response output until
completion. On an uncaught exception or a supported fatal shutdown after that
boundary, it replaces partial content with a standalone HTTP 500 response.
A large partially rendered owner page, nested template buffers or previously
prepared download headers must not escape as an apparent HTTP 200 success.

This applies only to requests loading the PHP application. Existing static and
persistent-media serving paths are unchanged. Ordinary successful HTML, JSON,
XML, binary bodies, redirects, intentional 204/404/503 responses and existing
CSRF/controller responses retain their original behavior. CLI retains a nonzero
exit status and bounded class/file/line diagnostics rather than a web page.

## Safe client-visible evidence

Each dynamic request has a server-generated 32-hex `X-Request-Id`. Incoming
request-ID headers are not trusted as that identifier. The error body repeats
the reference so a user can report it without copying private page contents.
The handler logs the reference, exception class and bounded source location,
plus an already-present valid agent trace ID where available. It does not log
raw exception messages, SQL, credentials, request bodies or session records.
Native PHP/web-server logs remain separately governed by host configuration.

JSON is used for API/remotedb and the existing Support/agent ask endpoints, an
already selected JSON response, or a request accepting JSON/`+json`. It contains
an error summary, request reference and cautious recovery guidance—not a stack,
raw exception or admin-only secret-bearing details. Browser errors use a static
HTML page with native Home/Contact links. HEAD failure responses have no body.

Errors set private/no-store and nosniff headers. Stale Location, Content-Length,
Content-Disposition, content encoding and cache validators are removed before
the new failure response; session/authentication cookies are not rewritten.
The handler itself does not depend on templates, the database or model providers.

## Do not infer business rollback

A rendering or transport error can occur after a payment, ticket, mail attempt
or other change has completed. The generic error must not say “nothing was
recorded” or automatically invite a retry. Check the current operation record
or ask support before repeating consequential actions. The handler neither
rolls back a completed business action nor authorizes its replay.

HTTP 500 means an unhandled request failure, not an assumed database outage.
Existing explicitly handled service-unavailable responses can still return
503. Investigate the reference and source evidence before naming a dependency
as the cause. This distinction is especially important for smoke checks and
clients that previously accepted a partial 200 response as success.

## Buffer and environment boundaries

The owned output buffer is non-flushable/non-removable by normal template buffer
cleanup, but nested application buffers continue to work. Failure replacement
is returned by the output callback; the callback never calls output-buffer
manipulation functions. There is no new streaming API or framework. Full dynamic
responses are buffered in memory; large exports and future streaming endpoints
need a separately designed response contract.

The boundary cannot repair output/headers flushed before it was installed,
web-server/startup failures, process termination, or every resource-exhaustion
condition. It logs an already-sent boundary state rather than pretending the
status was corrected. It is not a replacement for host monitoring or safe
application-specific transactions and retry rules.

## Verification

`php tests/http-response-boundary.php` starts a disposable loopback-only PHP
server with production DB access disabled and output_buffering=0. It covers
large partial views, signed-in-role fixtures, JSON, nested buffers, stale headers,
fatal/parse errors, HEAD and ordinary successful/redirect/unavailable bodies.
The script is CLI-only and creates no deployed crash route. It is invoked by
the standard regression suite on each CI PHP version.

Browser checks inspect the isolated 500 body at desktop and 375px, real recovery
links, no-JavaScript behavior and correlated JSON. Release checks exercise
normal deployed public/admin/API/feed paths and generated request references;
never trigger intentional faults or change customer data on production merely
to reproduce the fixture. A status assertion alone is not sufficient: inspect
meaningful page content, privacy and console/runtime errors.

Primary PHP references:
- https://www.php.net/manual/en/function.ob-start.php
- https://www.php.net/manual/en/function.headers-sent.php
- https://www.php.net/manual/en/function.register-shutdown-function.php
- https://www.php.net/manual/en/function.error-get-last.php
