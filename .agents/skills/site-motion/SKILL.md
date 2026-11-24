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
support. Avoid opacity-zero loading gates. Prefer native view timelines for section
entrances when supported. Keep the once-only entrance as a browser fallback
and leave task controls steady. Animate bounded transform/opacity,
stop offscreen/hidden activity, respect reduced motion dynamically, and keep
keyboard/focus/anchor behavior native. Shared public/admin layouts own the
assets; do not copy a different engine into every page.

Use the pinned Nebo reference for full-card editorial handoff. Each opaque
section fills the available stable viewport or grows naturally. Tall cards
scroll until their final controls are exposed. Release the stack for keyboard
focus and fragment navigation. Avoid per-item entrance animations inside the
homepage stack. Native horizontal scroll snap advances whole cards; preserve
purchase forms, short viewport layout and reduced/no-JS behavior. Verify the homepage header physically occupies the measured sticky offset in normal, reduced-motion and no-JS modes. Verify every consecutive section, including FAQ into the real, single footer as the final slide. Use tests/homepage-release-browser.cjs against the actual PHP layout and exact deployed SHA, not only a simplified fixture. Preserve readable dark-footer contrast and all links. Also reserve space inside cards for floating actions rather than shrinking the opaque surface. Rehearse long product names and active-cart states; inspect actual purchase-button hit targets at the resting frame.

Run `node --test tests/site-motion.test.cjs`, PHP/repository CI, then real
Playwright desktop/375px and reduced/no-JS checks. Inspect intermediate frames at distinct scroll positions and after scrolling back,
computed visibility, overflow, actual controls, private-directory HTTP denial
and console errors. Do not publish credentials or real customer screenshots.

For a sequence of sticky sections, measure each outgoing section and verify its top stays stationary while the next surface advances. Repeat in reverse and with a section taller than a short phone viewport.

For the hero portrait carousel, keep the first image visible without JS. Test eleven labeled dots, pause/play, 2-second autoplay, loaded-image crossfade, wrapping, focus/hover/hidden/covered and reduced-motion behavior. Use the W3C carousel pattern for controls.
