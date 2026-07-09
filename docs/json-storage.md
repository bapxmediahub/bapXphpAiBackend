# Data Storage

MySQL is the primary runtime data store. `DatabaseService` manages all persistence through the MySQL `DatabaseService` connection or the `/remotedb` HTTP proxy fallback in local dev.

## Collections

Tables are defined in `storage/schema/collections.php`. Each collection maps to a MySQL table:

- `products` - Product catalog
- `categories` - Product categories
- `orders` - Customer orders
- `users` - User accounts (customers, admins, astrologers)
- `appointments` - Remote astrologer call/message session requests
- `consultation_messages` - Chat messages within active sessions
- `consultation_signals` - WebRTC signaling for call sessions
- `astrologers` - Astrologer profiles
- `temples` - Temple information
- `coupons` - Discount coupons
- `contact_submissions` - Contact form submissions
- `settings` - Site settings and admin credentials
- `secrets` - API secrets (Razorpay, SMTP, Google OAuth, Stripe, Meta Pixel, Support Bot)
- `audit_events` - Admin audit log
- `reviews` - Product and astrologer reviews
- `mail_queue` - Queued transactional emails
- `mail_inbox` - Inbound email records
- `mail_outbox` - Outbound email records
- `wallet_transactions` - Customer credit top-ups and session spends
- `support_tickets` - Support assistant questions and replies
- `media_files` - Uploaded media library records

## Schema

The collection schema contract lives in `storage/schema/collections.php`. Agents should treat that file as the authoritative contract before changing MySQL table shapes or admin forms.

## CLI

```bash
bapXphp db init     # Create tables from collections.php
bapXphp db sync     # Push seed JSON data into MySQL
bapXphp db query    # Query MySQL tables
```

## Local Dev / Remote Fallback

When the MySQL host (`localhost` on Hostinger) is unreachable (e.g., from a local development machine), `DatabaseService` automatically falls back to the `/remotedb` HTTP proxy:

```bash
curl -X POST https://sripanchamispiritual.com/remotedb \
  -H "Content-Type: application/json" \
  -d '{"token":"<remote_db_token>","query":"SELECT * FROM products LIMIT 5"}'
```

The `remote_db_token` is stored in the MySQL `secrets` table and also hardcoded as a fallback in `config/database.php`. Write operations (`write`, `upsert`, `delete`) throw in remote proxy mode.
