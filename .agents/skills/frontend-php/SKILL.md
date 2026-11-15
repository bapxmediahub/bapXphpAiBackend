---
name: frontend-php
description: Use when editing public, account, shop, consultant, temple, cart, checkout, contact, support, or other customer-facing PHP templates.
---
# PHP Frontend

- Follow the root `AGENTS.md` repository contract and its view/asset area rules.
- Read and follow root `Design.md` before changing any customer-facing UI. Treat it as the canonical token, typography, component, and responsive contract.
- Capture the current desktop and mobile page before editing. Validate the complete customer path from first viewport through primary action and authenticated result, not only isolated markup.
- Before using a browser capture as blog/help media, wait until every represented image reports `complete` with a non-zero `naturalWidth`, reveal content is visible, and no loading skeleton or empty data state remains. Inspect the saved bitmap before attaching it with `bapXphp blog:image`.
- Use `bapXphp dev:user` for repeatable authenticated browser checks. Keep `BAPX_TEST_USER_PASSWORD` only in ignored operator configuration.
- Keep UI as PHP-rendered templates plus existing CSS; do not add React, CDN React, SPA shells, or a second frontend.
- Templates consume controller-provided data and existing services; they never read databases, local YAML catalogues, or generated indexes directly.
- Reuse shared tokens and classes in `assets/css/band.css`; keep its tokens synchronized with the critical CSS in `views/layouts/app.php`.
- Preserve the product's real routes and content. Apply the design system surgically instead of copying reference-product labels or scaffolding parallel components.
- Essential content must be legible before JavaScript enhancement. Never make reveal-animation opacity a loading dependency.
- Audit card families together. Product, consultant, account, authentication, and admin cards have different jobs but share the geometry and focus rules in `Design.md`.
- Reuse the circular top-overlapping marketplace card geometry and face-focused clipped portrait frame on home and consultation surfaces. Render only real profile metadata and reviews, and keep hero slides isolated on a warm-neutral image frame.
- Keep public help guides in `content/blog/posts/*.md` with `category: help`. Render them through existing blog cards and article pages; `/docs` and `/help/{slug}` are compatibility redirects only.
- Public consultation actions create scheduled appointment requests only. Do not expose live calls, messaging, signaling, or wallet pricing. `ConsultationService` and the consultation message/signal collections are retained legacy code, not permission to surface those features; restore them only under an explicit product change with matching routes, authorization, UI, schema, and tests.
- Validate with `php -l` for changed templates, `php tests/run.php`, and the active coding client's browser workflow for changed pages. Do not add a browser runtime or browser dependency to the application repository.

## Testing

Verify in a browser at desktop **and** 375px mobile — `Design.md` is canonical for responsive behaviour. Check computed styles, not just the screenshot: an author rule such as `display:flex` silently overrides `[hidden]`, which breaks JS filters without any visible error. Then `./bapXphp test`.

## Keeping this skill current

Update when `Design.md` tokens, breakpoints, or shared component classes change. Public pages are gated by `module_on()`; re-check with each module both on and off.

## Support agent customer feedback

The public Support widget in `views/layouts/app.php` posts
`/support/ask` and can send an explicit rating to
`/support/feedback` with CSRF and the latest turn ID.
Do not assume a support reply is customer resolution. Positive feedback
means confirmed resolved, negative means needs follow-up; subsequent turns
reset resolution. The PHP controller validates session ownership so
browser-supplied conversation IDs cannot access another customer.
Test the actual browser post/feedback interaction at desktop and 375px,
including no JavaScript exceptions and saved JSON status on the local fixture.

## Shared scroll/motion

For animations read `.agents/skills/site-motion/SKILL.md` and `docs/site-motion.md`.
Keep native scroll and actual commerce/form state; no zero-opacity dependency.
Both layouts use the same progressive asset and respect reduced-motion/no-JS.
Stageshow's authored illustration kit is not a runtime application framework.

## Consistent task help

Public contextual help must link only to a published customer Help article,
respect the Blog module switch and remain a normal link without JavaScript.
Operator Help is authenticated and lives in the admin shell. Keep the Support
guide link outside its replaceable conversation log, and do not insert private
operator instructions into public layouts or business-agent prompts.
