---
type: doc
title: Temples Page
description: Route /temples - show JSON-backed temple entries with image, address, pooja details, and detail links.
category: page
---
# Temples Page

Route: `/temples`

Controller: `PublicController@temples`

Purpose: show JSON-backed temple entries with image, address, pooja details, and detail links.

Key checks: temple images exist locally when configured, detail links resolve, and map links open externally when present.
