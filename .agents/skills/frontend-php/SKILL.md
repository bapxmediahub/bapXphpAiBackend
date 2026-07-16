---
type: skill
name: frontend-php
description: Use when editing public, account, shop, astrologer, temple, cart, checkout, contact, or support templates.
---
# PHP Frontend

- Follow the root `AGENTS.md` repository contract and its view/asset area rules.
- Read and follow root `Design.md` before changing any customer-facing UI. Treat it as the canonical token, typography, component, and responsive contract.
- Capture the current desktop and mobile page before editing. Validate the complete customer path from first viewport through primary action and authenticated result, not only isolated markup.
- Before using a browser capture as blog/help media, wait until every represented image reports `complete` with a non-zero `naturalWidth`, reveal content is visible, and no loading skeleton or empty data state remains. Inspect the saved bitmap before attaching it with `bapXphp blog:image`.
- Use `bapXphp dev:user` for repeatable authenticated browser checks. Keep `BAPX_TEST_USER_PASSWORD` only in ignored operator configuration.
- Keep UI as PHP-rendered templates plus existing CSS; do not add React, CDN React, SPA shells, or a second frontend.
- Templates should consume controller-provided data and existing services, not read JSON storage directly.
- Reuse shared tokens and classes in `assets/css/band.css`; keep its tokens synchronized with the critical CSS in `views/layouts/app.php`.
- Preserve the product's real routes and content. Apply the design system surgically instead of copying reference-product labels or scaffolding parallel components.
- Essential content must be legible before JavaScript enhancement. Never make reveal-animation opacity a loading dependency.
- Audit card families together. Product, consultant, account, authentication, and admin cards have different jobs but share the geometry and focus rules in `Design.md`.
- Use browser WebRTC only for call media and the authenticated consultation APIs for polling messages and signaling.
- Reuse the circular top-overlapping marketplace card geometry, face-focused clipped portrait frame, and message/call/profile icon row on home and consult surfaces. Render only real profile metadata/reviews, and keep hero slides isolated on a warm-neutral image frame.
- Keep public help guides in `content/blog/posts/*.md` with `category: help`. Render them through existing blog cards and article pages; `/docs` and `/help/{slug}` are compatibility redirects only.
- Public consultation actions create scheduling requests only. Do not expose live call/message controls or wallet pricing unless that product is explicitly restored.
- Validate with `php -l` for changed templates, `php tests/run.php`, and a browser workflow for changed pages. Codex agents should use `Browser:control-in-app-browser` for local UI checks when available; standalone Playwright is only a fallback for agents or environments without the Browser plugin.
