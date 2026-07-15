---
type: doc
title: Shop Page
description: Route /shop - list JSON-backed products, category filters, prices, stock status, product detail links, and add-to-cart forms.
resource: docs/pages/shop.md
tags: [page]
---

# Shop Page

Route: `/shop`

Controller: `PublicController@shop`

Purpose: list JSON-backed products, category filters, prices, stock status, product detail links, and add-to-cart forms.

Key checks: category filters use real slugs, product images exist locally, add-to-cart posts to `/cart/add`, and no inactive coupon UI appears.