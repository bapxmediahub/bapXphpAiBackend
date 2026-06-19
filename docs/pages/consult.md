# Consult Page

Route: `/consult`

Controller: `PublicController@consult`

Purpose: show remote astrology experts with credit pricing, filters, status, waitlist/offline states, and icon-only call/message actions.

Key checks: buttons post to `/appointments/book`, busy state says `Waitlist`, old `JOIN Q` text is absent, and guest users are redirected to login.
Astrologer cards use one full-width portrait frame and stable body/action geometry. Availability, rates, languages, experience, and review totals come from stored profile or verified review data; missing values are omitted rather than replaced with placeholders.
