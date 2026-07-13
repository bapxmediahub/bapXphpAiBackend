# Home Page

Route: `/`

Controller: `PublicController@home`

Purpose: storefront landing page with product categories, featured products, temple highlights, and a looping astrologer carousel.

Key checks: hero buttons link to `/shop` and `/consult`; the hero headline does not include the old Chennai-only wording; all astrologers can rotate through the carousel.
The first viewport uses the remote catalog and media metadata for the Varahi Amman image carousel. The astrologer carousel reads live provider profiles through `DatabaseService` and shares the consult-page card geometry without fabricated ratings or metadata.
