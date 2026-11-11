---
name: visual-workspaces
description: Use when redesigning Sri Panchami public storytelling, admin analytics, dashboards, charts, agent workspaces, or responsive operational layouts.
---
# Visual Workspaces

Follow root `AGENTS.md`, `Design.md`, and the matching frontend/admin skill first.

## Approval gate

For a substantial new visual direction, inspect the existing live desktop and exact
375px experience, then generate paired desktop and mobile concept images. Obtain owner
approval before implementing the visual system. Concept imagery is direction only; the
real logo, content, data, accessibility semantics and brand tokens come from the repo.

## Operational hierarchy

- Begin with the one decision the screen must support. Give its evidence the dominant
  area; supporting facts become a compact strip or table, not equal-weight cards.
- Use direct chart labels, an accessible text/table fallback, real zero/empty states,
  and no invented values. A chart must answer a question rather than decorate a page.
- Compose one hero surface with at most one supporting inspector or drawer. Simplify
  repeated framing before adding borders, cards or badges.
- Mobile is a sibling composition: drop side rails, keep actions whole, avoid horizontal
  tables, preserve 44px targets, and show the operational payoff in the first viewport.

## Motion and scroll

Adapt the hierarchy, framing and purposeful-motion principles from
[Stageshow](https://github.com/benjitaylor/skills/tree/main/stageshow) and
[Scroll Craft](https://github.com/nateherkai/scroll-craft), both MIT-licensed references.
Do not copy their branding, templates or example content.

- Public editorial pages may use one caused, reduced-motion-safe signature transition
  when it clarifies the product or devotional story.
- Admin and checkout never use scroll-jacking, pinned narrative sections, decorative
  parallax, auto-playing motion, or animation that delays an action.
- Every motion needs an initiating action and a meaningful settled state. With
  `prefers-reduced-motion`, the settled state remains complete and understandable.

## Agent workspaces

- Preserve exactly two product agent surfaces: customer support and Owner Agent.
- Owner Agent context is explicit, scoped and inspectable. Show sources and safe tool
  activity, but never prompts, secrets, provider bodies, identities outside an explicit
  lookup, or model reasoning.
- Writes remain drafts until a conventional authenticated, CSRF-protected, audited form
  is reviewed and confirmed by the owner.
- Provider observability shows model, attempt number, HTTP outcome, elapsed time and
  bounded fallback. It must preserve the Gemma-only router contract in `AGENTS.md`.

## Verification

Use the built-in Browser. Check desktop and exact 375px, keyboard navigation, visible
focus, empty/error/loading states, computed overflow, reduced motion, chart fallback,
and that minimized/floating layouts never cover the primary action.
