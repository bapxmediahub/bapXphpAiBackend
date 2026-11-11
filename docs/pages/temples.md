---
type: doc
title: Temples Page
description: Route /temples - show MySQL-backed temple entries with image, address, pooja details, and detail links.
category: page
---
# Temples Page

Route: `/temples`

Controller: `PublicController@temples`

Purpose: show hosted MySQL-backed temple entries with image, address, pooja details, and detail links without limiting the listing to one city.

Key checks: temple images exist locally when configured, detail links resolve, and map links open externally when present.
