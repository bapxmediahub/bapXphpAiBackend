# Views DOX

## Purpose

Owns PHP-rendered public, account, admin, and layout templates.

## Ownership

- `public/`: customer-facing pages and forms.
- `account/`: authenticated customer account pages.
- `admin/`: owner/admin pages.
- `layouts/`: shared shells and layout markup.

## Local Contracts

- Keep the frontend PHP-template based. Do not add React, CDN React, SPA shells, or a second frontend.
- Follow root `Design.md` for every customer-facing template; preserve real product routes and content while applying its tokens, hierarchy, geometry, and responsive rules.
- Templates should consume controller-provided data and service-backed JSON data, not read storage directly.
- Forms that mutate admin data must map to auditable controller actions.

## Work Guidance

- Preserve visible copy unless the user asks for copy changes.
- Reuse shared layout and component classes before adding page-specific variants. Do not create nested card layouts or decorative UI that conflicts with `Design.md`.
- Use media-library picker/upload flows for product, temple, and astrologer media.
- Keep customer and astrologer consultation rooms participant-scoped and backed by the existing PHP API endpoints.
- Remove or wire dead buttons, placeholder cards, duplicated fallbacks, and stale labels before finishing.
- Customer help uses the existing blog cards and article renderer under `/blog/category/help`; `/docs` and `/help/{slug}` are redirect-only compatibility routes.
- Blog templates preserve Markdown ownership while providing editorial media, metadata, readable line length, and mobile hierarchy.
- Keep customer Orders, Sessions, Wallet, and Back to Home links in the shared account-side navigation under `/account/dashboard/*`; the global authenticated header exposes only Dashboard and Logout.
- Astrologer marketplace and homepage cards must use real profile availability, rates, optional metadata, and verified review totals; do not fabricate states, ratings, counts, languages, or experience.
- Astrologer marketplace and homepage cards share the circular top-overlapping portrait layout and three icon actions. Keep availability truthful; offline and busy actions may create an uncharged queued request, while only available providers can start immediately.
- Wrap each marketplace portrait in the shared clipped frame so source-template margins never determine visible face placement.

## Verification

- `php -l path/to/changed.php`
- `php tests/run.php`
- Browser workflow for changed pages.

## Child DOX Index
