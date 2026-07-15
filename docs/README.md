---
type: doc
title: Documentation Index
description: Connected documentation for developers, maintainers, and coding agents building on Sri Panchami Spiritual.
category: docs
---
# Documentation Index

This folder contains connected documentation for developers, maintainers, and coding agents building on Sri Panchami Spiritual.

## Start Here

- [Main README](../README.md): repo overview, local setup, deployment summary, and documentation links.
- [Agent operating guide](../AGENTS.md): required DOX workflow for coding agents.
- [Architecture](architecture.md): current PHP-template architecture and file structure.
- [Deployment guide](deployment-hostinger.md): Hostinger Git auto deployment, branch setup, cron, and Vercel note.
- [Systematic project map](systematic-map.mmd): generated route/controller/service/view/schema/storage/tool/integration/gap map.
- [Storage](json-storage.md): MySQL persistence and schema notes.
- [Agentic monorepo](agentic-monorepo.md): repo-native backend primitives and built-in agent guidance.
- [Schema registry](schema.md): MySQL schema contract and agent context fields.
- [Storefront reference review](competitor-review.md): commerce-polish comparison and release checklist.

## Page Notes

- [Home](pages/home.md)
- [Shop](pages/shop.md)
- [Blog](pages/blog.md)
- [Checkout](pages/checkout.md)
- [Consult](pages/consult.md)
- [Temples](pages/temples.md)
- [About](pages/about.md)
- [Admin dashboard](pages/admin-dashboard.md)
- [Integrations](pages/integrations.md)
- [Project map page](pages/project-map.md)

## Module Notes

- [Admin](modules/admin.md)
- [Auth](modules/auth.md)
- [Booking](modules/booking.md)
- [Consultation communication](modules/consultations.md)
- [Catalog](modules/catalog.md)
- [Google OAuth](modules/google-oauth.md)
- [Orders](modules/orders.md)
- [PWA](modules/pwa.md)
- [Razorpay](modules/razorpay.md)
- [Remote DB](modules/remote-db.md)
- [Temples](modules/temples.md)

## Generated Files

- `systematic-map.mmd` is generated from `App\Services\ProjectMapService`.
- Regenerate it after route, service, view, schema, storage, tool, or integration changes:

```bash
bapXphp update
bapXphp ci
```
