---
type: doc
title: Home Page
description: Route / - storefront landing page with product categories, featured products, ordering steps, temple highlights, and more.
category: page
---
# Home Page

Route: `/`

Controller: `PublicController@home`

Purpose: storefront landing page with product categories, featured products, ordering steps, temple highlights, and trust guidance.

Key checks: hero and product-card actions link to `/shop` or product detail pages; malformed remote categories are rejected before rendering; product, ordering, temple, and value sections all reach the response.
The first viewport uses the remote catalog and the Varahi Amman image carousel. Product and category data come from remote MySQL through `DatabaseService`; missing required category identity fields never create blank cards or terminate the page.
