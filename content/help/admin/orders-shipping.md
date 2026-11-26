## Find and verify the order

Open Orders and choose the customer's record. Inspect its payment evidence, line items, delivery address and review flags. A gateway reference or a customer screenshot is useful for investigation but is not permission to create an unverified paid order. Avoid asking a customer to pay twice while confirmation is uncertain.

## Prepare delivery

On the order detail, review any tax or fulfillment hold. Use the delivery-state correction or availability confirmation only after checking the actual facts. Update the status, courier name and tracking details through the provided form, then verify the saved record. Shipped orders need the required tracking information; the form explains missing fields.

## Invoices and refunds

An issued invoice is a historical snapshot. View or download it from the order; changing today's settings must not rewrite an old invoice. Refund import requires a real refund reference which the site verifies with the configured gateway. It is not a button for inventing a refund or making a new payment.

## When something is uncertain

Keep the order/payment/refund reference and the exact visible error for the team. Check the stored state before resubmitting. Do not use a generic status change to bypass tax, payment or fulfillment review.

## Save and preview proposed shipping zones

Open **Shipping** to draft an ordered set of state/PIN rules and flat or free
shipping methods. Use **Save shipping draft** to keep the proposal, then reopen
the screen to review or change it. If another owner session has saved since you
opened the editor, your stale save is refused; reopen the latest draft before
editing again.

Use **Preview shipping fee** with a hypothetical item subtotal. The first
matching zone wins. A missing match or a minimum not met returns no fee.
The preview does not save changes. Neither saving nor previewing the proposal
activates rates or changes customer checkout, tax or payments. The existing
shipping configuration is linked separately; an authoritative checkout
integration is still required before charging from these zones.
