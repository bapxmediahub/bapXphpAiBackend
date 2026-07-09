# App DOX

## Purpose

Owns PHP runtime behavior: bootstrap, routes, controllers, services, guards, persistence boundaries, integrations, and project-map scanning/rendering.

## Ownership

- `Controllers/`: route actions and request/response flow.
- `Services/`: business logic, JSON-store access, integrations, schema helpers, environment helpers, and agent context.
- `routes.php`: route registry consumed by the app and project map.
- `bootstrap.php`: runtime bootstrapping and shared helpers.

## Local Contracts

- Keep route -> controller -> service -> JSON-store boundaries.
- Extend existing services before introducing a new service.
- User-specific assistant data must flow through `AgentContextService` or equivalent filtering.
- Project-map structure, including global and internal navigation-to-route edges, belongs in `ProjectMapService::scan()` and `ProjectMapService::renderSystematicMermaid()`.
- Project-map scans must be deterministic across clean checkouts and deployments; exclude ignored runtime secret stores from generated artifacts.
- Consultation messages, call signaling, participant authorization, status, and analytics belong in `ConsultationService` and authenticated PHP API controllers.

## Work Guidance

- Read `storage/schema/collections.php` before changing JSON-backed behavior.
- Keep admin mutations auditable through `AuditLogService` when data changes.
- Do not bypass `SecretService` or `EnvService` with ad hoc file writes.
- Secrets (payment keys, SMTP credentials, API keys, SEO defaults) are stored in MySQL `secrets` table and are admin-editable through **Admin → Integrations**; never put secrets in `.env`. System env vars serve as fallback for critical credentials.
- Unknown routes render the themed `views/public/404.php` through `Router::renderNotFound()` and the `index.php` 404 branch; keep that view and its layout variables (`$pageTitle`, `$seo`, `$metaDescription`, `$metaRobots`, `$viewFile`) in sync so the 404 stays styled.
- Admin mail surfaces live at `/admin/email-inbox` and `/admin/email-outbox` (rendered by `AdminController::emailInbox`/`emailOutbox` via `MailStorageService`); keep those routes wired so the admin sidebar links stay live.

## Verification

- `php -l path/to/changed.php`
- `php tests/run.php`
- `php tools/generate-project-map.php`
- `php tools/validate-project-map.php`

## Child DOX Index
