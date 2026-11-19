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

## Owner product directory and import

The product directory counts matching records and reads one page from MySQL. Name order uses product ID as its tie-breaker. Direct edit links load the raw record by ID, so a filter or an inactive offer does not erase editable fields.

CSV preview validates the complete file before offering confirmation. The admin session retains a digest of the reviewed file and its excluded slugs. Confirmation checks the digest, parses the original file again and takes the catalogue lock shared with product-form saves. It checks for existing slugs on the database host and commits all new hidden products together. This path accepts simple products in the sample format. For fields, limits and the owner workflow, see [Catalogue help](../../content/help/admin/catalogue.md).
