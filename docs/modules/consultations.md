---
type: doc
title: Consultation Bookings
description: Customers browse /consult, open a consultant profile, and submit a preferred date, time, phone number, and guidance notes.
category: module
---
# Consultation Bookings

Customers browse `/consult`, open a consultant profile, and submit a preferred date, time, phone number, and guidance notes. The request is stored as an appointment and appears in the customer, consultant, and admin booking views.

The product does not expose live call, message, wallet, credit, or WebRTC controls. Consultants update booking status through the authenticated provider panel.

Consultant profiles are managed only by the central administrator. New requests queue a detailed SMTP notification to the configured site mailbox and are reviewed under `/admin/appointments`; consultants do not receive application login IDs.
