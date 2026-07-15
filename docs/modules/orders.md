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
