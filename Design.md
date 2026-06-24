# Public Interface Design System

This is the canonical visual contract for customer-facing pages in `views/` and `assets/css/band.css`. It adapts the supplied marketplace reference to Sri Panchami Spiritual without copying travel-specific content or navigation.

## Principles

- Keep the interface calm, warm, earthy, and content-led.
- Use maroon (`#3a0003`) as the primary brand color, warm brown (`#7a4a35`) as an accent, and muted gold (`#d1b368`) as the secondary pair.
- Prefer whitespace, refined typography, hairline borders, and restrained shadows over decorative panels.
- Keep the existing PHP templates, routes, forms, and JSON-backed behavior. Design changes must not scaffold a second frontend.

## Tokens

### Color

- Canvas: `#faf7f0`
- Soft surface: `#f7f0e4`
- Warm surface: `#f6ede4`
- Primary ink: `#222222`
- Body ink: `#3f3f3f`
- Muted ink: `#6a6259`
- Soft ink: `#91877c`
- Hairline border: `#d8ccb7`
- Soft border: `#eadfcd`
- Brand primary / deep maroon: `#3a0003`
- Brand primary active: `#240002`
- Brand accent / warm brown: `#7a4a35`
- Brand accent hover: `#9a6a55`
- Brand accent light: `#a67a64`
- Brand secondary / muted gold: `#d1b368`
- Brand secondary light: `#f3e8c9`
- Brand secondary dark: `#b89440`
- Success and error colors are semantic exceptions.

### Typography

- Body: Inter (300–700 weight) with system sans-serif fallbacks.
- Display headings: Inter, weight 600–700.
- Decorative accent headings: Playfair Display (600 italic) — used sparingly on eyebrow labels and value-card titles.
- Page headings: `22px` to `28px`, weight `600` to `700`.
- Body copy: `14px` to `16px`, weight `400`, line-height `1.45` to `1.6`.
- Labels and metadata: `12px` to `14px`, weight `500` to `600`.
- Letter spacing is `0`. Uppercase is reserved for short operational labels.
- Serif italic should be used as a decorative highlight, not for body text.

### Geometry

- Spacing scale: `2, 4, 8, 12, 16, 24, 32, 48, 64px`.
- Radius scale: `4, 8, 14, 20, 32px`, plus fully rounded pills.
- Inputs and standard buttons are `48px` high with an `8px` radius.
- Search and filter controls may be `64px` high and fully rounded.
- Repeated photo cards use a `14px` radius and stable media aspect ratio.
- Use `0 2px 8px rgba(0, 0, 0, 0.12)` as the standard elevated shadow. Do not stack decorative shadow tiers.

## Components

- Header: warm-neutral (`rgba(250,247,240,0.98)`), approximately `80px` high on desktop, non-sticky, with a hairline bottom border (`rgba(209,179,104,0.45)`), compact logo, centered primary navigation, active gold underline, and right-aligned account/cart actions.
- Navigation: retain the product's real routes and labels. Do not copy reference-product labels that do not exist in this application.
- Buttons: primary buttons use solid `#3a0003` with white text and a subtle gold overlay on hover (slide-up overlay). Minimum height `48px`, radius `8px`, no uppercase transform. Secondary buttons use gold background with maroon text. Hover states must not move layout.
- Forms: white fields, clear labels, `8px` radius, strong ink focus ring, and no glow effects.
- Search/filter surfaces: use a single rounded search control or a quiet grouped filter row; keep labels and values readable without card nesting.
- Product cards: white card, `14px` radius, `1px` soft gold-tinted border, image on top with warm background, centered or left-aligned content, and `box-shadow: var(--shadow-sm)` with hover elevation to `var(--shadow-md)`.
- Astrologer cards: deep-maroon content panel with a circular portrait centered across the top edge, approximately half outside the panel. Crop the supplied square portrait around the face. Message, call, and profile are three equal circular icon controls in one row.
- Hero: keep the actual deity imagery visible and correctly framed against dark gradient with temple background. Text is left aligned on desktop with gold eyebrow and stats. Single-column stack on mobile with deity image first, text centered.
- Value proposition cards: 4-column grid on desktop (2 on tablet, 1 on mobile), white card with warm icon circle, serif heading, muted body text, hover elevation lift `4px`.
- Footer: white background with soft border. Warm brown headings and muted body text. Bottom bar with copyright and credit.

## Section Types

- `.section`: default, `64px` vertical padding, no background override.
- `.section--alt`: uses `#f7f0e4` warm surface background.
- `.section--warm`: uses `#f6ede4` warmer background for value sections.
- Responsive: tablet `56px` padding, mobile `48px`.

## Responsive Rules

- Mobile: below `744px`; use one primary content column, compact header controls, and the existing bottom navigation.
- Tablet: `744px` through `1128px`; reduce grid columns while preserving card geometry.
- Desktop: above `1128px`; use centered containers up to `1300px` and `64px` section spacing.
- Text, buttons, images, and fixed controls must not overlap or shift when content length changes.

## Verification

- Check the home page and `/consult` at desktop and mobile widths in a real browser.
- Confirm all 21 image crops, active navigation, focus states, warm canvas, card alignment, and footer contrast.
- Run the repo's PHP tests, project-map validation, and local smoke test before commit or push.
