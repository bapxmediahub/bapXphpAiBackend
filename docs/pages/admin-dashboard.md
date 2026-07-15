---
type: doc
title: Admin Dashboard
description: Route /admin - owner dashboard for product, order, and astrology session counts.
category: page
---
# Admin Dashboard

Route: `/admin`

Controller: `AdminController@dashboard`

Purpose: owner dashboard for product, order, and astrology session counts.

Key checks: admin guard redirects guests to `/login`, and counts come from JSON resources rather than placeholder copy.
