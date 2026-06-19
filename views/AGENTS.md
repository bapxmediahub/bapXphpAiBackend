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
- Templates should consume controller-provided data and service-backed JSON data, not read storage directly.
- Forms that mutate admin data must map to auditable controller actions.

## Work Guidance

- Preserve visible copy unless the user asks for copy changes.
- Use media-library picker/upload flows for product, temple, and astrologer media.
- Keep customer and astrologer consultation rooms participant-scoped and backed by the existing PHP API endpoints.
- Remove or wire dead buttons, placeholder cards, duplicated fallbacks, and stale labels before finishing.

## Verification

- `php -l path/to/changed.php`
- `php tests/run.php`
- Browser workflow for changed pages.

## Child DOX Index
