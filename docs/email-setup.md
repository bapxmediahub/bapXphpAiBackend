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

## Public enquiry containment

The existing contact form saves admitted enquiries in Contacts and Email Inbox;
it does not automatically send a visitor confirmation or an owner email.
The server checks bounded Unicode fields and requires 12–4,000 message
characters. A deployment-local private locked JSON admission state applies
3 accepted submissions per IP per 120 seconds, 2 per normalized email per
24 hours, 20 per site per hour and 60 per site per 24 hours. All budgets are
reserved atomically; only hashed bucket identifiers and timestamps are stored.
Unavailable or corrupt state fails closed. Where the owner publishes direct
contact options in the Public Site Profile, users can use them when the form
is limited. Public contact fields are independent of SMTP configuration. These limits are a containment measure,
not proof of human identity or spam classification.

Existing pending contact and legacy appointment automation is excluded from
worker selection and cannot acquire a database-host delivery claim. Outbox
shows the held subset separately from eligible due messages. Historical rows,
including failures and uncertain attempts, are preserved without replay.
Account and commerce notifications retain their existing delivery contract.
Re-enabling automated enquiry mail requires a reviewed anti-abuse and recipient
verification policy; do not remove this guard simply to retry old mail.

## Public account mail admission

Manual signup and recovery have separate atomic deployment-local budgets. Signup: 3 per IP per 5 minutes, 12 per site per hour and 30 per site per day; recovery: 3 per IP per 2 minutes, 20 per site per hour and 80 per site per day. Both apply a 15-minute case-normalized recipient cooldown; signup has 2 and recovery 4 admissions per recipient per day. These are attempt-admission caps, not measured provider quotas. Denied recovery does not issue a new reset token or email. Google-verified registration, completed payments and security notices retain existing paths. No unverified guest contact email is enabled by this change.

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
| Admin Notification Email | The configured owner notification address | Separate from the customer/test mailbox; never replace it during customer QA |

### From Email vs Admin Notification Email

These are deliberately different and are a common source of confusion:

- **From Email** is the mailbox everything is *sent from*, and where customer
  **replies land**. It must be the authenticated mailbox.
- **Admin Notification Email** is where *the owner* is told about new orders and
  bookings. It can be any inbox, including a personal Gmail address.

## Which event sends which message

The authenticated SMTP sender, customer recipient and configured owner recipient
are separate identities. A connected customer Gmail proves only that mailbox's
receipts, never receipt in the owner's different inbox.

| Event | Customer | Owner | Trigger/limitations |
|---|---|---|---|
| New manual registration | Welcome/account access | None | One stable `signup:<account-id>` event after successful creation |
| Newly created Google customer | Same welcome | None | Only a new persisted customer with provider-verified email; existing sign-ins do not resend |
| Ordinary customer login | No routine login email | None | Absence of a login email is not a transport failure |
| Configured admin login | Existing security notice to that admin | Existing security behavior | Not a customer welcome |
| Password recovery requested | Private one-time reset link | None | Existing account only; link is never a public response or a preview credential |
| Successful password change/reset | Security notice | None | Sent to verified account email only after the change succeeds; failure/stale changes do not notify |
| Newly gateway-verified order | Payment/order confirmation, saved item quantities/line totals and secure invoice link when issued | Distinct new-paid-order message with owner order link | Existing deterministic customer/owner event identities prevent repeated calls from duplicating messages |
| Checkout completion not confirmed | Payment-attention guidance | Existing owner failure notice | Never claim the bank made no charge; avoid a second payment while uncertain |
| Shipped / delivered update | Status-specific courier/delivery message | No additional owner confirmation | The saved state determines wording; delivered is not described as newly shipped |
| Product review request | Delayed message | None | Queued for ten days after the existing shipped/delivered update; due-pending processing is still required |
| Contact enquiry | On-page confirmation only | Existing Admin Contacts and Email Inbox | Automatic contact emails are held; unverified visitors cannot trigger SMTP |
| Newsletter | Explicitly selected recipients | Existing workflow | Not an automatic broadcast merely because an article has a date or a preview is opened |

Refund/cancellation changes do not imply an automatic email or PDF attachment
unless an implemented, verified trigger supplies one. Existing invoice links
require the customer to sign in to the matching order. The notification uses
saved order totals and the already-issued invoice number; it never issues an
invoice or reconstructs tax from a synthetic example.

## Templates and controlled sample delivery

**Email → Templates** (`/admin/email-templates`) previews seven allowlisted
customer/owner variants from the same `MailTemplateService` used by delivery.
The shared native HTML shell has event-specific preheaders, table/inline-style
fallbacks, readable item totals and bounded mobile detail cells. Client CSS
support varies; website preview does not certify every email client.

Preview uses synthetic data in a script-disabled sandbox. It sends nothing,
changes no account/order, and does not create an invoice. A separate admin-only,
CSRF-protected form can send one explicitly confirmed sample. The subject AND
body say **TEST SAMPLE / NO ORDER**. Sample order/reset targets are disabled;
a usable password link or private invoice is never invented.

Customer samples go to the explicitly entered customer test address. Owner
samples go ONLY to the stored owner-notification address, ignoring the customer
field; templates cannot be sent under the wrong audience. The owner value is
masked in the UI and is not changed. A short-lived token is consumed before
transport, the queue uses a stable event identity, and requests are bounded by
rate limits. An expired/repeated/uncertain request must not be blindly resent.
The result reports SMTP acceptance and a queue reference, not inbox delivery.

To prove receiving behavior, inspect the intended recipient mailbox and correlate
the sample Message-ID with the queue. A received synthetic sample establishes
that template/transport path only, NOT a real signup, payment or fulfillment
trigger. Never label a sample as an actual order or change production payment
mode to manufacture successful evidence.

## Testing

**Admin → Integrations → Verify email delivery** links to the existing Email Templates workspace. Send one explicitly confirmed synthetic sample through the normal queue. A legacy direct-test POST redirects without sending. Check the canonical queue result and intended inbox separately; do not blindly repeat a failed or uncertain sample.

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

For end-to-end paid-order acceptance, verify an authorized gateway-confirmed
order and actual receipt separately in the customer and owner mailboxes.
Contact, password-reset, generic SMTP and synthetic-template receipts do not
prove that specific order event. Never forge a production paid record or invoice.
Keep concrete issue state and release evidence in GitHub, not this runbook.

References: SMTP responsibility after DATA acceptance is defined in RFC 5321
section 6.1; MIME Base64 line encoding is defined in RFC 2045 section 6.8.

Primary email rendering reference: https://developers.google.com/workspace/gmail/design/css .
Gmail supports style blocks/media queries within its documented subset; do not
claim that all clients universally remove them. Inline/table fallbacks remain.

## Plain-text alternative

Every message remains multipart/alternative. SmtpMailer derives its text part
from authored visible HTML: style/script/head blocks and inline/attribute-hidden
preheaders are excluded, Unicode/entities and block/table separation retained,
and safe absolute HTTP(S)/mailto targets shown beside their action label.
This does not sanitize or change the HTML alternative and never fetches linked
resources. DOMDocument is used for PHP8.3-compatible text extraction, not as an
HTML security sanitizer; arbitrary CSS visibility is not evaluated.

Email → Templates exposes the exact plain-text projection as a native disclosure
without sending. Sample warnings remain visible; inactive sample fragment links
are not turned into real order/invoice/reset URLs. Test actual decoded MIME as
well as browser HTML preview. A renderer repair does not resend historical mail.
Reference: RFC2046 section 5.1.4 and PHP DOMDocument::loadHTML's parser limitations.
