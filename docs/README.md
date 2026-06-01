# Documentation Index

This folder contains connected documentation for developers, maintainers, and coding agents building on Sri Panchami Spiritual.

## Start Here

- [Main README](../README.md): repo overview, local setup, deployment summary, and documentation links.
- [Agent workflow](../example-Agent.md): required workflow for Codex, Claude Code, Hermes-style agents, and other coding agents.
- [Architecture](architecture.md): current PHP-template architecture and file structure.
- [Deployment guide](deployment-hostinger.md): Hostinger Git auto deployment, branch setup, cron, and Vercel note.
- [Project map](PROJECT_MAP.md): generated route/controller/service map.
- [JSON storage](json-storage.md): JSON collections and persistence notes.
- [Agentic monorepo](agentic-monorepo.md): repo-native backend primitives and built-in agent guidance.
- [Schema registry](schema.md): JSON database schema and agent context contract.

## Page Notes

- [Home](pages/home.md)
- [Shop](pages/shop.md)
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
- [Catalog](modules/catalog.md)
- [Google OAuth](modules/google-oauth.md)
- [Orders](modules/orders.md)
- [Razorpay](modules/razorpay.md)
- [Temples](modules/temples.md)

## Generated Files

- `PROJECT_MAP.md`, `project-map.json`, and `project-map.mmd` are generated from `App\Services\ProjectMapService`.
- Regenerate them after route or service changes:

```bash
php tools/generate-project-map.php
```
