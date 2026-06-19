# Tests DOX

## Purpose

Owns PHP regression tests and test expectations for repo contracts.

## Ownership

- `run.php`: broad project regression suite.

## Local Contracts

- Tests should assert current project contracts, not deleted legacy files.
- When docs, map artifacts, or skills change, update tests in the same change.

## Work Guidance

- Keep tests runnable with `php tests/run.php`.
- Avoid assertions that require network access or production credentials.

## Verification

- `php tests/run.php`

## Child DOX Index

