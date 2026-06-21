# Public Interface Design System

This is the canonical visual contract for customer-facing pages in `views/` and `assets/css/band.css`. It adapts the supplied marketplace reference to Sri Panchami Spiritual without copying travel-specific content or navigation.

## Principles

- Keep the interface calm, warm-neutral, photo-first, and content-led.
- Use the client-supplied deep maroon and muted gold as the public brand pair. Decorative spiritual imagery may retain its source colors, but UI chrome must not introduce unrelated accent colors.
- Prefer whitespace, typography, hairline borders, and one restrained shadow tier over decorative panels.
- Keep the existing PHP templates, routes, forms, and JSON-backed behavior. Design changes must not scaffold a second frontend.

## Tokens

### Color

- Canvas: `#faf7f0`
- Soft surface: `#f7f0e4`
- Subtle surface: `#efe5d3`
- Primary ink: `#222222`
- Body ink: `#3f3f3f`
- Muted ink: `#6a6a6a`
- Soft ink: `#929292`
- Hairline border: `#dddddd`
- Soft divider: `#ebebeb`
- Strong border: `#c1c1c1`
- Brand primary / deep maroon: `#3a0003`
- Brand primary active: `#240002`
- Brand secondary / muted gold: `#d1b368`
- Brand secondary light: `#f3e8c9`
- Success and error colors are semantic exceptions and must not become general decoration.

### Typography

- Use Inter with system sans-serif fallbacks for all public text.
- Do not use a separate serif display family.
- Page headings are generally `22px` to `28px`, weight `600` to `700`.
- Body copy is `14px` to `16px`, weight `400`, line-height `1.45` to `1.6`.
- Labels and metadata are `12px` to `14px`, weight `500` to `600`.
- Letter spacing is `0`. Uppercase is reserved for short operational labels.

### Geometry

- Spacing scale: `2, 4, 8, 12, 16, 24, 32, 48, 64px`.
- Radius scale: `4, 8, 14, 20, 32px`, plus fully rounded pills.
- Inputs and standard buttons are `48px` high with an `8px` radius.
- Search and filter controls may be `64px` high and fully rounded.
- Repeated photo cards use a `14px` radius and stable media aspect ratio.
- Use `0 2px 8px rgba(0, 0, 0, 0.12)` as the standard elevated shadow. Do not stack decorative shadow tiers.

## Components

- Header: warm-neutral, approximately `80px` high on desktop, non-sticky, with a hairline bottom border, compact logo, centered primary navigation, active underline, and right-aligned account/cart actions. It must not cover overlapping card portraits during scroll.
- Navigation: retain the product's real routes and labels. Do not copy reference-product labels that do not exist in this application.
- Authenticated navigation: keep global account actions to Dashboard and Logout. Dashboard-owned Orders, Sessions, Wallet, and Back to Home belong in the internal left-side account menu and use `/account/dashboard/*` URLs.
- Buttons: primary buttons use solid `#3a0003` with white text. Secondary buttons use muted gold or warm-neutral backgrounds with maroon text. Hover states must not move layout.
- Forms: white fields, clear labels, `8px` radius, strong ink focus ring, and no glow effects.
- Search/filter surfaces: use a single rounded search control or a quiet grouped filter row; keep labels and values readable without card nesting.
- Product cards: image first, consistent crop, warm-neutral image background, compact hierarchy, and restrained metadata.
- Astrologer cards: deep-maroon content panel with a circular portrait centered across the top edge, approximately half outside the panel. Crop the supplied square portrait around the face and remove its unused red template area from the visible circle; do not render the raw square edge-to-edge. Message, call, and profile are three equal circular icon controls in one row; unavailable session actions remain visibly disabled while profile stays available.
- Hero: keep the actual deity imagery visible and correctly framed against white or a soft neutral surface. Text remains compact and left aligned on desktop; slides must show one image at a time.
- Footer: deep maroon with muted-gold headings and warm-neutral body text, separated from content by a gold hairline.

## Responsive Rules

- Mobile: below `744px`; use one primary content column, compact header controls, and the existing bottom navigation.
- Tablet: `744px` through `1128px`; reduce grid columns while preserving card geometry.
- Desktop: above `1128px`; use centered containers up to `1300px` and `64px` section spacing.
- Text, buttons, images, and fixed controls must not overlap or shift when content length changes.

## Verification

- Check the home page and `/consult` at desktop and mobile widths in a real browser.
- Confirm all 21 image crops, active navigation, focus states, warm-neutral canvas, card alignment, and footer contrast.
- Run the repo's PHP tests, project-map validation, and local smoke test before commit or push.
