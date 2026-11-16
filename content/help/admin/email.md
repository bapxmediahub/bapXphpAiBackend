## Start with queue health

Open Email Outbox. The health summary is based on canonical queue records, while the message list is bounded. Pending, in-progress/interrupted, failed, unknown acceptance and recorded sent messages are distinct. Opening the page never retries the backlog.

## Acceptance is not an inbox receipt

**SMTP accepted / receipt unverified** means the sending server accepted the message, or the record is an older sent entry. It does not prove inbox placement. Inspect the message reference and obtain an actual recipient receipt when diagnosing a missing confirmation. A contact/test message does not establish that a particular order confirmation arrived.

## Handle failures safely

An unknown or interrupted attempt needs investigation before any resend: the provider may already have accepted it. Do not repeatedly trigger messages or replay historical queued mail. Ask the authorized team to check provider configuration, limits and the exact outcome. Never paste mail passwords into a support chat or screenshot.

Email Inbox shows stored incoming evidence. Newsletters are a separate selection of outbox records, not authorization to send a campaign. Customer confirmation and owner notification are separate messages; verify the intended recipients for both.

## Preview templates without sending

Open **Email → Templates** to choose an actual account, order, payment-attention,
shipment or security-message layout. The preview is marked **TEST SAMPLE ONLY**
and contains example data. It does not create an account, order, invoice or usable
reset link. The customer and owner variants use different actions and audiences.
An order email links to the real issued invoice when present; it does not promise
a PDF attachment.

## Verify the correct recipient

For a diagnostic, explicitly confirm **Send test sample once**. Customer templates
use the entered customer test mailbox. Owner templates use only the separately
configured owner address and cannot be redirected using that field. Review the
masked role/address before sending; do not change the saved owner recipient just
to make a customer Gmail test pass.

The result and Email Outbox distinguish transport acceptance from receipt. Inspect
the correct inbox and message reference. A received test sample proves that
sample's arrival, not that a real payment, signup or invoice was created. A normal
customer login does not send a new welcome; a first-time customer account does.
Do not resend an uncertain request, replay the old queue or place a live charge
solely to obtain a passing diagnostic.
