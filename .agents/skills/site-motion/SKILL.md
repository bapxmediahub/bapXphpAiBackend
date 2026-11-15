---
name: site-motion
description: Use for progressive animations, native scrolling, panel and message motion, and reduced-motion/browser checks in the existing PHP public or admin layouts.
---
# Native PHP site motion (developer harness only)

Read `Design.md`, `docs/site-motion.md` and existing `assets/site-motion.js`
before changing animation behavior. This skill guides coding tools; the live
Support and Enterprise Agent never load `.agents/` into their prompts.

Use the pinned Stageshow and Scrollcraft sources in `docs/site-motion.md` for
causal motion and meaningful verification. Stageshow is an illustration kit,
not a replacement for functional commerce UI. Do not add React, invented data,
fake cursor actions, scrolljacking, long pinned checkout scenes or paid video.

Keep content fully visible without JavaScript, observer APIs or animation
support. Avoid opacity-zero loading gates. Animate bounded transform/opacity,
stop offscreen/hidden activity, respect reduced motion dynamically, and keep
keyboard/focus/anchor behavior native. Shared public/admin layouts own the
assets; do not copy a different engine into every page.

Run `node --test tests/site-motion.test.cjs`, PHP/repository CI, then real
Playwright desktop/375px and reduced/no-JS checks. Inspect intermediate frames,
computed visibility, overflow, actual controls, private-directory HTTP denial
and console errors. Do not publish credentials or real customer screenshots.
