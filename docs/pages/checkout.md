---
type: doc
title: Checkout Page
description: Route /checkout - collect shipping contact details and start Razorpay checkout when payment keys are configured.
category: page
---
# Checkout Page

Route: `/checkout`

Controller: `PublicController@checkout`

Purpose: collect shipping contact details and start Razorpay checkout when payment keys are configured.

Key checks: name, email, phone, address, city, and PIN are posted through payment verification; missing Razorpay config is shown clearly instead of silently failing. The payment button creates a server-side Razorpay order, opens Standard Checkout, handles cancellation and failed payments, then verifies the returned payment id, order id, and signature before saving the order.
