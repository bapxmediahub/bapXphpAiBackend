---
version: alpha
name: Sri Panchami Spiritual
description: Calm, credible interface system for a consultation-first spiritual service with supporting commerce and editorial content.
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
  card-astrologer:
    backgroundColor: "{colors.on-primary}"
    rounded: "{rounded.sm}"
  input:
    backgroundColor: "{colors.on-primary}"
    rounded: "{rounded.sm}"
    height: 48px
---

## Overview

Sri Panchami Spiritual is a consultation-first service. The primary customer journey is discover consultant -> review profile -> request an appointment -> manage the session. Spiritual products and editorial content support that service; they do not replace it in the first viewport. This file is the canonical visual contract for everything customer-facing in `views/` and `assets/css/band.css`.

Commercial references such as Deiveegaa are used to audit product clarity, imagery, pricing, policies, testimonials, FAQs, and checkout visibility. Do not copy their visual identity or unverified product claims; use the comparison to expose missing commerce information and interaction wiring.

Keep the existing PHP templates, routes, forms, and JSON-backed behavior. Design changes must not scaffold a second frontend.

## Colors

- **Primary -- deep maroon (`#3a0003`):** headers, primary buttons, price, active nav state. `on-primary` is white; `primary-container` (`#651016`) is the maroon-active/pressed state.
- **Secondary -- warm brown (`#7a4a35`):** supporting accent, links-on-dark, secondary emphasis.
- **Tertiary -- muted gold (`#d1b368`):** secondary buttons, active underline, eyebrow labels, dividers. `on-tertiary` is maroon text for contrast.
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
- The first viewport must show the product's primary action and a hint of the next section. Home leads to consultation; shop leads to products; account pages lead to the user's current task.
- Page sections are unframed full-width bands. Cards are reserved for repeated entities, forms, summaries, and genuinely bounded tools. Never place a card inside another decorative card.
- Desktop operational screens use compact density and stable columns. Mobile screens use one clear column with 16px page gutters and no horizontal scrolling.

## Elevation & Depth

Depth is used sparingly and tonally-first, the way Material 3 treats elevation: a surface reads as "raised" primarily because it's a different, lighter tone than the canvas (white card on warm surface), with a soft shadow as the secondary cue -- not a spotlight effect.

Four shadow steps exist (`--shadow-sm/md/lg/xl` in `band.css`), each with exactly one job:

| Step | Use |
|---|---|
| `sm` | Resting state for cards and inputs |
| `md` | Raised buttons; hovered product cards |
| `lg` | Hovered astrologer cards and feature/value cards |
| `xl` | Modals, drawers, popovers only |

Rules:
- Never stack two shadow steps on one element.
- A hover state may move exactly one step up the scale (`sm` → `md`, or `sm` → `lg`), never two.
- No glow, no colored shadows, no blur-heavy "spotlight" effects.

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

- **Header:** warm-neutral (`rgba(250,247,240,0.98)`), ~`80px` tall, non-sticky, hairline bottom border (`rgba(209,179,104,0.45)`), compact logo, centered primary nav with a gold active underline, right-aligned account/cart actions.
- **Navigation:** the linked brand mark and name are the sole home control. Do not repeat a separate Home item in desktop or mobile navigation.
- **Mobile commerce tray:** after the cart becomes non-empty, show one fixed maroon tray above the bottom navigation with item count and a direct View cart action. Use an 8px radius and stable 56px minimum height; update it without page reload.
- **Editorial media:** every blog post uses one intentional 16:9 image for both its listing thumbnail and article hero. UI guides use a legible screenshot of the exact page, cropped around the relevant interface rather than a decorative stock image, and link the represented page below the article.
- **Buttons (`button-primary` / `button-secondary`):** `48px` minimum height, `8px` radius, no uppercase, no letter-spacing. Primary is solid maroon with a gold hover overlay; hover moves from `shadow-md` to `shadow-lg` and lifts 2px, no more. Secondary is gold-on-maroon-text. Hover states never shift layout.
- **Forms:** white fields (`on-primary`), `8px` radius, `48px` height, clear labels, a single-value focus ring (`--shadow-focus`) -- no glow.
- **Search/filter:** one rounded (`pill`) search control, or a quiet grouped filter row. No nested cards for filters.
- **Product cards (`card-product`):** white, `8px` radius, 1px quiet border, stable 1:1 media, concise title and price, then the `- 0 +` quantity control. Do not add a second cart button.
- **Consultant cards (`card-astrologer`):** white, `8px` radius, face-forward portrait, name, speciality, language/experience metadata, review summary when present, and one clear profile/booking action. Every card uses equal media and content tracks so rows align.
- **Hero:** consultation-first offer over actual devotional imagery. The primary action is `Book a consultation`; shopping is secondary. Desktop text is left aligned. Mobile uses one column, a compact image, and must reveal the next content band without requiring a full-screen scroll.
- **Authentication:** login and registration are task pages, not marketing pages. Use a centered form surface, suppress the public footer, and keep the complete form visible on common mobile heights.
- **Consultation discovery:** search and language controls form one quiet toolbar. Results render immediately without reveal animations or low-opacity loading states. Empty and filtered states explain the next action.
- **Account:** use a persistent internal menu and one unframed content region. Orders, sessions, addresses, and installation are tasks, not promotional cards.
- **Admin:** optimize for scanning and repeated action: compact sidebar, clear tables, consistent forms, explicit save state, and no marketing-style hero composition.
- **Value-proposition cards:** 4-column desktop / 2 tablet / 1 mobile, white card, warm icon circle, `accent-italic` heading, muted body, `4px` hover lift into `shadow-lg`.
- **Footer:** white background, soft border, warm-brown headings, muted body, bottom bar with copyright + credit.
- **Documents and guides:** Markdown-backed pages use the same warm canvas and a constrained reading column. The page header is centered and quiet; the content surface is white with a single soft border, 14px-20px radius, and `shadow-sm`. Use maroon `h2` headings, muted body text at 1.6-1.7 line-height, generous section spacing, and gold only for eyebrows, links, and small metadata. Documentation indexes use a two-column desktop grid and one-column mobile layout with clear titles, summaries, and a visible `Read guide` action. Do not render legal or customer documentation as long unstructured text or nested cards.

## Do's and Don'ts

- **Do** keep the canvas warm (`surface` / `surface-container`) and reserve pure white for cards, inputs, and modals so they read as raised.
- **Do** let a hover state move exactly one elevation step and/or lift by a few pixels -- nothing more theatrical.
- **Do** use `accent-italic` (Playfair Display) sparingly, as a decorative highlight.
- **Do** keep shape consistent per component family (see Shapes).
- **Don't** use uppercase or letter-spacing on buttons or headings -- reserve it for short operational labels only.
- **Don't** add glow, colored blur, gradients-as-decoration, or stacked shadow tiers.
- **Don't** introduce a second frontend, second routing scheme, or a component library that bypasses the existing PHP templates.
- **Don't** copy reference-product navigation labels or routes that don't exist in this app -- keep the real routes.
- **Don't** let hover/focus states shift layout or reflow siblings.
- **Don't** hide essential content behind entrance animations. Motion is optional enhancement and must never control legibility.
- **Don't** use decorative gradients, oversized pills, or large-radius containers to manufacture hierarchy. Use spacing, typography, borders, and real media.
- **Do** keep policy, legal, customer, and internal guide content in Markdown/YAML sources and render it through the shared document surface so copy changes do not require template rewrites.

## Verification

- Check `/`, `/consult`, `/shop`, one product, `/login`, and authenticated account pages at 1440x1000 and 390x844 in a real browser.
- Confirm image crops, active navigation, focus states, card alignment, no hidden reveal content, no horizontal overflow, and that the next section is hinted in the first mobile viewport.
- Use the fixed development customer created by `bapXphp dev:user`; its password must come from `BAPX_TEST_USER_PASSWORD` and must never be committed.
- Run the repo's PHP tests, project-map validation, and local smoke test before commit or push.

## Implementation Notes

- Tokens above are also expressed as CSS custom properties in `assets/css/band.css` (`:root`). The semantic names here (`primary`, `on-primary`, `primary-container`, etc.) exist there as aliases (`--color-primary`, `--color-on-primary`, `--color-primary-container`, ...) layered directly on top of the original hue-named variables (`--color-maroon`, `--color-gold`, ...) -- both are safe to use; new code should prefer the semantic names.
- Elevation tokens: `--shadow-sm/md/lg/xl` plus `--shadow-focus` for the single focus-ring definition.
- Motion: `--transition-spring` (a restrained expressive-motion curve, `cubic-bezier(0.34, 1.4, 0.64, 1)`) is used for hover lifts on buttons and cards; `--transition-base` still governs color/shadow fades.
- This file can be linted against the open spec with `npx @google/design.md lint Design.md` if you want machine validation of token references and section order.
