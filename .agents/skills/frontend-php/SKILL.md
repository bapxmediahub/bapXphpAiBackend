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
- Keep enquiry card label contrast in the shared stylesheet. Test the native
  contact link and measure the shared label with the About stylesheet blocked.
- Render authored campaign forms after Markdown with the contact form styles.
  Keep the native submission, preserve validated text on recoverable errors,
  and verify the focused error and receipt states at desktop and 375px.
  Keep owner previews visibly disabled.
- Render campaign download names and URLs only after the controller confirms
  access. Put locked-state guidance beside the article and preserve native
  download and enquiry behavior at 375px without JavaScript.
- Campaign bookings use a native form and a private status page. Verify pending,
  expired and captured states on desktop and at 375px. Put downloadable files on
  the captured receipt only and check the correct version with the same browser.
- Use the shared public site profile for the header, footer, About and Contact.
  Test absent contact methods, app metadata and module-off URLs in an
  independent synthetic deployment as well as the reference layout.
- Search results keep the query in a labelled native form with grouped results
  and a clear empty state. At 375px, check long queries, command examples in public
  documents, and the focusable invoice table. Tab to the order review rating
  and use Arrow keys to check visible focus and selection.
- In Profile, display initials in the avatar slot from the first render. Reveal an HTTPS photo after it loads and keep the initials for absent, failed or blocked images. Check the slot and form without JavaScript and at 375px with a long email.
- Audit card families together. Product, consultant, account, authentication, and admin cards have different jobs but share the geometry and focus rules in `Design.md`.
- Reuse the circular top-overlapping marketplace card geometry and face-focused clipped portrait frame on home and consultation surfaces. Render only real profile metadata and reviews, and keep hero slides isolated on a warm-neutral image frame.
- Keep public help guides in `content/blog/posts/*.md` with `category: help`. Render them through existing blog cards and article pages; `/docs` and `/help/{slug}` are compatibility redirects only.
- Public consultation booking is retired. `/consult` redirects to the shop, and legacy submissions go to the enquiry form. Historical appointment records do not establish bookable availability. Do not expose booking, live calls, messaging, signaling or wallet pricing; a future booking workflow requires an explicit product change with matching routes, authorization, UI, schema and tests.
- Validate with `php -l` for changed templates, `php tests/run.php`, and the active coding client's browser workflow for changed pages. Do not add a browser runtime or browser dependency to the application repository.

## Testing

Verify in a browser at desktop **and** 375px mobile — `Design.md` is canonical for responsive behaviour. Check computed styles, not just the screenshot: an author rule such as `display:flex` silently overrides `[hidden]`, which breaks JS filters without any visible error. Then `./bapXphp test`.

Check the product's labelled quantity, both native purchase submits, the
unavailable state, and the gallery at each width. Compare stock gating against
the cart controller. Check the category card link, shop empty-search recovery,
cart unit and line amounts, and the checkout review jump and return to the cart
with keyboard and pointer. Empty homepage product sections retain a visible
shop link and skip the empty carousel controls.

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
Check the same-session A2UI transcript and conversation ID after reopening
Support. Verify that an actor or session change gets a fresh transcript and
New chat resets the conversation independently of the checkout receipt.

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
Site-wide Search is a fixed magnifier beside Cart. Keep the top-centered native
dialog and direct link fallback. Check field focus, Escape with a filled field,
and real results at desktop and 375px. Verify the 375px Menu and Close menu
links with JavaScript disabled, including keyboard operation.
Verify that Skip to content focuses the main region and that Escape closes the
enhanced mobile menu and returns focus to its toggle. In the footer, keep each
destination in one group and show Journal only when Blog is enabled.

When Support opens, focus the visible pane heading without opening the mobile
keyboard. Return focus to the launcher on Close or Escape, and to the initiating
link when returning from an enquiry. Keep Close at 44px and verify the composer
remains visible on a short screen with the cart tray present.

## Public browser tools

Register public business discovery from the shared layout so it works on `/`.
Keep contact/services available when Shop and Blog are off; keep the visible
shop-results snapshot scoped to its real DOM. Load the official pinned polyfill
before adapters without replacing native document.modelContext. Await
registerTool to support native void and promise implementations, catch refusal,
and use AbortSignal lifecycle cleanup. Public GETs omit credentials and consume
the shared PHP schemas. Verify native isolated-world discovery separately from
polyfill tests; neither implies installation in an external chat. See docs/webmcp.md.

## Structured Support answers

Use `AgentAnswerPresentationService` for already-authorized answer sections and `PublicA2uiService` for published references. Render the surface as the primary reply with `{scope:"support"}`; keep the full original reply when the renderer rejects unsupported data. Never append a second flat copy. Query named products and articles rather than substituting an unrelated catalogue sample. Keep public images and navigation bounded; format stored replay text without fetching new prices. Verify primary rendering, complete fallback, reload, keyboard links and no overflow at 1280px and 375px with `tests/a2ui-public-browser.cjs`. See `docs/a2ui.md` for public versus actor-scoped contracts.

Use the fixed product grid/list/detail/comparison, article list, contact panel and service enquiry families. Detail/comparison fields come from the selected canonical public products; resolve named comparisons through the shared published-product resolver and exact slugs, never an all-token `A versus B` search. Missing attributes remain missing or explicitly Not listed. Contact is one panel with its canonical fields and one enquiry link. Responsive rules use container width because desktop drawers and external apps can be narrow. Additional photos use native disclosures so controls remain keyboard-operable after Support restores saved HTML. Test the disclosure after real session reload, not only after initial rendering. Public card families cannot include owner reports or private account records.

## Fixed chat checkout components

Reuse `BapxShopping` and `BapxCheckout` for native Support and MCP Apps. The native
`support-checkout.js` adapter calls only fixed public shopping tools through
`/api/mcp` with `credentials: 'omit'`; it does not extend owner or Support model
permissions. Keep validated product card controls usable after saved HTML replay.
PHP remains authoritative for products, delivery, discounts, totals and payment.
Do not add write actions or arbitrary form/event interpretation to the public
A2UI renderer. Enterprise reports never opt into the shopping component.

Mount the native form outside `support-log`, whose HTML is saved for chat history.
Only a bounded sessionStorage capability receipt may survive native reload: cart
and payment-status tokens, revision and expiry. Reject extra fields, refresh from
the server before displaying facts, clear expired/reset handles and show a recovery
warning when storage is unavailable. Do not save delivery/contact/coupon data or
reuse this storage mechanism in external MCP Apps. Abort stale work and clear form
drafts on teardown; refresh status after a back/forward-cache restore.
Preserve validated recovery access on temporary network/service errors, with an explicit
retry and no stale facts. A disabled Shop keeps only existing payment-status recovery;
remove restored product-action controls and block every new cart or checkout action.

Browser WebMCP uses `BapxSupportCheckout.acceptToolResult(name,args,result,{signal})`
to await the shared canonical render. Validate the fixed result family and capability
binding, strip delivery/coupon arguments and unrelated App metadata, and preserve the
active human checkout when a queued external result is malformed or canceled.

Payment preparation and navigation require separate human actions. Open only the
validated merchant payment page from chat; do not inject third-party payment
scripts or frames into chat. The ordinary checkout page retains its provider UI.
Its asynchronous verification callback must contain failures, preserve the original
payment receipt for retry and distinguish verified-but-unsaved orders from uncertain
payments. Run the focused Node checks in `tests/checkout-payment.test.cjs` and
`tests/support-checkout.test.cjs`, plus the desktop/375px native checkout browser fixture.

For reusable About pages, keep deployment-specific prose and approved media in
config/about.php; use the canonical public contact and SEO services and the
existing module-filtered navigation targets. Do not invent brand history or
duplicate the domain in the template. Test the real route, configured origin,
disabled modules, image loading, contact navigation and no-JavaScript phone layout.
