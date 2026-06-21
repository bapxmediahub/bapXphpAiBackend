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
- Project-map structure, including shared navigation-to-route edges, belongs in `ProjectMapService::scan()` and `ProjectMapService::renderSystematicMermaid()`.
- Project-map scans must be deterministic across clean checkouts and deployments; exclude ignored runtime secret stores from generated artifacts.
- Consultation messages, call signaling, participant authorization, status, and analytics belong in `ConsultationService` and authenticated PHP API controllers.

## Work Guidance

- Read `storage/schema/collections.json` before changing JSON-backed behavior.
- Keep admin mutations auditable through `AuditLogService` when data changes.
- Do not bypass `SecretService`, `EnvService`, or `JsonStoreService` with ad hoc file writes.

## Verification

- `php -l path/to/changed.php`
- `php tests/run.php`
- `php tools/generate-project-map.php`
- `php tools/validate-project-map.php`

## Child DOX Index
