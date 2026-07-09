# Home Page

Route: `/`

Controller: `PublicController@home`

Purpose: storefront landing page with product categories, featured products, temple highlights, and a looping astrologer carousel.

Key checks: hero buttons link to `/shop` and `/consult`; the hero headline does not include the old Chennai-only wording; all astrologers can rotate through the carousel.
The first viewport uses all ten client-supplied Varahi Amman images as transparent arch-shaped PNG cutouts, displaying one at a time with autoplay and ten accessible navigation dots. The astrologer carousel reads profiles from the MySQL `astrologers` table and shares the consult-page card geometry without fabricated ratings or metadata.
