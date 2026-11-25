---
version: alpha
name: Sri Panchami Spiritual
description: Calm, credible interface system for a spiritual-products storefront with supporting temple guides and editorial content.
colors:
  primary: "#3a0003"
  on-primary: "#ffffff"
  primary-container: "#651016"
  on-primary-container: "#f3e8c9"
  secondary: "#7a4a35"
  on-secondary: "#ffffff"
  secondary-container: "#a67a64"
  on-secondary-container: "#faf7f0"
  tertiary: "#d1b368"
  on-tertiary: "#3a0003"
  tertiary-container: "#f3e8c9"
  on-tertiary-container: "#5c4315"
  neutral: "#faf7f0"
  neutral-variant: "#f7f0e4"
  surface: "#faf7f0"
  surface-container: "#f6ede4"
  outline: "#d8ccb7"
  outline-variant: "#eadfcd"
  ink: "#222222"
  ink-muted: "#6a6259"
  ink-soft: "#91877c"
  success: "#2d8a4e"
  warning: "#e8a317"
  error: "#d64045"
  info: "#3b82f6"
typography:
  display:
    fontFamily: Inter
    fontSize: 1.75rem
    fontWeight: 700
    lineHeight: 1.2
  h2:
    fontFamily: Inter
    fontSize: 1.375rem
    fontWeight: 600
    lineHeight: 1.3
  body-md:
    fontFamily: Inter
    fontSize: 1rem
    fontWeight: 400
    lineHeight: 1.6
  body-sm:
    fontFamily: Inter
    fontSize: 0.875rem
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: Inter
    fontSize: 0.8rem
    fontWeight: 600
    lineHeight: 1.4
  accent-italic:
    fontFamily: Playfair Display
    fontSize: 1.05rem
    fontWeight: 600
rounded:
  xs: 4px
  sm: 8px
  md: 8px
  lg: 8px
  xl: 8px
  pill: 999px
spacing:
  2xs: 2px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  2xl: 48px
  3xl: 64px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    rounded: "{rounded.sm}"
    padding: 14px 32px
    height: 48px
  button-primary-hover:
    backgroundColor: "{colors.primary-container}"
  button-secondary:
    backgroundColor: "{colors.tertiary}"
    textColor: "{colors.on-tertiary}"
    rounded: "{rounded.sm}"
    height: 48px
  card-product:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.sm}"
    padding: 16px
  input:
    backgroundColor: "{colors.on-primary}"
    rounded: "{rounded.sm}"
    height: 48px
---

## Overview

Homepage merchandising uses a high-contrast devotional hero with real brand imagery and an accessible slide selector. Product sections retain actual commerce actions and must not imply repeat purchasing from sales totals. Admin collection editing uses searchable product rows, a persistent selection count, distinct homepage and shop visibility controls, and visible keyboard focus. Mobile layouts preserve devotional image scale and avoid horizontal overflow.

Sri Panchami Spiritual is a spiritual-products storefront. The primary customer journey is discover products -> review product details -> add to cart or buy now -> manage the order. Temple guides and editorial content support that journey. This file is the canonical visual contract for everything customer-facing in `views/` and `assets/css/band.css`.

Commercial references such as Deiveegaa are used to audit product clarity, imagery, pricing, policies, testimonials, FAQs, and checkout visibility. Do not copy their visual identity or unverified product claims; use the comparison to expose missing commerce information and interaction wiring.

Keep the existing PHP templates, routes, forms, and hosted MySQL-backed behavior. Design changes must not scaffold a second frontend.

## Colors

- **Primary -- deep maroon (`#3a0003`):** headers, primary buttons, price, active nav state. `on-primary` is white; `primary-container` (`#651016`) is the maroon-active/pressed state.
- **Secondary -- warm brown (`#7a4a35`):** supporting accent, links-on-dark, secondary emphasis.
- **Tertiary -- muted gold (`#d1b368`):** secondary buttons, active underline, eyebrow labels, dividers. `on-tertiary` is maroon text for contrast.
- **Enquiry card labels** use `--color-text-muted` on the white card surface. The shared stylesheet owns the color pair so labels remain readable if a page stylesheet is unavailable. Small text needs at least 4.5:1 contrast. [WCAG 2.2 contrast minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html)
- **Surface (`#faf7f0`) / surface-container (`#f6ede4`):** canvas and warm alternate-section background. Never pure white as a page background; white (`on-primary`) is reserved for cards and inputs so they read as raised above the canvas.
- **Outline / outline-variant:** hairline borders only -- no heavy strokes.
- **Ink / ink-muted / ink-soft:** body text hierarchy from primary copy down to placeholders.
- Success, warning, error, info are semantic exceptions -- they may break the earthy palette when, and only when, they communicate order/payment/form state.

## Typography

- Body: Inter (300-700) with system sans-serif fallbacks.
- Display headings: Inter, weight 600-700, `22px`-`28px`.
- `accent-italic` (Playfair Display, 600 italic): decorative highlight only -- eyebrow labels and value-card titles. Never body text.
- Body copy: `14px`-`16px`, weight 400, line-height `1.45`-`1.6`.
- Labels/metadata: `12px`-`14px`, weight 500-600.
- Letter-spacing is `0` everywhere except short operational labels (table headers, filter labels, badges), where uppercase + slight tracking is acceptable. Buttons and headings are never uppercase.

## Layout

- Spacing scale: `2, 4, 8, 16, 24, 32, 48, 64px`.
- Container: centered, max `1300px` (`1440px` for wide layouts).
- `.section`: `64px` vertical padding by default. `.section--alt` uses `surface-container`; `.section--warm` uses the warmer `#f6ede4` for value sections.
- Responsive breakpoints: mobile below `744px` (one column, compact header, bottom nav), tablet `744-1128px` (reduced grid columns, same card geometry), desktop above `1128px` (centered container, `64px` section spacing).
- Text, buttons, images, and fixed controls must not overlap or reflow awkwardly as content length changes.
- Do not scale typography with viewport width. Use explicit breakpoint sizes so headings remain predictable and do not dominate short mobile screens.
- The first viewport must show the product's primary action and a hint of the next section. Home and shop lead to products; account pages lead to the user's current task.
- Page sections are unframed full-width bands. Cards are reserved for repeated entities, forms, summaries, and genuinely bounded tools. Never place a card inside another decorative card.
- Desktop operational screens use compact density and stable columns. Mobile screens use one clear column with 16px page gutters and no horizontal scrolling.

## Elevation & Depth

Use elevation to show which surfaces belong together and which actions deserve
attention. Combine the existing warm canvas and borders with a visible, soft
shadow when it helps readers distinguish foreground controls.

| Token | Typical use |
|---|---|
| `--shadow-sm` | Quiet panels, toolbars, and resting controls |
| `--shadow-md` | Cart items and other distinct working cards |
| `--shadow-lg` | Order summaries and floating checkout actions |
| `--shadow-xl` | Larger overlays such as drawers and dialogs |

Treat these as starting points. A component can use a stronger elevation or a
tuned shadow if it clarifies hierarchy in the rendered page. Preserve readable
contrast and focus rings, and check that the effect has room to render.

## Shapes

The radius scale expresses a calm, rounded-but-not-playful geometry:

| Token | Value | Use |
|---|---|---|
| `rounded.xs` | 4px | Chips, tags, small badges |
| `rounded.sm` | 8px | Buttons, inputs, standard controls (48px tall) |
| `rounded.md` | 8px | Repeated photo cards, product cards |
| `rounded.lg` | 8px | Feature cards, panels |
| `rounded.xl` | 8px | Hero panels and large media when a radius is needed |
| `rounded.pill` | 999px | Search bars, filter pills, status badges |

Shape should stay consistent within a component family -- don't mix `sm` and `lg` radii on sibling elements of the same card.

## Components

- **Header:** warm-neutral (`rgba(250,247,240,0.98)`), ~`80px` tall, non-sticky, hairline bottom border (`rgba(209,179,104,0.45)`), compact logo, centered primary nav with a gold active underline, and Search beside Cart on the right. The homepage keeps the logo and primary navigation pinned regardless of animation preference, including reduced-motion and no-JavaScript modes. Mobile keeps the visible brand on the left, then Search, Cart and Menu on the right. The header occupies the measured editorial-stack offset.
- **Navigation:** the linked brand mark and name are the sole home control. Start with a visible-on-focus skip link to the main content. Escape closes the mobile menu and returns focus to its toggle. Keep the native fragment menu available without JavaScript.
- **Appearance → Header menu** controls the order and visibility of the six public page slots. The brand, account actions and cart retain their own positions. Without JavaScript the mobile Menu and Close menu links reveal and dismiss the same primary navigation.
- **Mobile navigation:** scope the collapsible menu styles to the primary header. Account links, blog categories, report controls, and other in-page navigation remain visible and keyboard accessible.
- **Mobile commerce tray:** On the home, shop, category and product pages, show a fixed maroon tray when the cart contains items. Include the item count and a direct View cart action. The header cart provides access on other pages. Use an 8px radius and stable 56px minimum height; update it without page reload.
- **Floating support stack:** on desktop, align the support circle directly below the cart tray at the same right edge. On mobile, keep the cart tray above the bottom navigation and keep support clear of both controls.
- **Contact:** put direct support links and the message form in the first screen where space allows. Show required and optional fields, preserve entered values on validation errors, and move focus to inline feedback. The page provides support through its own controls.
- **Campaign enquiry:** place the form after the authored article. Name and email share one row when space allows. Stack fields in document order at 375px. Mark required and optional fields in visible labels. Focus an anchored status after a native submission and link the error summary to invalid fields. Show the same fields disabled in the owner preview.
- **Editorial media:** every blog post uses one intentional 16:9 image for both its listing thumbnail and article hero. UI guides use a legible screenshot of the exact page, cropped around the relevant interface rather than a decorative stock image, and link the represented page below the article.
- **Buttons (`button-primary` / `button-secondary`):** `48px` minimum height, `8px` radius, no uppercase, no letter-spacing. Primary is solid maroon with a gold hover overlay. A soft hover shadow or small lift can clarify the action. Secondary is gold-on-maroon-text. Hover states never shift layout.
- **Forms:** white fields (`on-primary`), `8px` radius, `48px` height, clear labels, a single-value focus ring (`--shadow-focus`) -- no glow.
- **Search/filter:** one rounded (`pill`) search control, or a quiet grouped filter row. No nested cards for filters.
- **Site-wide search:** the header magnifier opens a top-centered maroon pill with a gold border, reflecting the cart tray. Focus opens in the search field. Search submits to the existing results page. Close, Escape and the backdrop dismiss the popup and return focus to the magnifier. One Escape closes even with a filled field and preserves the query. Keep 44px controls and 12px side clearance at 375px. The magnifier links directly to the search page when JavaScript is unavailable.
- **Product cards (`product-card`):** linked image/title, short description, price, Buy Now and one stateful cart control. Initially show Add to Cart; adding one unit replaces it in place with `− quantity +`. The number is the actual cart quantity. Each press updates the cart immediately; decrementing the final unit restores Add to Cart. Keep 44px targets, native form fallback, visible focus and disabled controls during requests. Do not show a separate Quantity row or redundant in-cart link.
- **Homepage collections:** the first tile is a flat, text-only link occupying one product column. Show the collection name and View collection with an arrow. Member products use the same Buy Now and cart quantity controls as best sellers. Both cards update together when the same product appears twice. Keep Shop all products visible above the first product.
- **Cart layout:** lead with the item count, a jump to the order summary, and a checkout link. Use the full mobile content rail. Give each item a square image, readable title, and one row for quantity and line total. Give the summary more elevation than its items. On mobile, keep a total and checkout shortcut within reach while both inline checkout links are outside the usable viewport.
- **Cart quantity:** provide `−` and `+` controls beside every product, update line totals and the cart count, and remove the line when decreased to zero. Repeated additions of the same product merge into one line. Distinguish selecting a quantity to add from editing the quantity already in the cart.
- **Commerce responsiveness:** use `minmax(0, 1fr)` for purchase-button columns, wrap quantity labels when space is tight, and keep keyboard focus visible. Verify home, shop, product and cart at 375px and desktop in the built-in Browser. Do not shrink purchase controls to 30px to force them onto one row.
- **Product action alignment:** related-product prices and purchase controls sit at the bottom of equal-height cards. Long names remain readable without shifting buttons between columns. The homepage rail uses two-line visual previews with the complete linked product name available in its accessible text and title; the product detail page retains the full name.
- **Hero:** product-first offer over actual devotional imagery. The primary action opens the shop. Public consultation booking is retired. Desktop text is left aligned. Mobile uses one column and a compact image on normal-height screens, with a two-column treatment on short screens. The opening is a complete editorial card.
- **Authentication:** login and registration are task pages, not marketing pages. Use a centered form surface, suppress the public footer, and keep the complete form visible on common mobile heights.
- **Section actions:** offer jump links where a page has distinct tasks or information sections. Keep the heading visible after the jump and mark the section in view. Keep the destinations in ordinary document order.
- **Form feedback:** show a short pending status for slow native submissions. Use the submitted form and button as authored. Let existing cart and checkout interactions own their async feedback.
- **Product gallery:** show previous and next buttons on touch and pointer screens with the current image number. Limit Arrow Left and Arrow Right to the gallery and keep the first photo available without scripts.
- **Temple discovery:** homepage square previews crop to fill, and the detail page preserves the full photo. Place the photo to the left of the title and visit details. Homepage previews move one card at a time through native scrolling. Keep maps on temple detail pages and load the embedded map when the visitor opens it.
- **Product details:** keep the short introduction, feature bullets, detailed description, and labelled specification rows in separate readable sections. Admin-created products use the same fields and layout as existing products; never paste table markup into a plain-text description. Omit empty sections instead of fabricating claims.
- **Account:** use a persistent internal menu and one unframed content region. Keep orders, addresses and installation task-focused. In Profile, align a fixed-size decorative avatar beside the heading and email. Use the same frame for initials and the loaded photo so the layout stays steady.
- **Admin:** use a compact owner rail, clear tables and persistent in-page feedback after a form action. Sidebar groups are native disclosures that keep the current group open. At 375px the enhanced sidebar opens as a keyboard-contained drawer with an explicit close action. Without JavaScript, Menu and Close navigation open and close the same rail in the page flow. The skip link goes to the main workspace.
- **Email Outbox:** place queue health above the native search and delivery filters. Label the filtered record range beside the message table. Keep filter controls and pagination reachable on mobile.
- **Admin navigation and directories:** keep sidebar and top-bar action labels visible beside decorative line icons and highlight the current destination. Give account segments clear counts and distinguish them from the filtered result total. Use aligned columns on desktop and stacked identities and history on phones. Show the latest dated order status beside its timestamp and keep the mobile search field wide enough to read the query. Keep search visible and group secondary filters in a native disclosure. Show applied values below the toolbar as individually removable controls, with a single reset alongside them. Use decorative action icons with visible labels. For a reference lookup, mark the matching history row with a text label and open the history automatically when exactly one account matches.
- **Admin data tables:** retain server search on paginated pages. A local filter identifies the table and shows the number of matching rows. Wide tables scroll inside their region and can receive keyboard focus. On phones, ticket identity, status, context and the Manage ticket disclosure fit in the same card without side scrolling. Cancel returns focus to Manage ticket.
- **Admin bulk deletion:** select individual records in the list or grid, then show their names and a separate permanent deletion action. Keep Cancel visible and report the result for each item. Keep the selection and both review actions usable at 375 px with native form controls.
- **Admin analytics:** start with one operational question and make its evidence dominant. Revenue reports lead with net revenue after verified refunds, use directly labelled charts with a table fallback, and keep supporting figures in a compact evidence strip. Avoid equal-weight KPI grids and decorative charts.
- **Structured agent answers:** make the server-owned answer surface the primary response in Support and Enterprise. Use readable answer sections, followed by the relevant published references and approved navigation. Products have collection grid, compact list, selected detail and explicit comparison variants; price and availability remain distinct typed facts. Details may reveal up to two additional published photos with a native keyboard-operable disclosure, four real highlights and six specifications. Comparisons use two or three exact published selections and repeat matching field labels; missing specifications say Not listed. Articles use a reading list, contacts one grouped panel and service enquiries their own tiles. Choose layout from the actual chat/iframe container width, with one column at 375px, natural wrapping and 44px actions. Show the complete original answer when presentation is unsupported; do not duplicate it below a successful surface.
- **Enterprise report cards:** lead with a recorded primary metric and supporting figures, then bounded report rows with source, effective period or snapshot, observation time, calculation basis and an explicit shown/total count. Product/coupon rows, stock-status records and aggregate customer-frequency bands come only from completed sanitized tool evidence; do not parse prose into metrics or imply stock quantities, profits or forecasts. Keep the full answer and owner report links. Restore exact saved values without new retrieval. Failed/unavailable requests and editable draft proposals retain their existing native states; a draft is not a saved change.
- **Enterprise Agent:** center the conversation workspace with a left-aligned heading and a single composer above the prompt examples. Stack the examples on narrow phones, keep attachment and draft guidance visible, and use a 16px message field on touch screens. Reopen the active chat in the current session without moving focus to the composer. Show a visible recovery action while a saved chat is unavailable or has an unfinished request. Keep the current draft and conversation visible through media uploads. Support keyboard selection of @ references at the cursor. Use expandable run evidence to show scoped tool outcomes and provider timing. Keep draft writes behind owner review.
- **Support chat:** keep New chat and Close support in the conversation header with 44px targets. Opening focuses the active pane heading, and Close or Escape restores the launcher. Keep the conversation log keyboard-scrollable and leave the composer reachable at short viewport heights. Keep the conversation controls separate from the cart and enquiry panes. Repeat the same-session A2UI and follow-up checks at desktop and 375px, then switch the session and verify a fresh transcript.
- **Admin analytics:** keep period controls and shared sales metrics on Dashboard. Use a revenue trend, ranked product bars and a paid-order status ring with visible values. Group report navigation under Analytics, outbound and inbound mail under Email, and catalog and coupon controls under Commerce.
- **GST quantities:** keep the unit and amount per sale item together in the product form. In reports, label the recorded unit beside its total quantity and give unrecorded units a visible review state. Keep wide reports in a focusable scroll region on phones.
- **Agent navigation:** Enterprise Agent groups Chat, Agent Monitoring and AI Support Tickets. Monitoring shows privacy-safe request metadata for the two existing PHP agents. In expanded attempts, put cumulative transfer milestones on a separate wrapping line with readable labels and tabular figures.
- **Admin platform guidance:** describe the product-led public journey consistently. Astrologer profile management, navigation and portrait media are retired. Historical session records remain owner-accessible, but must not reintroduce profile editing or portrait pickers. Integration help must not advertise retired public booking, text sessions, or direct calls.
- **Admin form sizing:** use zero-minimum grid tracks and shrinkable labels/controls. Long integration endpoints or credentials must stay within the field, never widen the page. Check 375px with populated fields without exposing their values in screenshots or reports.
- **Public site profile:** display the approved business name consistently in navigation and metadata. Use published contact methods and hours when present, with a full-width form when no direct information is published. Keep the profile editor separate from credentials and delivery settings, and show save feedback with or without JavaScript.
- **Admin brand uploads:** show the active logo and favicon beside their labelled file inputs on desktop and below them on phones. State the supported formats and limits next to each input. Save feedback should reflect the active settings and keep the previous previews visible after an error.
- **Admin product preview:** render saved images and structured content through the existing product-detail layout. Clearly label the view as an admin-only preview, disable purchase controls, and provide Back to products. Hidden products remain absent from public listings and ordinary URLs; previews are uncached and not indexed.
- **AI request status (both chat surfaces):** immediately show a keyboard-operable Thinking… row and elapsed seconds while waiting. Never show raw model reasoning, fabricated stages, or processing-language explanations. After the animation ends, retain only the elapsed duration; the adjacent answer or error message communicates outcome. Use the shared warm-white/maroon component, 44px summary targets, tabular time digits, and an indeterminate progress control. Respect reduced motion, prevent duplicate submits, and restore the composer on every completion path.
- **Homepage FAQs:** show concise, accurate commerce FAQs in the page body and keep any matching structured data in sync. Do not claim shipping, certification, inventory, or return terms unless the current policy and checkout behavior prove them.
- **Waiting and loading:** only show a loading state while a real request is pending. Keep the user's submitted message visible; do not replace real catalog content with decorative skeletons. Errors and fallbacks must remain distinguishable from successful model responses in monitoring.
- **Value-proposition cards:** 4-column desktop / 2 tablet / 1 mobile, white card, warm icon circle, `accent-italic` heading, muted body, `4px` hover lift into `shadow-lg`.
- **Footer:** use the maroon surface and cream text. Group links under Explore, Journal, and Customer support. Show Journal when Blog is enabled. Keep Terms, Privacy, copyright, and credit in the bottom row. Use two link columns at phone widths, with room for email addresses and 44px touch targets.
- **Authored-page preview:** use the published reading surface inside the named preview section. Show an optional, uncropped header image below the title, with page-specific alternative text. Use the existing Media Library for selection and upload. Keep the image choice, clear option, and native Preview and Save actions reachable at desktop and 375px.
- **Documents and guides:** Markdown-backed pages use the same warm canvas and a constrained reading column. The page header is centered and quiet; the content surface is white with a single soft border, 14px-20px radius, and `shadow-sm`. Use maroon `h2` headings, muted body text at 1.6-1.7 line-height, generous section spacing, and gold only for eyebrows, links, and small metadata. Documentation indexes use a two-column desktop grid and one-column mobile layout with clear titles, summaries, and a visible `Read guide` action. Do not render legal or customer documentation as long unstructured text or nested cards.

## Do's and Don'ts

- **Do** keep the canvas warm (`surface` / `surface-container`) and reserve pure white for cards, inputs, and modals so they read as raised.
- **Do** use elevation changes to show hover or selection. Keep the layout stable and check pointer and keyboard states.
- **Do** use `accent-italic` (Playfair Display) sparingly, as a decorative highlight.
- **Do** keep shape consistent per component family (see Shapes).
- **Don't** use uppercase or letter-spacing on buttons or headings -- reserve it for short operational labels only.
- **Do** reserve the strongest shadows for surfaces that genuinely sit above nearby content.
- **Don't** introduce a second frontend, second routing scheme, or a component library that bypasses the existing PHP templates.
- **Don't** copy reference-product navigation labels or routes that don't exist in this app -- keep the real routes.
- **Don't** let hover/focus states shift layout or reflow siblings.
- **Don't** hide essential content behind entrance animations. Motion is optional enhancement and must never control legibility.
- **Don't** use decorative gradients, oversized pills, or large-radius containers to manufacture hierarchy. Use spacing, typography, borders, and real media.
- **Do** keep policy, legal, customer, and internal guide content in Markdown/YAML sources and render it through the shared document surface so copy changes do not require template rewrites.

## Verification

- Check `/`, `/shop`, one product, `/login`, and authenticated account pages at 1440x1000 and 390x844 in a real browser.
- Confirm image crops, active navigation, focus states, card alignment, no hidden reveal content, no horizontal overflow, and that the next section is hinted in the first mobile viewport.
- Use the fixed development customer created by `bapXphp dev:user`; its password must come from `BAPX_TEST_USER_PASSWORD` and must never be committed.
- Run the repo's PHP tests, project-map validation, and local smoke test before commit or push.

## Implementation Notes

- Tokens above are also expressed as CSS custom properties in `assets/css/band.css` (`:root`). The semantic names here (`primary`, `on-primary`, `primary-container`, etc.) exist there as aliases (`--color-primary`, `--color-on-primary`, `--color-primary-container`, ...) layered directly on top of the original hue-named variables (`--color-maroon`, `--color-gold`, ...) -- both are safe to use; new code should prefer the semantic names.
- Elevation tokens: `--shadow-sm/md/lg/xl` plus `--shadow-focus` for the single focus-ring definition.
- Motion: `--transition-spring` (a restrained expressive-motion curve, `cubic-bezier(0.34, 1.4, 0.64, 1)`) is used for hover lifts on buttons and cards; `--transition-base` still governs color/shadow fades.
- This file can be linted against the open spec with `npx @google/design.md lint Design.md` if you want machine validation of token references and section order.

## Shared motion and scroll

Motion is progressive and causal. Public section entrances may follow native scroll position. Use a perceptible offset, readable content and clear still frames. Public and operational surfaces share
`assets/site-motion.js` and `site-motion.css`, with shorter operational timing.
The homepage uses a complete opaque editorial-card stack, inspired by Nebo.
Each section fills the available stable viewport or grows to fit its content.
Tall cards scroll to reveal their final controls before holding; incoming
cards cover the previous card in normal paint order. Product and collection
rails snap complete cards horizontally, with one per mobile viewport and
two or three complete cards on wider screens. Avoid nested per-item scroll
animations inside the editorial stack; the card handoff itself is the motion.
Keep shopping controls, text-only collection links and FAQ disclosures steady.
Never animate fabricated KPI values, hide text pending JavaScript, hijack native
scrolling or move controls away from keyboard focus. Respect reduced motion
and data-saving settings, stop hidden/offscreen slide rotation, and preserve
readable still frames. All primary controls remain functional at 375px.
See `docs/site-motion.md` for source attribution and browser acceptance.

## JavaScript interaction and recovery

- Build on the existing PHP templates and form actions. Use small browser scripts
  for responsive controls, local recovery, and feedback tied to actual state.
- Keep editing modes visibly distinct and expose the selected mode through
  `aria-pressed`. Give editors accessible names, keep keyboard focus visible,
  and use 44px minimum targets for editor controls.
- In the blog editor, report browser draft recovery beside the fields. Restoring
  a draft requires an explicit action. A copy based on an older revision can
  be downloaded for comparison. The Save button still submits to PHP, which
  verifies the current revision and controls publication.
- Announce storage and request failures near their controls. Preserve typed
  content if a request fails. Verify keyboard focus, computed visibility,
  recovery after reload, and absence of page overflow at 375px and desktop.

The mobile homepage stacks the devotional portrait and primary action on normal-height screens, retaining a compact side-by-side composition on short screens. Eleven compact dots sit between previous and next arrows in one row; selecting any portrait stops the two-second automatic rotation for the visit. Full-width scroll sections have straight outer edges, while internal cards retain their existing radius. A stable frame crossfades opacity without changing the layout. Group the temple name and address beside the photo, give hours and the detail action the full card width, and group rail navigation in a single row above the cards. The order guide, temples and values hand off as complete editorial cards; focus and reduced motion restore ordinary flow. On the homepage the header stays visible at the measured sticky offset. FAQ is the last sticky predecessor; the same real footer becomes the final full-height opaque slide when enhanced, with readable cream text on maroon. Long footer content grows naturally; no-JS retains the ordinary footer. Allow bottom clearance inside each opaque card for the real floating cart, return and support controls; never reduce their touch targets. Mobile value rows use a compact icon-and-text layout.

## Chat checkout

Support product cards offer Add to cart, Buy now and Checkout as explicit controls.
Keep the cart and checkout form inside the Support panel, with a clear Back to chat
control and visible request status. The form scrolls within the available viewport;
fields and actions remain usable at 375px without widening the page. Keep 44px
targets, labelled inputs and keyboard focus return to the initiating product control.

The same fixed shopping and checkout components serve native Support and MCP Apps.
PHP owns product eligibility, prices, totals, delivery validation and payment status.
Keep the public A2UI answer renderer read-only; it cannot interpret arbitrary form
HTML, model-defined actions or owner tools. Enterprise report surfaces remain separate.
Collect delivery details in the trusted form and show the returned quote before an
explicit payment-preparation action. A separate button opens the validated merchant
payment page; chat never embeds third-party payment inputs or provider scripts.

Mount native checkout outside the saved Support conversation. Native session recovery
may contain only the bounded anonymous cart/status capabilities, revision and expiry,
never address, contact, coupon, card or quote data. Refresh server state before showing
recovered facts, clear expired handles and explain storage denial beside the form.
External MCP Apps do not adopt native browser storage. Clear typed form data on teardown.
Retain valid recovery access through temporary refresh failures and offer a retry without
showing stale cart facts. When Shop is disabled, keep existing payment-status access
visible while removing product actions, cart changes and checkout submission.

Completed browser tool results update the same native component before the tool resolves.
Wait for an active human action, validate the result family and handle, and discard a
canceled queued update without clearing the human form or its recovery access.

Payment failures must distinguish an unconfirmed payment from a verified payment whose
order is not yet saved. A verification retry checks the same payment; it must not open
a second payment attempt or claim that no money was debited without confirmation.

### Editorial About page

Use the deployment-owned story descriptor and the reusable About renderer.
A contrasting editorial hero, a two-column image-and-story section, module-aware
text pathways and real contact details replace repetitive icon cards.
Use shared semantic color roles and typography, natural document scrolling,
one primary heading, real image dimensions, and a single-column phone layout.
All content and navigation work without JavaScript. Never borrow competitor
claims, images or unverifiable milestones.
