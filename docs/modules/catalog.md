---
type: doc
title: Catalog Module
description: Owns product, category, temple, and historical service-record reads from MySQL via DatabaseService.
category: module
---
# Catalog Module

Owns product, category, temple, and historical service-record reads from MySQL via DatabaseService.

Main files: `ProductService.php`, `CategoryService.php`, `TempleService.php`, `AstrologerService.php`, and the remote MySQL collections declared in `storage/schema/collections.php`. Astrologer records are owner-only after public booking retirement.

Key checks: configured image paths exist locally, slugs resolve to detail pages, and admin resource forms can edit catalog fields.

## Google product export

MerchantFeedService exposes a read-only projection of the same ProductService
prices and visibility used by the shop. The public RSS file and authenticated
Google & YouTube readiness page share it with the developer CLI; they do not
change catalogue storage or grant Google account access. See
`docs/google-sales-channels.md` for exact fields, exclusions and external gates.
