## Start with queue health

Open Email Outbox. The health summary is based on canonical queue records, while the message list is bounded. Pending, in-progress/interrupted, failed, unknown acceptance and recorded sent messages are distinct. Opening the page never retries the backlog.

## Acceptance is not an inbox receipt

**SMTP accepted / receipt unverified** means the sending server accepted the message, or the record is an older sent entry. It does not prove inbox placement. Inspect the message reference and obtain an actual recipient receipt when diagnosing a missing confirmation. A contact/test message does not establish that a particular order confirmation arrived.

## Handle failures safely

An unknown or interrupted attempt needs investigation before any resend: the provider may already have accepted it. Do not repeatedly trigger messages or replay historical queued mail. Ask the authorized team to check provider configuration, limits and the exact outcome. Never paste mail passwords into a support chat or screenshot.

Email Inbox shows stored incoming evidence. Newsletters are a separate selection of outbox records, not authorization to send a campaign. Customer confirmation and owner notification are separate messages; verify the intended recipients for both.
