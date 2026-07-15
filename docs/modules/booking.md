---
type: doc
title: Booking Module
description: Owns remote astrology session requests and participant rooms.
category: module
---
# Booking Module

Owns remote astrology session requests and participant rooms.

Main files: `ConsultationController.php` (initiate), `AppointmentService.php`, `views/public/consult.php`, `views/account/bookings.php`.

Key checks: customers request a date and time, the central admin receives the full request through SMTP, appointment status is admin-managed, and ended sessions can collect five-star reviews.
