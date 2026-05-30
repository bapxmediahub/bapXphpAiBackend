# JSON Storage

Collections are stored separately in `storage/data`. Writes use lock files plus temporary files and atomic rename to reduce corruption risk. Runtime secrets must live outside Git-tracked collection files.

## Collections
- `products.json` - Product catalog
- `categories.json` - Product categories
- `orders.json` - Customer orders
- `users.json` - User accounts
- `appointments.json` - Remote astrologer call/message session requests
- `astrologers.json` - Astrologer profiles
- `temples.json` - Temple information
- `coupons.json` - Discount coupons
- `contact_submissions.json` - Contact form submissions
- `settings.json` - Site settings
- `audit_events.json` - Admin audit log
