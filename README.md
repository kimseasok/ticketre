# Prompt Engineering Service Desk Skeleton

## Requirements
- PHP 8.3+
- Composer 2
- Node 20+ (project package.json declares `"type": "module"` for Vite)
- Docker (optional but recommended)

## How to Run Locally
1. `composer install`
2. `npm install && npm run build`
3. `cp .env.example .env`
4. `php artisan key:generate`
5. `php artisan migrate --seed`
6. `php artisan serve`

> Note: requires Docker stack (db, redis, meilisearch) or compatible local services.

## Docker
- `make up` to start services (db, redis, meilisearch, queue, app, nginx)
- `make down` to stop
- Copy `.env.docker.example` to `.env.docker` to populate container-specific secrets including the Meilisearch master key and health-check configuration.

## Meilisearch Infrastructure

- Configuration defaults live in `config/meilisearch.php` with environment keys documented in `.env.example`. Health checks and backup paths are safe to tweak per-tenant.
- `php artisan meilisearch:health-check` performs a JSON health probe using structured logs with correlation IDs; it is also scheduled every five minutes when `SCOUT_DRIVER=meilisearch`.
- Backups write to `storage/app/backups/meilisearch` by default. See `docs/runbooks/meilisearch.md` for provisioning, backup rotation, and monitoring alert definitions covering uptime and indexing lag.
- The Docker service exports persistent volume `meilisearch-data` and respects `MEILISEARCH_HEALTHCHECK_URL` so managed hosts can override the default probe target.

## Testing
- `make test`
- `make ci-check` to run lint, static analysis, and tests

> PHPUnit loads `.env.testing` to exercise the suite against in-memory SQLite, so `php artisan test` runs without external services.

## Ticket Message Visibility API

Extend ticket conversations with explicit visibility controls:

- `POST /api/v1/tickets/{ticket}/messages` – create an internal note or public reply (requires `tickets.manage`).
- `GET /api/v1/tickets/{ticket}/messages` – returns all messages for agents; viewers automatically receive only public messages.
- `PATCH /api/v1/tickets/{ticket}/messages/{message}` – update visibility or content (agents only).
- `DELETE /api/v1/tickets/{ticket}/messages/{message}` – soft delete a message (agents only).

All requests must include the multi-tenant headers:

```http
X-Tenant: <tenant-slug>
X-Brand: <brand-slug>
```

API responses follow the `{ "data": { ... } }` envelope, while errors follow `{ "error": { "code", "message" } }`.

Filament administrators can manage the same records via `/admin/messages`, with filters for tenant, brand, and visibility pre-configured.

## Contact & Company Directory

Tenant administrators can now manage a centralized contact directory with GDPR-aware consent tracking, hashed audit trails, and reusable tags:

- `GET /api/v1/contacts` – list contacts for the active tenant with optional `search`, `company_id`, `gdpr_consent`, and `tag_ids` filters. (Requires `contacts.view` or `contacts.manage`).
- `POST /api/v1/contacts` – create a contact with required GDPR consent metadata, optional company linkage, and tag assignments. (Requires `contacts.manage`).
- `GET /api/v1/contacts/{contact}` / `PATCH /api/v1/contacts/{contact}` / `DELETE /api/v1/contacts/{contact}` – inspect, update, or soft delete a contact while preserving hashed audit trails and structured JSON logs. (Requires `contacts.view`/`contacts.manage`).
- `GET /api/v1/companies` – list tenant companies with search support. (Requires `companies.view` or `contacts.view`).
- `POST /api/v1/companies` / `PATCH /api/v1/companies/{company}` / `DELETE /api/v1/companies/{company}` – manage company records with per-tenant uniqueness on name/domain and audit logging. (Requires `companies.manage` or `contacts.manage`).

Every response follows the `{ "data": { ... } }` envelope and error payloads continue to use `{ "error": { "code", "message" } }`. Requests must include the `X-Tenant` header; optional `X-Brand` headers are ignored for contacts/companies because data is tenant scoped.

Structured audit entries record hashed emails, phone numbers, consent notes, and tag diffs, while API logs emit correlation IDs for observability. Consent method/source are mandatory whenever `gdpr_consent` is true, and the system automatically timestamps consent when missing. Contacts can be tagged with tenant-owned labels to drive segmentation—Filament exposes inline tag creation with hashed metadata.

Filament administrators manage the same data via `/admin/contacts` and the new `/admin/companies` resource. Both resources respect tenant scoping, surface consent status, support bulk actions, and redaction-safe metadata entry. Only users with `contacts.manage`/`companies.manage` may mutate records, while viewers granted `contacts.view` or `companies.view` receive read-only dashboards.

## Customer Portal Ticket Submission

Give contacts a branded, unauthenticated portal for raising support tickets while keeping internal agents in control of triage.

- **Public API** – `POST /api/v1/portal/tickets` accepts JSON or multipart form payloads with the standard tenant headers. Requests capture `name`, `email`, `subject`, `message`, optional `tags` (up to five), and up to five attachments. Successful submissions return a confirmation payload with a stable reference, correlation ID, and confirmation link. Validation errors respond with `ERR_VALIDATION` and field details.
- **Portal UI** – `/portal/tickets/create` renders the submission form for the active tenant/brand, surfaces inline validation errors, and redirects to `/portal/tickets/{id}/confirmation` with a friendly reference code once accepted. Confirmation emails use the `TicketPortalSubmissionConfirmation` notification template and redact PII in structured logs.
- **Agent surfaces** – Authenticated users with `tickets.view` or `tickets.manage` may review submissions via `GET /api/v1/ticket-submissions` (with optional `channel`, `status`, and `search` filters) or Filament at `/admin/ticket-submissions`. Records display correlation IDs, hashed IP metadata, attachment counts, and linked ticket/contact context with eager-loaded relations to prevent N+1 queries.

All responses continue to use the `{ "data": { ... } }` envelope and include the structured JSON logging correlation ID. Tenant isolation is enforced by middleware, and demo seeds (`DemoDataSeeder`) provision NON-PRODUCTION sample submissions for manual testing.

## Knowledge Base Categories & Articles

The knowledge base module introduces hierarchical categories with closure table metadata and brand-aware article publishing. All APIs require authentication plus tenant and optional brand headers:

```http
X-Tenant: <tenant-slug>
X-Brand: <brand-slug>
```

### Category API

- `GET /api/v1/kb-categories` – list categories ordered by hierarchy for the current tenant/brand (requires `knowledge.view`).
- `POST /api/v1/kb-categories` – create a category (requires `knowledge.manage`). Accepts `name`, `slug`, optional `parent_id`, and `order`.
- `GET /api/v1/kb-categories/{id}` – fetch a single category with parent metadata.
- `PATCH /api/v1/kb-categories/{id}` – update category attributes, including re-parenting within the same brand.
- `DELETE /api/v1/kb-categories/{id}` – soft delete a category; descendants are soft deleted and linked articles are detached.

### Article API

- `GET /api/v1/kb-articles` – list articles with optional `status`, `locale`, or `category_id` filters. When a locale is supplied the response selects that translation if it exists, otherwise it falls back to the article’s `default_locale`.
- `POST /api/v1/kb-articles` – create a multilingual article tied to a category (requires `knowledge.manage`). Requests accept `brand_id`, `category_id`, `slug`, `default_locale`, and a `translations` array where each entry defines `locale`, `title`, `status`, `content`, optional `excerpt`, `metadata`, and `published_at`.
- `GET /api/v1/kb-articles/{id}` – retrieve an article with category and author context. The response includes the selected translation alongside a `translations` collection containing every locale and a digest-friendly metadata payload.
- `PATCH /api/v1/kb-articles/{id}` – update base metadata or synchronise translations. Passing `translations` replaces/updates locales, while including `delete: true` on an entry softly removes that locale. The `default_locale` must always resolve to an active translation.
- `DELETE /api/v1/kb-articles/{id}` – soft delete the article and its translations while retaining audit history.
- `GET /api/v1/kb-articles/search` – full-text search powered by Laravel Scout + Meilisearch. Filters include `locale`, `status`, `category_id`, and tenant/brand scope is enforced automatically. Responses include the standard resource payload plus optional search `score` and Meilisearch highlight snippets under `highlights`.

Articles are indexed asynchronously after every create/update/delete or translation change via the `SyncKbArticleSearchDocument` job. The job retries with exponential backoff, redacts slug and query data via SHA-256 digests, and writes structured JSON logs with correlation IDs for observability.

Structured JSON logs capture every knowledge base search request with hashed query digests while audit logs continue to record create/update/delete operations. Filament exposes the same functionality via `/admin/kb-categories` and `/admin/kb-articles`, featuring tenant/brand scoped queries, validation rules, and soft-delete management. Demo data is seeded through `DemoDataSeeder` for NON-PRODUCTION environments only.

## Tenant Role Management

Every tenant receives a protected trio of roles—`Admin`, `Agent`, and `Viewer`—seeded automatically whenever a tenant is created. Each role is backed by Spatie permissions and logged through the shared audit log pipeline:

- `GET /api/v1/roles` – list roles for the authenticated tenant (requires `roles.view`). Supports optional `search` query for name/slug.
- `POST /api/v1/roles` – create a custom tenant role with an optional description and permission set (requires `roles.manage`).
- `PATCH /api/v1/roles/{role}` – update role metadata or synced permissions. System roles retain their slug and cannot be downgraded.
- `DELETE /api/v1/roles/{role}` – remove custom roles. System roles return `ERR_ROLE_PROTECTED` with status `422`.

All endpoints follow the `{ "data": { ... } }` success envelope and `{ "error": { "code", "message" } }` error contract. Tenant headers are required:

```http
X-Tenant: <tenant-slug>
```

Filament administrators can manage the same data under `/admin/roles`. The resource respects tenant scoping automatically, disables destructive actions for system roles, and surfaces permission counts to help audit access. Each create/update/delete call emits a structured JSON log with correlation IDs and writes a `role.*` audit entry for traceability.

### Permission catalog

Spatie permissions are now first-class resources so that tenants can extend the system catalog without custom code. Two new permissions—`permissions.view` and `permissions.manage`—are provisioned automatically for Admins (view-only access is granted to Agents). Endpoints follow the standard API envelope and require tenant headers:

- `GET /api/v1/permissions` – list system and tenant-specific permissions in scope (requires `permissions.view`). Supports optional `search` and `system_only` query parameters.
- `POST /api/v1/permissions` – create a tenant permission (requires `permissions.manage`). Names and slugs are unique across both system and tenant scopes and audit logs capture hashed descriptions.
- `GET /api/v1/permissions/{permission}` – retrieve metadata for a permission that is either global or owned by the active tenant (requires `permissions.view`).
- `PATCH /api/v1/permissions/{permission}` – update description or guard metadata for tenant-owned permissions (requires `permissions.manage`). System permissions return `ERR_IMMUTABLE_PERMISSION`.
- `DELETE /api/v1/permissions/{permission}` – remove tenant-owned permissions with audit + structured logs. System permissions return `ERR_IMMUTABLE_PERMISSION` with status `422`.

Filament exposes the same catalogue at `/admin/permissions`, with inline validation for duplicates, tenant-aware filtering, and toast errors when attempting to mutate system records. Permission caching is now tenant- and brand-aware via a custom registrar that salts Spatie’s cache keys using the current scope to avoid cross-tenant bleed-through while retaining 24-hour cache lifetimes.

### RBAC middleware and access attempts

- A dedicated `permission` middleware now guards every authenticated API route and Filament panel request. Permissions are resolved via Spatie gates with tenant and brand alignment checks before the controller executes.
- A new `admin.access` permission determines who can load the Filament admin experience. The system `Admin` and `Agent` roles are seeded with this capability; custom roles may opt in through the existing role APIs.
- Failed authorization attempts are recorded in the new `access_attempts` table with hashed IP/user-agent values, correlation IDs, and the exact permission that was denied. These rows provide a lightweight audit trail for SOC analysts without persisting raw PII.
- The same events emit structured JSON logs under the `authorization_attempt` key so central logging can raise alerts for repeated failures.

## Team Management

Model collaborative support pods with tenant- and brand-aware teams:

- `GET /api/v1/teams` – list teams for the active tenant/brand (requires `teams.view`). Supports optional `search` and `brand_id` filters.
- `POST /api/v1/teams` – create a team (requires `teams.manage`). Payload accepts `name`, optional `brand_id`, `default_queue`, `description`, and a `members` array of `{ user_id, role, is_primary }` entries.
- `GET /api/v1/teams/{team}` – retrieve a team including membership roster (requires `teams.view`).
- `PATCH /api/v1/teams/{team}` – update metadata or replace membership assignments (requires `teams.manage`).
- `DELETE /api/v1/teams/{team}` – soft delete a team and its memberships (requires `teams.manage`).

All endpoints enforce the standard `{ "data": { ... } }` success envelope and `{ "error": { "code", "message" } }` error schema with tenant headers:

```http
X-Tenant: <tenant-slug>
X-Brand: <brand-slug>
```

Teams emit `team.*` audit log entries that redact descriptions to hashed digests and log structured JSON with correlation IDs, membership counts, and timing metadata. Filament exposes the same CRUD via `/admin/teams`, including brand filters, member repeaters, and soft-delete management. The NON-PRODUCTION demo seeder provisions Tier 1 and Escalation teams so environments can validate RBAC and audit trails quickly.
## Audit Log Writers & Viewer

Every ticket and contact create, update, and delete action now produces an audit trail entry with tenant, optional brand, actor, and a redacted change set:

- Ticket subjects are hashed and metadata is reduced to key lists so no raw PII is persisted.
- Contact emails and phone numbers are stored as SHA-256 digests alongside sanitized field diffs.
- Structured JSON logs include the correlation ID and timing metadata for observability.

### Audit Log API

- `GET /api/v1/audit-logs` – list audit events for the authenticated tenant (requires `audit_logs.view`).
  - Optional query parameters: `action`, `auditable_type` (`ticket`, `contact`, `message`, `kb_article`, `kb_category`), `auditable_id`, `user_id`, `from`, `to`, `per_page`.
  - Responses use the standard `{ "data": [...] }` envelope with pagination metadata; errors follow `{ "error": { "code", "message" } }`.

All requests must include tenant and optional brand headers:

```http
X-Tenant: <tenant-slug>
X-Brand: <brand-slug>
```

Filament administrators can review the same history via `/admin/audit-logs`, which provides action, actor, and date filters scoped to the active tenant and brand.

> **Tenant + Brand assumption:** contact audit entries inherit the initiating user's brand to maintain consistent brand scoping during review. Mixed-brand tenants should authenticate with the relevant brand header when querying the API or Filament.

## Contact Anonymization Workflow

Data-subject requests can now be fulfilled without purging analytics by queuing a tenant-scoped anonymization job. Only users with the new `contacts.anonymize` permission (seeded for tenant Admins) may initiate the flow.

- `GET /api/v1/contact-anonymization-requests` – list pending/processed requests with optional `status`, `contact_id`, and `brand_id` filters. Responses include the sanitized contact snapshot and requester details.
- `POST /api/v1/contact-anonymization-requests` – enqueue anonymization for a contact, capturing the reason and correlation ID. The background job replaces the contact’s PII with a pseudonym, updates linked ticket metadata, and writes `contact.anonymized` audit entries.
- `GET /api/v1/contact-anonymization-requests/{id}` – retrieve a single request to review status, pseudonym, and processing timestamps.

All endpoints require the standard multi-tenant headers:

```http
X-Tenant: <tenant-slug>
X-Brand: <brand-slug>
```

Errors use the `{ "error": { "code", "message" } }` schema. Successful responses include the `correlation_id` used for structured JSON logging so investigations can stitch audit trails across services.

Filament exposes the same functionality at `/admin/contact-anonymization-requests`, complete with tenant/brand filters and request detail modals. Creating a request through Filament dispatches the same queue job and logs the action with hashed reason metadata.

> The demo seeder provisions a NON-PRODUCTION example request showing the post-anonymization state. Run `php artisan queue:work` locally to process new requests automatically.

## Ticket Deletion & Redaction Workflow

Tickets that contain personal data can be purged while preserving operational analytics via the queued deletion workflow. The new `tickets.redact` permission is provisioned for tenant Admins and governs both the API and Filament surfaces.

- `GET /api/v1/ticket-deletion-requests` – list requests scoped to the active tenant/brand with optional `status`, `ticket_id`, and `brand_id` filters.
- `POST /api/v1/ticket-deletion-requests` – register a deletion request, capture the legal reason, and assign a correlation ID for observability.
- `POST /api/v1/ticket-deletion-requests/{id}/approve` – approve a request with a reversible hold window (0–168 hours). Approval enqueues the background processor.
- `POST /api/v1/ticket-deletion-requests/{id}/cancel` – cancel a pending or approved request before the hold expires.
- `GET /api/v1/ticket-deletion-requests/{id}` – inspect status, hold timers, aggregate metrics, and requester/approver metadata.

Processing replaces the ticket subject with a pseudonymous label, clears associations to contacts/companies, soft deletes ticket and message records, and redacts attachments. A sanitized aggregate snapshot (message counts, attachment totals, hashed subject) is persisted on the request for reporting, and `ticket.redacted` audit events/logs capture the correlation ID.

Filament exposes the workflow at `/admin/ticket-deletion-requests`, including approve/cancel actions with hold configuration, tenant/brand filters, and read-only detail views. Run `php artisan queue:work` to process approved requests asynchronously; tests process jobs synchronously for determinism.

## Ticket Lifecycle Broadcasting

Ticket lifecycle events are persisted, audited, and broadcast over Echo-compatible websockets so agent consoles can react in real time.

- `GET /api/v1/tickets` – list tenant-scoped tickets (requires `tickets.view`).
- `POST /api/v1/tickets` – create a ticket and emit a `ticket.created` event (requires `tickets.manage`).
- `GET /api/v1/tickets/{ticket}/events` – retrieve the lifecycle event history for a ticket.
- `POST /api/v1/tickets/{ticket}/events` – manually broadcast a lifecycle event with a custom payload (agents only).

All responses include `correlation_id` metadata and redact PII in logs via hashed digests. Manual testing is available via Filament at `/admin/ticket-events`, which respects tenant and brand scopes.
