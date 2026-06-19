# Assets DOX

## Purpose

Owns CSS, static images, and reusable media assets.

## Ownership

- `css/`: project styling.
- `images/`: static product, temple, astrologer, logo, and shared image assets.

## Local Contracts

- Do not add a frontend build step or SPA asset pipeline.
- Product, temple, and astrologer media should stay compatible with the media-library picker/upload flow.
- Asset paths referenced by JSON data or templates must resolve locally unless intentionally remote.

## Work Guidance

- Keep CSS aligned with existing PHP templates and layout classes.
- Avoid unused asset files when replacing images or CSS.
- Keep client astrologer portraits in one stable card frame across public surfaces; Varahi hero slides use a white frame with one visible image at a time.

## Verification

- `php tests/run.php`
- Browser workflow for changed visual surfaces.

## Child DOX Index
