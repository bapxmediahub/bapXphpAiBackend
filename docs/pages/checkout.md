# Checkout Page

Route: `/checkout`

Controller: `PublicController@checkout`

Purpose: collect shipping contact details and start Razorpay checkout when payment keys are configured.

Key checks: name, email, phone, address, city, and PIN are posted through payment verification; missing Razorpay config is shown clearly instead of silently failing.
