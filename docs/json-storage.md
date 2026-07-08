# JSON Storage

Collections are stored separately in `storage/data`. Writes use lock files plus temporary files and atomic rename to reduce corruption risk.

The collection schema lives in `storage/schema/collections.php`. Agents should treat that file as the database contract before changing MySQL table shapes or admin forms.
Data is now stored in MySQL tables directly, not in JSON files.

## Collections
- `products.json` - Product catalog
- `categories.json` - Product categories
- `orders.json` - Customer orders
- `users.json` - User accounts (customers, admins, astrologers)
- `appointments.json` - Remote astrologer call/message session requests
- `consultation_messages.json` - Chat messages within active sessions
- `consultation_signals.json` - WebRTC signaling for call sessions
- `astrologers.json` - Astrologer profiles
- `temples.json` - Temple information
- `coupons.json` - Discount coupons
- `contact_submissions.json` - Contact form submissions
- `settings.json` - Site settings and admin credentials
- `secrets.json` - Encrypted API secrets (Razorpay, SMTP, Google OAuth, Stripe, Meta Pixel, Support Bot)
- `audit_events.json` - Admin audit log
- `reviews.json` - Product and astrologer reviews
- `mail_queue.json` - Queued transactional emails
- `mail_inbox.json` - Inbound email records
- `mail_outbox.json` - Outbound email records
- `wallet_transactions.json` - Customer credit top-ups and session spends
- `support_tickets.json` - Support assistant questions and replies
- `media_files.json` - Uploaded media library records

## MySQL Sync

JSON is canonical. MySQL is the query backend for `bapXphp db` CLI operations. Push JSON → MySQL:

```bash
bapXphp db tunnel   # Open SSH tunnel
bapXphp db init     # Create tables from collections.php
bapXphp db sync     # Push all JSON data into MySQL
```

All 21 collections (including encrypted `secrets`) are synced to MySQL with the same structure: `id PK`, `_data JSON`, `_owner`, `_status`, `_created_at`, `_updated_at`.
