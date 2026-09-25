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
