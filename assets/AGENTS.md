# Assets DOX

## Purpose

Owns CSS, static images, and reusable media assets.

## Ownership

- `css/`: project styling.
- `images/`: static product, temple, astrologer, logo, and shared image assets.
- `images/astrologers/source/`: preserved 1080×1080 client card originals; public face crops live in `images/astrologers/client/`.

## Local Contracts

- Do not add a frontend build step or SPA asset pipeline.
- `Design.md` is the canonical public visual contract. Keep `assets/css/band.css` and the critical CSS in `views/layouts/app.php` token-compatible so first paint and loaded state match.
- Product, temple, and astrologer media should stay compatible with the media-library picker/upload flow.
- Asset paths referenced by JSON data or templates must resolve locally unless intentionally remote.

## Work Guidance

- Keep CSS aligned with existing PHP templates and layout classes. Use the shared design tokens instead of page-local brand colors, gradients, or shadow systems.
- Avoid unused asset files when replacing images or CSS.
- Keep client astrologer portraits in one circular, top-overlapping card frame across public surfaces; keep message, call, and profile icon controls aligned in one row. Varahi hero slides use a warm-neutral frame with one visible image at a time.

## Verification

- `php tests/run.php`
- Browser workflow for changed visual surfaces.

## Child DOX Index
