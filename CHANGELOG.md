# Changelog

## [Unreleased]
### Added
- Customer portal ticket submission flow with portal form, REST API, Filament review UI, notifications, observability, and documentation updates. (E1-F1-I4)
- Ticket message visibility controls with API, policy, Filament resource, and observability for internal notes. (E1-F5-I1)
- Knowledge base categories and articles with migrations, RBAC-enforced APIs, Filament management, observability, and documentation updates. (E3-F1-I2)
- Ticket lifecycle broadcasting stack with REST + Filament management, queued broadcasts, audit logging, and OpenAPI coverage. (E1-F8-I2)
- Tenant-scoped audit log writers for tickets and contacts with masked diffs, a Filament audit viewer, and an authenticated API endpoint. (E2-F6-I2)
- Tenant role provisioning with per-tenant RBAC APIs, Filament administration, audit logging, and OpenAPI/README documentation. (E2-F2-I1)
- Ticket deletion and redaction workflow with approval holds, queued processing, audit logging, and REST/Filament interfaces. (E2-F7-I3)
- Multilingual knowledge base storage with translation migrations, RBAC-aware API/Filament CRUD, audit logging, and OpenAPI documentation. (E3-F5-I2)
- Contact and company administration with GDPR consent enforcement, tag management, REST/Filament CRUD, audit logging, and OpenAPI documentation. (E2-F1-I2)
### Fixed
- Restore Pint compatibility and database credentials so CI linting and migrations succeed on MySQL and PostgreSQL.
- Declare the application as an ES module so Vite can import `laravel-vite-plugin` during CI builds without require() failures.
- Provide a committed `.env.testing` profile so `php artisan test` can run against SQLite during CI without requiring MySQL.
- Point PHPStan to the maintained Larastan extension file to prevent missing-include failures during linting.
- Enforce Pint import grouping in `tests/Feature/MessageVisibilityTest.php` to keep CI linting green.
