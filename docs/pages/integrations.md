# Admin Integrations

Route: `/admin/integrations`

Controller: `AdminController@integrations`

Purpose: configure Razorpay (test/live), Stripe, Google OAuth, SMTP, Meta Pixel, Google Site Kit, Support Bot, and SEO defaults.

## Secrets Storage

- All secrets are encrypted (AES-256-CBC) and stored in `storage/data/secrets.json`.
- The encryption key is auto-generated at `storage/runtime-key.php` on first use.
- Secrets are synced to MySQL via `bapXphp db sync` under the `secrets` collection.
- Never put secrets in `.env` — only `APP_NAME` and `APP_URL` belong there. Secrets go in the remote MySQL database through Admin → Integrations.

## Editable Fields

| Field | Environment variable (fallback) |
|---|---|
| Razorpay Mode, Test ID/Secret, Live ID/Secret | `RAZORPAY_MODE`, `RAZORPAY_TEST_KEY_ID`, etc. |
| Stripe Secret Key | `STRIPE_SECRET_KEY` |
| Google Client ID / Secret | — |
| Meta Pixel ID | `META_PIXEL_ID` |
| Google Analytics ID | `GOOGLE_ANALYTICS_ID` |
| Google Ads ID | `GOOGLE_ADS_ID` |
| Google Site Verification | `GOOGLE_SITE_VERIFICATION` |
| Support Bot API Key / Model | — |
| SMTP Host, Port, Encryption, Username, Password | — |

Key checks: API setup links are visible, secrets are stored outside normal catalog JSON, and Google Calendar/Meet is not requested.
