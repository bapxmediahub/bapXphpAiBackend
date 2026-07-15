---
type: doc
title: Home Page
description: Route / - storefront landing page with product categories, featured products, ordering steps, consultant bookings, and more.
category: page
---
# Home Page

Route: `/`

Controller: `PublicController@home`

Purpose: storefront landing page with product categories, featured products, ordering steps, scheduled consultant bookings, temple highlights, and trust guidance.

Key checks: hero buttons link to `/shop` and `/consult`; malformed remote categories are rejected before rendering; product, ordering, consultant, temple, and value sections all reach the response; consultant cards do not expose live availability.
The first viewport uses the remote catalog and the Varahi Amman image carousel. Product/category data and consultant profiles come from remote MySQL through `DatabaseService`; missing required category identity fields never create blank cards or terminate the page.
