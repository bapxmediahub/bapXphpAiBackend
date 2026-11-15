---
title: Email setup
description: How transactional email is sent, how to configure SMTP, and how to diagnose failures.
---

# Email setup

## How sending works

Mail is attempted **in the request that generates it**, unless deliberately
scheduled for later. The old `cli/process-mail-queue.php` was removed. The
optional `./bapXphp mail:process` command processes at most 25 due **pending**
messages; it does not retry failed, already accepted, interrupted or unknown
attempts. Do not run it against a historical backlog without reviewing recipients,
events, provider limits and delivery evidence first.

```
caller → MailQueueService::enqueue()
           ├─ canonical mail_queue row (pending), plus best-effort outbox projection
           └─ atomic per-row claim (sending) → SmtpMailer::send()
                ├─ final DATA 250 acknowledgement → sent / accepted_by_smtp
                ├─ definite rejection before acceptance → failed
                └─ missing acknowledgement after DATA → needs_review / unknown
```

The queue row is written **before** delivery is attempted, so a failure leaves an
auditable record carrying the error rather than disappearing. `deliverNow()` never
throws: a mail failure must not break the checkout, signup or password reset that
triggered it. Failed rows are visible in **Admin → Email Outbox**.

A legacy `sent` status or a successful SMTP acknowledgement is **not proof of
inbox delivery**. New records retain a stable Message-ID and explicitly mark
`inbox_receipt=unverified`. Verify an actual recipient receipt and its headers
before closing an end-to-end delivery issue.

The database host locks only the relevant row during claim/completion. A second
worker cannot claim a `sending`, `sent` or `needs_review` message. An interrupted
worker stays blocked for investigation; there is no automatic expired-lease
resend. A lost QUIT acknowledgement after accepted DATA does not undo acceptance.
A status/projection failure after SMTP acceptance is not turned into a retryable
send failure. Queue/outbox updates no longer replace whole collections.

Paid-order customer and owner notifications use distinct deterministic event
identities, so repeating the same new-event call does not create new mail. This
does **not** retroactively deduplicate older random-ID records or guarantee
exactly-once delivery to external mail providers.

## Configuration

All of it lives in **Admin → Integrations**, stored encrypted in the MySQL `secrets`
table. **Never put SMTP settings in `.env`** — `.env` holds only `APP_NAME`,
`APP_URL`, the `BAPX_MYSQL_*` values and `BAPX_REMOTE_DB_URL`.

| Field | Value | Notes |
|---|---|---|
| SMTP Host | `smtp.hostinger.com` | |
| SMTP Port | `465` (SSL) or `587` (TLS/STARTTLS) | Match the Encryption field |
| Encryption | `ssl` for 465, `tls` for 587 | |
| SMTP Username | `support@sripanchamispiritual.com` | **Full email address**, not just `support` |
| SMTP Password | the mailbox password | Set in hPanel → Emails |
| From Email | `support@sripanchamispiritual.com` | **Must match the authenticated mailbox** or the provider rejects the message |
| From Name | `Sri Panchami Spiritual` | |
| Admin Notification Email | `sripanchamispiritual@gmail.com` | Where the **owner** is notified |

### From Email vs Admin Notification Email

These are deliberately different and are a common source of confusion:

- **From Email** is the mailbox everything is *sent from*, and where customer
  **replies land**. It must be the authenticated mailbox.
- **Admin Notification Email** is where *the owner* is told about new orders and
  bookings. It can be any inbox, including a personal Gmail address.

## Who gets what

| Event | Customer | Owner |
|---|---|---|
| Signup | Welcome email | — |
| Forgot password | Reset link (**emailed, never shown on screen**) | — |
| Paid order | Payment confirmation | New-order notification |
| Contact enquiry | Enquiry received confirmation | New-contact notification |

## Testing

**Admin → Integrations → Send a Test Email.** It sends a real message through the
saved settings and reports the transport used, or the exact SMTP error. Use this
before assuming anything else is wrong.

## Diagnosing failures

The test button surfaces the server's own error. Common ones:

**`535 5.7.8 authentication failed`**
The username or password is wrong, or the mailbox does not exist.
- The username must be the **full email address**.
- The password is the **mailbox** password from hPanel → Emails, not the hosting
  account password. If unsure, reset it in hPanel and use the new value.
- Confirm the mailbox actually exists in hPanel → Emails.
- On Gmail specifically (`smtp.gmail.com`), a normal account password will always
  fail — it requires a 16-character **App Password** with 2-Step Verification on.

**`550` / `553` / `554` sender rejected**
From Email does not match the authenticated mailbox. Make them the same.

**`Could not connect`**
Wrong host or port, or outbound SMTP is blocked. Try 587/TLS if 465/SSL fails.
Note that many local development machines block outbound SMTP entirely, so a
connection failure locally does not mean production is broken.

**Timed out waiting for the server**
The port is blocked by the host or a firewall.

## Checking the domain

Mail cannot work if the domain is wrong. The live domain is
`sripanchamispiritual.com`. Verify the MX records resolve:

```bash
host -t MX sripanchamispiritual.com
# → mx1.hostinger.com, mx2.hostinger.com
```

A misspelled domain returns `NXDOMAIN` and no configuration will make it work.

## Queue health and testing

Admin → Email Outbox reconciles its status against the canonical queue, shows
pending/due, sending/interrupted, accepted, failed, needs-review and unknown
counts, and limits the visible message table to 100 rows. It does not process
or resend anything merely by opening the page. Canonical queue rows remain
authoritative when their secondary outbox projection is unavailable.

Run `php tests/smtp-protocol.php` for loopback-only SMTP scenarios: accepted,
accepted then closed before QUIT, explicit DATA rejection, and missing DATA
acknowledgement. No real mailbox or SMTP credential is used. The CI-only
`tests/mail-delivery-concurrency.php` uses the isolated MySQL fixture to prove
one sender among six workers, no replay of unknown outcomes, and preservation
of unrelated records. It must never point at production MySQL.

Current issue #25 retains an external acceptance gate: a verified order's
customer and owner recipients must receive their actual confirmations.
Previous contact, password-reset and admin-test receipts do not prove that
specific order flow. No test payment or artificial paid-order mutation is
permitted simply to obtain a passing test.

References: SMTP responsibility after DATA acceptance is defined in RFC 5321
section 6.1; MIME Base64 line encoding is defined in RFC 2045 section 6.8.
