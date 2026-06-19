# Booking Module

Owns remote astrology session requests and participant rooms.

Main files: `BookingController.php`, `AppointmentService.php`, `views/public/consult.php`, `views/account/bookings.php`.

Key checks: no dated slot picker is used, call/message requests save per-session rate and credits spent, participant-scoped PHP APIs poll messages and WebRTC signaling, astrologers manage status from `/astrologer`, and ended sessions can collect five-star reviews.
