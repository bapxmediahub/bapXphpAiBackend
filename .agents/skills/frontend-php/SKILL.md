---
name: frontend-php
description: Use when editing public, account, shop, consultant, temple, cart, checkout, contact, support, or other customer-facing PHP templates.
---
# PHP Frontend

- Follow the root `AGENTS.md` repository contract and its view/asset area rules.
- Start with root `Design.md` and its shared tokens. Adjust visual defaults when the browser shows clearer hierarchy or easier interaction. Soft shadows can distinguish items, summaries, and floating actions. Keep contrast and focus visible.
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

Update this skill and `Design.md` together when a reusable UI pattern changes.
Update `AGENTS.md` when the working guidance changes. Capture the change on a
representative page instead of relying on token values alone. For carts, verify
the visible checkout action and current total through item changes, mobile
navigation, scrolling, and the empty state. Public pages are gated by `module_on()`; re-check with each module both on and off.

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
On the home opening, check that short viewports can reach Shop Products before
the reassurance strip passes over the hero. Verify section links, both product
purchase locations and the native FAQ disclosure with real input.

## Consistent task help

Public contextual help must link only to a published customer Help article,
respect the Blog module switch and remain a normal link without JavaScript.
Operator Help is authenticated and lives in the admin shell. Keep the Support
guide link outside its replaceable conversation log, and do not insert private
operator instructions into public layouts or business-agent prompts.

## Google tag rendering boundary

Use the existing GoogleSiteKitClient for the public head and owner configuration
states; do not reintroduce inline IDs in JavaScript or a second renderer.
HTML-escape attributes, JSON-encode script strings, validate intended ID/token
formats and never label a nonempty field collected/verified/connected. Test
hostile values in isolated fixtures only. GoogleReportingClient uses separate admin consent, property discovery and server-side reports. Preserve customer login scopes and distinguish missing rows from quota, authorization and provider errors.

## JavaScript interactions

Use small native JavaScript enhancements for task state and feedback. Bind
controls with descriptive accessible names and expose selected or expanded
state on the control. Keep server-owned validation and native form or link
paths where the task supports them. Check real pointer and keyboard actions,
failure feedback, and mobile computed visibility in the browser.


Render the public header from SettingsService's approved menu selection.
Keep Shop/Blog module gating and the Blog dropdown consistent with the editor.
Verify the 375px Menu and Close menu links with JavaScript disabled, including
keyboard operation and the independent mobile shortcut bar.

## Public browser tools

Register public business discovery from the shared layout so it works on `/`.
Keep contact/services available when Shop and Blog are off; keep the visible
shop-results snapshot scoped to its real DOM. Load the official pinned polyfill
before adapters without replacing native document.modelContext. Await
registerTool to support native void and promise implementations, catch refusal,
and use AbortSignal lifecycle cleanup. Public GETs omit credentials and consume
the shared PHP schemas. Verify native isolated-world discovery separately from
polyfill tests; neither implies installation in an external chat. See docs/webmcp.md.
