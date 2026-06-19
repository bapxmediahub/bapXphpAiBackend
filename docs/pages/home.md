# Home Page

Route: `/`

Controller: `PublicController@home`

Purpose: storefront landing page with product categories, featured products, temple highlights, and a looping astrologer carousel.

Key checks: hero buttons link to `/shop` and `/consult`; the hero headline does not include the old Chennai-only wording; all astrologers can rotate through the carousel.
The first viewport uses all ten client-supplied Varahi Amman images one at a time in a white, accessible rotating hero. The astrologer carousel reads the 21 client profiles from `storage/data/astrologers.json` and shares the consult-page card geometry without fabricated ratings or metadata.
