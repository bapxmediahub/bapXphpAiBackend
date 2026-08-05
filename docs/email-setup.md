---
title: Email setup
description: How transactional email is sent, how to configure SMTP, and how to diagnose failures.
---

# Email setup

## How sending works

Mail is sent **in the request that generates it**. There is no cron job and no CLI
script — `cli/process-mail-queue.php` was removed.

```
caller → MailQueueService::enqueue()
           ├─ writes a row to mail_queue (status: pending)
           └─ deliverNow() → SmtpMailer::send() → marks sent | failed
```

The queue row is written **before** delivery is attempted, so a failure leaves an
auditable record carrying the error rather than disappearing. `deliverNow()` never
throws: a mail failure must not break the checkout, signup or password reset that
triggered it. Failed rows are visible in **Admin → Email Outbox**.

`bapXphp mail:process` retries rows already marked failed. It is optional.

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
| Consultation booking | Booking received confirmation | New-appointment notification |

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
