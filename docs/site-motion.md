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
  at zero and no final style is retained by the animation. On the homepage,
  only the product photo settles into place as it enters the viewport. The
  card, price, purchase controls and collection link stay still. Public section
  headings settle once when at least 12% of the target enters view. Headings
  move 20px over 460ms. Cart item details and the order breakdown use a 28px,
  520ms entrance. Quantity and checkout controls stay in place. An active
  entrance cancels when keyboard focus reaches its content. The homepage temple rail uses
  native snap and visitor-operated arrows without automatic rotation.
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
- The existing homepage slide selector remains operable. An explicit pause/play
  control is added. Automatic changes pause when offscreen, hidden, hovered,
  focused, user-paused, data-saving or reduced-motion. Arrow/Home/End keys select
  real slides; imagery and product copy remain unchanged.
- Reduced motion and data-saving skip presentation effects. A live preference
  change cancels active animations; no-JS and missing observer/animation APIs
  leave content visible. Print suppresses decorative controls.

The old `motion-ready` opacity gate was removed from the public inline script;
CSS also neutralizes a stale gate so a cached class or script failure cannot
leave readable content invisible. Motion uses transform/opacity, not layout
width/height changes. Existing brand tokens and real route/form behavior stay
canonical in `Design.md`.

## Source-derived decisions and scope

The [Nebo editorial stack](https://github.com/getwinharris/nebowellness_php_cms/blob/f5fb5f177c412b161566d02383e26a172b2d964b/assets/editorial-stack.js) and [design guidance](https://github.com/getwinharris/nebowellness_php_cms/blob/f5fb5f177c412b161566d02383e26a172b2d964b/Design.md) informed the once-only section entrance and single-step rail. The public layouts use the existing native document flow so reading, purchase controls and keyboard focus remain reachable.

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

## Validation contract

Run `node --test tests/site-motion.test.cjs` and the repository's PHP/CI checks.
Use the installed Playwright CLI at 1280 px and exactly 375 px to inspect real
scroll positions, return-to-top, panel state, current cards/forms, admin
sidebars, reduced-motion transitions, no-JS and absent observer/WAAPI cases.
Check computed visibility, page errors and horizontal overflow. Capture actual
screens, not only assertion logs; inspect beginning/middle/end frames.

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
