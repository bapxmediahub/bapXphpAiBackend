---
type: doc
title: Orders Module
description: Owns cart checkout, payment verification, order storage, shipping status, customer order history, and product review timing.
category: module
---
# Orders Module

Owns cart checkout, payment verification, order storage, shipping status, customer order history, and product review timing.

Main files: `CommerceController.php`, `OrderService.php`, `MailQueueService.php`, checkout/order views.

Key checks: shipping fields persist, admin status updates queue shipment/review emails, and product reviews unlock after `review_request_after_at`.

Verified gateway callbacks use a payment-derived order ID and atomic insert-if-absent storage. If stock changes after checkout, the paid order is stored as `on_hold`, the customer and owner are told that availability needs review, and fulfillment cannot proceed until the owner explicitly confirms availability. Search Admin → Orders for “Fulfillment review required”; contact the customer, confirm stock or arrange a refund, then record the resolution on the order. Callback retries return the original order and do not queue another confirmation.

For payments made before this safeguard, reconcile the gateway export against Admin → Orders by payment ID and investigate any paid ID without an order before creating or refunding anything. When a held order references a missing product, restore the original product tax evidence (HSN and GST rate) before invoice review; the application deliberately refuses to guess those values. Preserve the gateway total and payment ID, document the customer outcome, and never rewrite an already-issued invoice.
