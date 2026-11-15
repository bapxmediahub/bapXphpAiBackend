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
The service checks recorded usage and current product, temple, category,
settings and published/unpublished article references before quarantining
owned image binaries. Metadata deletion failure restores quarantined files.
A successful operation is recorded through the existing admin audit service.

These guards are not an atomic cross-collection reference-lock protocol.
Concurrent content edits and media deletion should be serialized operationally;
do not claim a no-race guarantee across arbitrary independently edited records.

## Reproducible isolated test

`php tests/media-delete-integration.php` creates unique disposable files and
an authenticated fixture-only remotedb process. It checks:

1. Referenced media cannot be deleted.
2. A simulated metadata-delete failure restores the original binary.
3. An unused disposable upload loses both its metadata and binary.

The fixture server is loopback only, does not connect to production, and is
terminated by the test. No customer file is used as a fixture.

## Browser/remotedb evidence, 15 November 2026

The candidate PR was tested through real authenticated Playwright UI:

- Upload one uniquely named 32px PNG via Media Library at 1280px.
- Read only that new ID through remotedb: exactly one row, expected fixture
  description, and the candidate's stored binary exists.
- Attempt deletion without CSRF: rejected, row remains.
- Switch to 375px, confirm Delete upload in the UI.
- Re-read the same ID: zero rows. Its image URL returns 404.

The candidate runs locally against hosted business metadata; it is not a
claim that the new route was deployed at test time. Repeat the same disposable
workflow after release to validate Hostinger permissions and persistent media
storage. Never delete an existing customer image for release verification.

Credentials, full media catalogues, cookies and fixture binary files are not
committed. The result is supported by private local Playwright test logs and
the unchanged production/customer assets, not by a screenshot alone.
