# Changelog

## [Unreleased]
### Added
- Multi-stage Docker build pipeline with dedicated dependency/test/runtime targets, CI size benchmarking, and artifact exports. (E11-F1-I2)
- Tenant/brand scoped permission coverage reports with automated route analysis, REST + Filament CRUD, RBAC policy updates, structured logging/audit trails, and README/OpenAPI updates. (E10-F2-I2)
- RBAC enforcement gap analyses with tenant/brand scoped API + Filament CRUD, sanitized audit matrices, structured logging/audit trails, updated permissions, and README/OpenAPI coverage. (E10-F2-I1)
- Horizon dashboard deployment infrastructure with tenant/brand scoped models, REST + Filament CRUD, health monitoring, audit logging, and OpenAPI/README updates. (E11-F2-I2)
- Centralized observability pipeline management with tenant/brand scoped models, REST + Filament CRUD, Prometheus metrics export, structured logging, and documentation updates. (E11-F4-I2)
- Observability stack selection registry with tenant/brand scoped models, REST + Filament CRUD, decision matrix support, structured logging/metrics, and OpenAPI/README coverage. (E11-F4-I1)
- Redis cache and session configuration with tenant/brand scoped models, REST + Filament CRUD, runtime fallback driver, structured logging, and OpenAPI/README coverage. (E11-F3-I2)
- SLA policy registry with tenant/brand scoped models, REST + Filament CRUD, timer calculations, structured logging, and OpenAPI/README updates. (E4-F3-I2)
- CI quality gate management with tenant/brand scoped models, REST + Filament CRUD, artisan enforcement command, structured logging, and GitHub Actions quality gates. (E11-F5-I2)
- Brand configuration and custom domain management with tenant/brand scoped models, REST + Filament CRUD, verification jobs, structured logging/audit trails, and README/OpenAPI updates. (E9-F4-I3)
- Brand asset storage and theme delivery with tenant/brand scoped models, REST + Filament CRUD, CDN-aware delivery endpoints, caching headers, audit logging, and README/OpenAPI updates. (E9-F4-I2)
- Two-factor authentication enrollment and enforcement with tenant policy configuration, REST endpoints, challenge middleware, Filament credential management, and OpenAPI/README updates. (E10-F1-I2)
- Tenant/brand-aware permission registry with REST + Filament CRUD, audit logging, artisan command docs, and OpenAPI/README updates. (E2-F3-I1)
- Tenant/brand-scoped contact and company directory with Filament CRM surfaces, REST APIs, GDPR consent enforcement, structured audit logging, and documentation/OpenAPI coverage. (E2-F1-I2)
- Tenant-aware RBAC middleware enforcing `platform.access` and `portal.submit` permissions across admin and portal surfaces with correlation IDs, structured denial logging, and documentation/test coverage. (E2-F3-I3)
- GDPR anonymization policy registry with tenant/brand scoped API + Filament CRUD, RBAC, structured logging, and OpenAPI/README coverage. (E2-F7-I1)
- Tenant-scoped teams and membership management with RBAC-protected API endpoints, Filament CRUD, audit logging, and structured observability. (E2-F4-I1)
- Ticket relationship metadata modeling with tenant-scoped API, Filament CRUD, audit logging, and documentation updates. (E1-F6-I1)
- Ticket merge workflow with transactional service, tenant-scoped API and Filament UI, audit logging, structured logging, and OpenAPI/README updates. (E1-F6-I2)
- Customer portal ticket submission flow with portal form, REST API, Filament review UI, notifications, observability, and documentation updates. (E1-F1-I4)
- Ticket message visibility controls with API, policy, Filament resource, and observability for internal notes. (E1-F5-I1)
- Knowledge base categories and articles with migrations, RBAC-enforced APIs, Filament management, observability, and documentation updates. (E3-F1-I2)
- Ticket lifecycle broadcasting stack with REST + Filament management, queued broadcasts, audit logging, and OpenAPI coverage. (E1-F8-I2)
- Echo broadcasting authentication and connection monitoring with RBAC-protected API, Filament tooling, structured logging, and OpenAPI/README updates. (E1-F8-I1)
- Tenant-scoped audit log writers for tickets and contacts with masked diffs, a Filament audit viewer, and an authenticated API endpoint. (E2-F6-I2)
- Tenant role provisioning with per-tenant RBAC APIs, Filament administration, audit logging, and OpenAPI/README documentation. (E2-F2-I1)
- Ticket deletion and redaction workflow with approval holds, queued processing, audit logging, and REST/Filament interfaces. (E2-F7-I3)
- Multilingual knowledge base storage with translation migrations, RBAC-aware API/Filament CRUD, audit logging, and OpenAPI documentation. (E3-F5-I2)
- Knowledge base article search indexing with Scout/Meilisearch, queued sync jobs, RBAC-protected API search endpoint, and documentation/test coverage. (E3-F6-I2)
- Meilisearch infrastructure configuration with health-check command, Docker templates, runbook, and monitoring guidance. (E3-F6-I1)
- Hardened knowledge base HTML sanitization with allow-listed rich text, redacted audit logging, structured observability, and regression tests covering malicious payloads. (E3-F2-I3)
- Tenant-scoped ticket creation API with custom field validation, JSON:API responses, Filament custom field management, structured logging, and OpenAPI/README documentation. (E1-F1-I5)
- Ticket workflow enforcement with migrations, API + FormRequest validation, Filament CRUD, SLA recalculation, audit/logging, and OpenAPI/README updates. (E1-F4-I3)
### Fixed
- Preserve observability metrics scrape cadence during PATCH operations and return JSON-formatted `ERR_HTTP_403` payloads when metrics export is denied. (E11-F4-I2)
- Restore Pint compatibility and database credentials so CI linting and migrations succeed on MySQL and PostgreSQL.
- Declare the application as an ES module so Vite can import `laravel-vite-plugin` during CI builds without require() failures.
- Provide a committed `.env.testing` profile so `php artisan test` can run against SQLite during CI without requiring MySQL.
- Point PHPStan to the maintained Larastan extension file to prevent missing-include failures during linting.
- Enforce Pint import grouping in `tests/Feature/MessageVisibilityTest.php` to keep CI linting green.
