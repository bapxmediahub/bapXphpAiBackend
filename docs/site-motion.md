---
type: doc
title: Shared Motion and Native Scroll Contract
description: Progressive motion across PHP layouts with reduced-motion, no-JS, static-content and real-control guarantees.
category: docs
---

# Shared motion for real screens

`assets/site-motion.js` and `assets/css/site-motion.css` are loaded with
content-mtime cache versions by both existing public and admin PHP layouts.
They enhance real application state; they do not render another frontend,
change data, fake a chart value or script a purchase.

## Behavior

- Real product/blog cards, selected headings, auth cards and operational KPI
  surfaces enter once when visible. Public motion is 300 ms / 10 px; admin
  motion is 180 ms / 4 px. Stagger is bounded at 120 ms. Opacity never starts
  at zero and no final style is retained by the animation. Public headings and
  designated panels outside the homepage stack use native view timelines where
  available, with once-only entrance fallback. The homepage's complete cards
  are the transition: no nested photo, heading or panel scroll animations.
  Product rails use native horizontal scroll snap and visitor-operated arrows.
  Purchase controls remain steady and reachable at settled snap positions. The homepage uses compact, equal-height merchandise cards with full linked names, two-line title/description previews and unchanged 44px purchase targets.
- Existing Support/History/navigation controls own their state. A panel or
  new chat message can receive a short entry transition after that real state
  changes. The module does not submit forms or fabricate completion feedback.
- Native wheel, touch, keyboard, anchors and scrollbars remain native. A passive
  scroll listener schedules one rendering update, not a permanent animation
  loop. Header height supplies anchor offsets; nested chat/table/sidebars retain
  their own scrolling. A labeled return-to-top button appears only after real
  page progress and returns focus to the main document. On mobile, it sits
  above bottom navigation and the cart tray. Native scroll targets leave room
  for those floating controls.
- The first devotional portrait renders in PHP. Eleven native dot buttons and
  previous/next controls select the supplied images. Autoplay advances after
  two seconds with a 420 ms opacity crossfade, only when motion is allowed and
  the artwork is visible. Pause/play, focus, hover, hidden tabs and reduced
  motion control rotation; image decode must succeed before selection.
- The homepage sections share an opaque native sticky stack. Each fills the
  available stable viewport, or grows to expose all content. A single
  ResizeObserver measures card/header heights only when geometry changes;
  tall cards reach their final row before holding. The homepage header remains pinned independent of animation preferences and JS. The existing single footer moves into the enhanced stack as the final opaque slide, with an explicit contentinfo landmark; FAQ is the last sticky predecessor. Without enhancement the same footer stays in normal document flow. The measured top offset is occupied rather than left blank. Opaque cards retain internal clearance for floating controls; an active cart gets additional mobile clearance. Focus, fragment navigation,
  reduced motion and missing enhancement restore ordinary flow.
- Reduced motion and data-saving skip presentation effects. A live preference
  change cancels active animations; no-JS and missing observer/animation APIs
  leave content visible. Print suppresses decorative controls.

The old `motion-ready` opacity gate was removed from the public inline script;
CSS also neutralizes a stale gate so a cached class or script failure cannot
leave readable content invisible. Motion uses transform/opacity, not layout
width/height changes. Existing brand tokens and real route/form behavior stay
canonical in `Design.md`.

## Source-derived decisions and scope

The homepage adapts Nebo's complete opaque editorial stack while keeping the
existing spiritual product composition and actual purchase forms. The trust
strip stays inside the opening card. Horizontal product rails snap whole cards,
with one on mobile and up to three on desktop. No active-card size animation,
scroll interception or external runtime is used. The pattern follows
[Chrome scrollytelling guidance](https://developer.chrome.com/docs/modern-web-guidance/guides/ui-behaviors/scrollytelling)
and [MDN CSS scroll snap](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Scroll_snap).
[Nebo editorial stack](https://github.com/getwinharris/nebowellness_php_cms/blob/69ff0a26c78908d21a2580a4509b56c11d6ec841/assets/editorial-stack.js) · [Nebo styles](https://github.com/getwinharris/nebowellness_php_cms/blob/69ff0a26c78908d21a2580a4509b56c11d6ec841/assets/css/band.css#L4324) · [Nebo design guidance](https://github.com/getwinharris/nebowellness_php_cms/blob/69ff0a26c78908d21a2580a4509b56c11d6ec841/Design.md)

The requested sources were inspected, not blindly installed:

- [Stageshow README](https://github.com/benjitaylor/skills/blob/299140fafd08df55a9c234e610c3f64af3f45531/stageshow/README.md)
  and [skill](https://github.com/benjitaylor/skills/blob/299140fafd08df55a9c234e610c3f64af3f45531/stageshow/SKILL.md):
  it is an **authored illustration/React kit**, not a way to operate live UI.
  This PHP implementation adopts causal motion, readable resting frames and
  responsive/reduced-motion rehearsal, not its fake cursors or React components.
- [Scrollcraft README](https://github.com/nateherkai/scroll-craft/blob/75d81f74e83692add18cd7a8a8e078b8a887a579/README.md)
  and [verification reference](https://github.com/nateherkai/scroll-craft/blob/75d81f74e83692add18cd7a8a8e078b8a887a579/plugins/nateherk-design/skills/scroll-craft/references/verify.md):
  retain semantic HTML, brand constraints, useful controls and scroll-state
  verification. The operational shop/checkout/admin surfaces are not cinematic
  pinned stories; the heavy video/scroll engine is not installed or modified.

These are deliberate adaptations to the existing product, not a claim that
all examples or verification tools from those projects are shipped. No paid
asset generator, new browser runtime, external analytics or new frontend
framework is added to the application.

The [animation-performance skill](https://github.com/emilkowalski/skills/blob/main/skills/animation-performance/SKILL.md) (reviewed blob c7dd441dde8d597c877e851ed9f9601249351591) distinguishes layout work, painting and compositor animation. Do not add blanket will-change or transition:all. Compare identical scroll paths under the same browser conditions; active animation counts and screenshots are not frame-rate measurements.

The [W3C carousel tutorial](https://www.w3.org/WAI/tutorials/carousels/) guides keyboard-operable navigation and understandable position announcements. A single visible slide announces its position as “1 of 4”, not “1–1 of 4”.

## Validation contract

Run `node --test tests/site-motion.test.cjs` and the repository's PHP/CI checks.
Use the installed Playwright CLI at 1280 px and exactly 375 px to inspect real
scroll positions, return-to-top, panel state, current cards/forms, admin
sidebars, reduced-motion transitions, no-JS and absent observer/WAAPI cases.
Check computed visibility, page errors and horizontal overflow. Capture actual
screens, not only assertion logs; inspect beginning/middle/end frames.
For the homepage handoff, also check a short viewport, scroll past the hero
and return to it by keyboard. Verify that the first shop action stays reachable,
fragment links land on their sections and horizontal temple/product rails advance whole cards.
Run \`tests/home-full-card-browser.cjs\` at 375x812, 1280x900 and 375x580;
check native sticky handoff, reverse scrolling, keyboard flow, reduced/no-JS,
whole-card rail geometry and intact purchase forms. Verify the pinned header meets the incoming section, long product titles do not make ordinary cards unnecessarily tall, and real floating controls do not cover purchase buttons or trust labels at rest, including the active-cart state.

Private admin browser state, customer-page text and screenshots stay outside
Git. Browser emulation does not certify every physical phone's compositor or
iOS behavior; record that limitation rather than claiming universal smoothness.

## Scroll defect found during verification

The Media Library's implicit inner grid used a long filename's minimum width
to extend beyond its 375px page. The fix gives each media tile a `minmax(0,1fr)`
column and wrapping children. It does not mask the bug with body overflow hiding;
tables still scroll in their intended wrappers. Browser coverage includes an
explicit long, unbroken filename to preserve this behavior.

The mobile owner navigation now has a reachable 44px close control, a backdrop,
Escape dismissal, contained keyboard focus and focus return to its trigger.
Background scroll/focus is disabled only while that explicit modal drawer is
open, then restored; resizing to desktop removes modal state. This is not a
page scrolljacking mechanism. The drawer's own contents remain scrollable.

### Whole-page release verification

Use the actual PHP-rendered public layout as well as controlled product fixtures.
Run tests/homepage-release-browser.cjs locally and after the deployment SHA
gate with EXPECTED_SHA set to the full expected production revision. The
post-deployment workflow runs the same test at 1536×850, 1280×900, 375×812 and
375×580. Verify every consecutive handoff forward and reverse, pinned header
and brand, real footer landmark, no blank strip at the end, readable footer
contrast (at least 4.5:1), legal link hit testing, mobile menu, reduced motion and
no-JS fallback. This is read-only public acceptance, not a purchase or a physical
phone performance certification. Preserve prior product/cart and WebMCP gates.
