---
type: doc
title: Media Deletion Verification
description: Disposable-upload and rollback checks for the guarded Media Library delete action.
category: docs
---

# Guarded Media Library deletion

`POST /admin/media/delete` is authenticated/admin-only and CSRF-protected.
The owner confirms a visible Delete upload action. Only valid uploaded media
records are eligible; bundled assets and legacy YAML-only records are not.
Upload history identifies the owner at the time of upload. The service checks both
image URLs against current products, temples, categories, settings and all articles
before quarantining the binaries. Historical consultant references also require a
successful consultant lookup. An owner can delete an image after replacing it or
deleting its product. Unknown or malformed historical references prevent deletion. Metadata deletion failure restores quarantined files.
A successful operation is recorded through the existing admin audit service.

These guards are not an atomic cross-collection reference-lock protocol.
Concurrent content edits and media deletion should be serialized operationally;
do not claim a no-race guarantee across arbitrary independently edited records.

## Reproducible isolated test

`php tests/media-delete-integration.php` creates unique disposable files and
an authenticated fixture-only remotedb process. It checks:

1. Current references and unverifiable upload history block deletion. A product that has since replaced the image, or a deleted product, releases the historical reference.
2. A simulated metadata-delete failure restores the original binary.
3. An unused disposable upload loses both its metadata and binary.

The fixture server is loopback only, does not connect to production, and is
terminated by the test. No customer file is used as a fixture.

## Repeatable browser/remotedb acceptance

Use only a newly created, uniquely named disposable upload. Verify its exact ID
and binary, confirm a no-CSRF request is rejected, then remove it through the
real admin UI at desktop and 375px. Confirm the same row no longer exists and
its image URL is no longer served. Never delete an existing customer image.

Record execution results, candidate SHA and deployed verification on the
relevant GitHub issue/PR. Private credentials, cookies and uploaded test binaries
remain outside Git; this guide describes procedure rather than release status.


## Metadata-only editing

\`POST /admin/media/metadata\` uses the existing owner authentication and CSRF guard.
It checks a revision of the editable metadata and identity on a locked MySQL row,
including when invoked through the dedicated authenticated remotedb mutation.
Only description, alternative text and caption are patched. No binary is moved,
no missing record is recreated, and no URL or usage fields are changed by this action.
The library search and preview use the new metadata. This is not a storefront-wide
alternative-text migration. Bundled and legacy-only assets are read-only.
The isolated MySQL cases in \`tests/admin-list-pagination.php\` verify persistence,
field isolation, search, stale and missing rows, and explicit clearing.
\`tests/media-metadata-browser.cjs\` exercises the PHP view and form with intercepted
fixture responses at 375px and 1280px.
