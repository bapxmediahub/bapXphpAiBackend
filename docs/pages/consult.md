---
type: doc
title: Retired Consult URL
description: Route /consult redirects visitors to the spiritual product shop.
category: page
---
# Retired Consult URL

Route: `/consult`

Controller: `PublicController@consult`

Purpose: preserve old links while directing visitors to the product shop.

Key checks: `/consult` and `/consult/{slug}` redirect to `/shop`; no public consultant directory or booking form is rendered.
