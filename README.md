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

## CI Quality Gates & Pipeline

- **GitHub Actions** – `.github/workflows/ci.yml` now runs linting, static analysis, matrix tests (PHP 8.3/8.4 across MySQL and PostgreSQL), dependency audits, and a Docker image build. Composer/npm caches and buildx layer caching keep the workflow fast, and every failure uploads structured logs plus test artifacts. Optional Slack webhooks (`SLACK_WEBHOOK_URL`) receive failure summaries with correlation IDs when configured.
- **Coverage enforcement** – The `coverage` job generates `coverage/clover.xml` and runs `php artisan ci:enforce-quality-gate --source=coverage/clover.xml` to abort the pipeline when thresholds drop below tenant-configured minimums or when dependency scans exceed allowed critical/high counts.
- **Artisan command** – `php artisan ci:enforce-quality-gate --gate=<slug> --tenant=<tenant-slug> --brand=<brand-slug> --coverage=92 --critical=0 --high=1` evaluates a specific gate. Pass `--source` with a Clover XML file to compute coverage automatically. The command emits JSON logs with hashed notification channels and correlation IDs.
- **API** – Manage CI quality gates via `GET/POST /api/v1/ci-quality-gates` and `GET/PATCH/DELETE /api/v1/ci-quality-gates/{id}` (permissions: `ci.quality_gates.view` / `ci.quality_gates.manage`). Requests accept optional `metadata`, hashed notification channel hints, and correlation IDs; responses include digests instead of raw channels.
- **Filament UI** – `/admin/ci-quality-gates` offers tenant/brand scoped CRUD with coverage/vulnerability fields, toggleable enforcement options, and NON-PRODUCTION operator notes.

## Observability Pipelines

- **API** – `GET /api/v1/observability-pipelines` (view) and `POST /api/v1/observability-pipelines` (manage) expose tenant/brand scoped pipeline records, while `GET/PATCH/DELETE /api/v1/observability-pipelines/{id}` manage individual resources. Permissions: `observability.pipelines.view` for read access, `observability.pipelines.manage` for write operations. Payloads capture pipeline type (`logs`, `metrics`, or `traces`), ingest endpoint, buffering/retry policies, and optional metadata. Responses include hashed endpoint digests to avoid leaking PII, and all errors follow `{ "error": { "code", "message" } }`. PATCH requests default the metrics scrape interval to the current value so operators can tweak other fields without resubmitting metrics cadence.
- **Metrics endpoint** – `GET /api/v1/observability-pipelines/metrics` returns Prometheus-compatible counters, gauges, and summaries tagged with `tenant_id`, `brand_id` (or `unscoped`), pipeline type, and operation. Authenticate with `platform.access` + `observability.pipelines.view`, and supply tenant headers: `X-Tenant` and optional `X-Brand`. Unauthorized requests receive the standard `{ "error": { "code": "ERR_HTTP_403", "message": "…" } }` payload while still logging the denial event.
- **Structured logging** – Create/update/delete operations emit JSON logs to the default channel with correlation IDs, digested endpoints, and performance timings. The service also records audit log entries with hashed fields for traceability.
- **Filament UI** – `/admin/observability-pipelines` provides CRUD forms with brand filters, pipeline type badges, and helper text marking NON-PRODUCTION operator guidance. Metrics-specific fields surface only when the pipeline type is `metrics`, and metadata is editable via key/value inputs.
- **Demo data** – `DemoDataSeeder` provisions a NON-PRODUCTION logging pipeline tied to the demo tenant/brand so administrators can explore observability features locally without contacting external systems.

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

Rich-text translations are sanitized against a strict HTML allow list before persistence. Disallowed tags, event attributes, and `javascript:` protocols are stripped, the cleaned payload is stored, and a `kb_article.sanitization_blocked` audit entry captures hashed digests plus a redacted preview when changes occur. Structured JSON logs include the same correlation ID that arrives in the request headers so investigations can follow the trail without exposing raw content.

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

## Permission Registry

Tenant permissions build on the Spatie package but add tenant/brand awareness, audit logging, and observability hooks:

- `GET /api/v1/permissions` – list permissions for the active tenant (requires `permissions.view`). Supports optional `search` query for name/slug matches and a `brand` query parameter (`brand=<brand-id>` or `brand=global`) to filter brand-specific definitions.
- `POST /api/v1/permissions` – create a custom permission (requires `permissions.manage`). Requests accept `name`, optional `description`, and optional `brand_id` to scope the ability to a brand. Leaving `brand_id` empty creates a tenant-wide permission available to all brands.
- `GET /api/v1/permissions/{permission}` – return a single permission with brand context and system flag metadata.
- `PATCH /api/v1/permissions/{permission}` – update description or brand scope (requires `permissions.manage`). System permissions retain their name and tenant binding.
- `DELETE /api/v1/permissions/{permission}` – remove custom permissions. System records respond with `ERR_PERMISSION_PROTECTED` and status `422`.

Responses follow the existing `{ "data": { ... } }` envelope; validation/auth errors return `{ "error": { "code", "message", "details" } }` and include the upstream correlation ID. Every create/update/delete operation records a `permission.*` audit log entry with hashed fields, emits a structured JSON log containing the correlation ID, and flushes the tenant-aware Spatie cache to keep role assignments in sync.

Filament exposes the same CRUD operations at `/admin/permissions`. The resource filters by the resolved tenant, offers a brand picker (including “All brands”), disables destructive actions for system permissions, and eager loads brand relationships to avoid N+1 queries.

## Two-Factor Authentication

Platform administrators and agents must maintain an active TOTP profile before interacting with privileged APIs. The tenant security policy defaults to enforcing two-factor for `Admin` and `Agent` roles; viewer-only accounts remain exempt.

- **Enrollment** – `POST /api/v1/two-factor/enroll` provisions an encrypted secret and returns an `otpauth://` URI suitable for QR display. Confirm the pairing with `POST /api/v1/two-factor/confirm` by supplying a 6-digit TOTP from the authenticator. Successful confirmation returns a fresh set of one-time recovery codes.
- **Challenge** – Each authenticated session must call `POST /api/v1/two-factor/challenge` with either a current TOTP or unused recovery code. Successful challenges store a tenant-scoped session token for the configured TTL (30 minutes by default) and allow access to other `/api/v1/*` routes guarded by `platform.access`.
- **Recovery maintenance** – `POST /api/v1/two-factor/recovery-codes` regenerates recovery codes after they are consumed. All responses follow the `{ "data": { ... }, "meta": { ... } }` envelope and include `X-Correlation-ID` headers for observability.

If an account fails too many challenges (`config('security.two_factor.max_attempts')`, default `5`), the credential locks and the API responds with `ERR_2FA_LOCKED` until an administrator resets the lock via Filament.

### Filament operations

Visit `/admin/two-factor-credentials` to review tenant-scoped credentials. Admins can:

- Start enrollment on behalf of a user (the generated secret is displayed in a persistent notification for secure handoff).
- View status, last verification timestamps, and remaining recovery codes.
- Unlock a credential directly from the edit screen after a lockout event.

All actions emit structured JSON logs with the request correlation ID and write `two_factor.*` audit events that hash sensitive fields (secrets and recovery codes never appear in plaintext). Tenant scoping is enforced automatically and brand filters are available when multiple brands exist.

### Artisan helpers

The Spatie commands remain available for operational tasks and now respect the tenant bindings configured in this project:

```bash
php artisan permission:create-permission "reports.download"
php artisan permission:create-role "qa.auditor"
php artisan permission:show
```

Run the commands with `--guard=web` to match the default guard. When scoped to a tenant, ensure the `currentTenant` helper is registered (the HTTP middleware does this automatically) or provide a `tenant_id` when seeding via factories.

### RBAC Middleware Enforcement

- `platform.access` – baseline permission provisioned to Admin/Agent/Viewer roles. Required to reach any authenticated admin API (`/api/v1/*`) or Filament panel route.
- `portal.submit` – permission assigned to tenant roles for authenticated submissions. Guest access to customer portal routes remains available, but authenticated users must hold this permission.

The `EnsureTenantAccess` middleware protects both admin and portal surfaces:

- Verifies the authenticated user's tenant and brand match the resolved request context to prevent header spoofing across tenants.
- Checks for the configured Spatie permissions and emits `rbac.denied` structured logs with correlation IDs, hashed identifiers, and denial reason metadata.
- Automatically appends an `X-Correlation-ID` header to every response (success or error) to align API and view troubleshooting.
- Returns JSON errors in the `{ "error": { "code", "message", "correlation_id" } }` schema for API requests and a stylised `errors/403` view for web requests.

Portal routes (`/portal/*` and `/api/v1/portal/*`) continue to resolve tenants via headers while logging denied authenticated attempts. Admin APIs require `X-Tenant` (and optional `X-Brand`) headers; Filament relies on the authenticated user's tenant to scope data automatically.
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

### Contact & Company Directory

- Manage tenant-scoped companies and contacts via Filament under **CRM**. Companies support tag-based filtering and optional brand scoping, while contacts capture GDPR marketing/data processing consent flags.
- API endpoints:
  - `GET /api/v1/companies`, `POST /api/v1/companies`, `GET /api/v1/companies/{company}`, `PATCH /api/v1/companies/{company}`, `DELETE /api/v1/companies/{company}`
  - `GET /api/v1/contacts`, `POST /api/v1/contacts`, `GET /api/v1/contacts/{contact}`, `PATCH /api/v1/contacts/{contact}`, `DELETE /api/v1/contacts/{contact}`
- All responses include structured JSON:API payloads and enforce tenant plus optional brand scope derived from `X-Tenant`/`X-Brand` headers.
- Unique contact emails are enforced per tenant, and consent toggles are required when creating or updating contacts. Structured audit logs emit correlation-aware entries for create/update/delete operations.

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

## GDPR Anonymization Policies

Codify the fields to anonymize or delete when processing GDPR subject requests. Policies are tenant-scoped, optionally brand-scoped, and enforce RBAC via the new `compliance.policies.view`/`compliance.policies.manage` permissions (Admins manage, Agents/Viewers read).

- `GET /api/v1/anonymization-policies` – list policies for the active tenant/brand with optional `status`/`brand_id` filters (requires `compliance.policies.view`).
- `POST /api/v1/anonymization-policies` – create or approve a policy definition, including retention notes and subject request procedures (requires `compliance.policies.manage`).
- `GET /api/v1/anonymization-policies/{policy}` – retrieve a single policy including approval metadata.
- `PATCH /api/v1/anonymization-policies/{policy}` – update fields, flip approval state, or adjust brand scope (requires `compliance.policies.manage`).
- `DELETE /api/v1/anonymization-policies/{policy}` – retire a policy while preserving audit history (requires `compliance.policies.manage`).

All API requests require the standard tenant headers and follow the `{ "data": { ... } }` / `{ "error": { ... } }` contract. Structured JSON logs include the supplied `X-Correlation-ID` (or generated UUID) and redact sensitive notes by hashing text fields.

Filament administrators can manage the same records via `/admin/anonymization-policies`, with status and brand filters plus tags-based editors for the anonymize/delete field lists. Demo data seeds a NON-PRODUCTION approved policy for manual exploration.

## Ticket Deletion & Redaction Workflow

## Team Operations & Memberships

Coordinate support staff into tenant-scoped teams with RBAC, audit logging, and structured observability.

- `GET /api/v1/teams` – paginate teams for the active tenant/brand. Requires `teams.view`.
- `POST /api/v1/teams` – create a team with optional `brand_id`, `default_queue`, and `description`. Requires `teams.manage`. Slugs are unique per tenant.
- `GET /api/v1/teams/{team}` – retrieve a single team with eager-loaded memberships and counts.
- `PATCH /api/v1/teams/{team}` – update metadata safely; validation enforces tenant scope and unique slugs.
- `DELETE /api/v1/teams/{team}` – soft delete a team while retaining audit history.
- `GET /api/v1/teams/{team}/memberships` – list team memberships with user context. Requires `teams.view`.
- `POST /api/v1/teams/{team}/memberships` – attach a user with a `role` (`lead`, `member`), primary flag, and optional `joined_at`. Requires `teams.manage`.
- `PATCH /api/v1/teams/{team}/memberships/{membership}` – update membership role/flags.
- `DELETE /api/v1/teams/{team}/memberships/{membership}` – detach a member; soft deletes allow re-adding users later.

All endpoints require the standard tenant headers and respond with the `{ "data": { ... } }` envelope while emitting `{ "error": { "code", "message" } }` on failure. Structured JSON logs capture `team.*` and `team.membership.*` events with hashed names, correlation IDs (from `X-Correlation-ID` or generated UUIDs), and execution timings. Audit entries persist snapshots for compliance, redacting PII via SHA-256 digests.

Filament exposes teams at `/admin/teams` with brand filters, inline membership management, and relation managers for attaching users. The demo seeder provisions NON-PRODUCTION Tier 1 and VIP teams for exploration.

Tickets that contain personal data can be purged while preserving operational analytics via the queued deletion workflow. The new `tickets.redact` permission is provisioned for tenant Admins and governs both the API and Filament surfaces.

- `GET /api/v1/ticket-deletion-requests` – list requests scoped to the active tenant/brand with optional `status`, `ticket_id`, and `brand_id` filters.
- `POST /api/v1/ticket-deletion-requests` – register a deletion request, capture the legal reason, and assign a correlation ID for observability.
- `POST /api/v1/ticket-deletion-requests/{id}/approve` – approve a request with a reversible hold window (0–168 hours). Approval enqueues the background processor.
- `POST /api/v1/ticket-deletion-requests/{id}/cancel` – cancel a pending or approved request before the hold expires.
- `GET /api/v1/ticket-deletion-requests/{id}` – inspect status, hold timers, aggregate metrics, and requester/approver metadata.

Processing replaces the ticket subject with a pseudonymous label, clears associations to contacts/companies, soft deletes ticket and message records, and redacts attachments. A sanitized aggregate snapshot (message counts, attachment totals, hashed subject) is persisted on the request for reporting, and `ticket.redacted` audit events/logs capture the correlation ID.

Filament exposes the workflow at `/admin/ticket-deletion-requests`, including approve/cancel actions with hold configuration, tenant/brand filters, and read-only detail views. Run `php artisan queue:work` to process approved requests asynchronously; tests process jobs synchronously for determinism.

## Ticket Relationship Metadata

Track merges, splits, and duplicate links between tickets while keeping tenancy, RBAC, and observability consistent across surfaces.

- `GET /api/v1/ticket-relationships` – list relationships for the active tenant/brand. Supports optional `relationship_type`, `primary_ticket_id`, `related_ticket_id`, and pagination parameters. Requires `tickets.relationships.view` (seeded for Admin/Agent/Viewer).
- `POST /api/v1/ticket-relationships` – create a new relationship with `primary_ticket_id`, `related_ticket_id`, `relationship_type` (`merge`, `split`, or `duplicate`), optional `context` metadata, and an optional `correlation_id`. Requires `tickets.relationships.manage` (or `tickets.manage`). Requests reject cross-brand pairs, self-references, duplicate/inverse pairs, and merge cycles.
- `GET /api/v1/ticket-relationships/{id}` – return a single relationship with ticket + creator context.
- `PATCH /api/v1/ticket-relationships/{id}` – update the relationship type or context metadata. Context values are trimmed to 255 characters and logged by key only to avoid PII exposure.
- `DELETE /api/v1/ticket-relationships/{id}` – delete a relationship with full audit + structured log coverage.

All responses follow the `{ "data": { ... } }` envelope and include the stored `correlation_id`. Errors use `{ "error": { "code", "message" } }` and provide validation details when applicable. Provide the standard tenant headers:

```http
X-Tenant: <tenant-slug>
X-Brand: <brand-slug>
```

Structured JSON logs emit `ticket.relationship.*` events with correlation IDs, hashed context key lists, and execution timings. Each create/update/delete also writes `ticket.relationship.*` audit entries with tenant/brand scope for traceability. The demo seeder provisions a NON-PRODUCTION duplicate relationship to explore via Filament at `/admin/ticket-relationships`, which offers type and brand filters plus scoped CRUD actions using the shared service layer.

## Ticket Workflows & SLA Enforcement

Model configurable ticket workflows per tenant/brand and enforce transitions directly in the ticket service layer.

- `GET /api/v1/ticket-workflows` – list workflows for the active tenant/brand (requires `tickets.workflows.view`). Responses include state/transition definitions.
- `POST /api/v1/ticket-workflows` – create workflows with nested state + transition definitions. Requests validate uniqueness per tenant/brand and return `ERR_VALIDATION` on conflict.
- `GET /api/v1/ticket-workflows/{id}` – inspect a single workflow.
- `PATCH /api/v1/ticket-workflows/{id}` – update metadata, states, and transitions idempotently.
- `DELETE /api/v1/ticket-workflows/{id}` – soft delete a workflow; tickets automatically fall back to the next available default.

`TicketService` validates requested `workflow_state` changes against configured transitions, invokes optional guard/entry hooks, recalculates SLA timers when states define `sla_minutes`, writes `ticket.workflow.transitioned` audit entries, and emits structured lifecycle events. Invalid transitions return `422 ERR_VALIDATION` with the standard error envelope. Filament surfaces full CRUD management at `/admin/ticket-workflows`, including brand filters, default toggles, and NON-PRODUCTION repeaters for state/transition definitions.

## Ticket Lifecycle Broadcasting

Ticket lifecycle events are persisted, audited, and broadcast over Echo-compatible websockets so agent consoles can react in real time.

- `GET /api/v1/tickets` – list tenant-scoped tickets (requires `tickets.view`).
- `POST /api/v1/tickets` – create a ticket and emit a `ticket.created` event (requires `tickets.manage`).
- `GET /api/v1/tickets/{ticket}/events` – retrieve the lifecycle event history for a ticket.
- `POST /api/v1/tickets/{ticket}/events` – manually broadcast a lifecycle event with a custom payload (agents only).

The ticket creation endpoint accepts authenticated requests with the standard `X-Tenant` and optional `X-Brand` headers. Provide
an `X-Correlation-ID` header (max 64 chars) to tie API calls to structured logs; a UUID is generated when omitted. The JSON reques
t body must include `subject`, `status`, and `priority`, and may include tenant-scoped foreign keys plus a `metadata` object and
`custom_fields` collection:

```json
{
  "subject": "Customer cannot log in",
  "status": "open",
  "priority": "high",
  "contact_id": 123,
  "metadata": {"source": "api", "tags": ["vip"]},
  "custom_fields": [
    {"key": "order_id", "type": "string", "value": "INV-1001"},
    {"key": "urgent", "type": "boolean", "value": true}
  ]
}
```

Custom field keys must be unique per request (case-insensitive), limited to 64 characters, and the `type` must be one of `string`
, `number`, `boolean`, `date`, or `json`. Values are automatically normalised (numbers are cast, booleans coerce `"true"/"false"`
, dates resolve to ISO-8601 strings, JSON accepts arrays/objects). Validation errors return `422` with `ERR_VALIDATION` plus field
-specific messages. Related IDs (`contact_id`, `company_id`, `assignee_id`) are tenant-scoped to prevent cross-tenant leakage.

Successful responses follow a JSON:API style envelope:

```json
{
  "data": {
    "type": "tickets",
    "id": "142",
    "attributes": {
      "subject": "Customer cannot log in",
      "status": "open",
      "priority": "high",
      "channel": "api",
      "metadata": {"source": "api", "tags": ["vip"]},
      "custom_fields": [
        {"key": "order_id", "type": "string", "value": "INV-1001"},
        {"key": "urgent", "type": "boolean", "value": true}
      ],
      "created_at": "2025-10-07T12:45:30Z"
    },
    "relationships": {
      "assignee": {"data": null},
      "contact": {"data": {"type": "contacts", "id": "123", "attributes": {"name": "Acme CTO"}}}
    },
    "links": {
      "self": "https://api.example.com/api/v1/tickets/142",
      "messages": "https://api.example.com/api/v1/tickets/142/messages",
      "events": "https://api.example.com/api/v1/tickets/142/events"
    }
  }
}
```

Structured logs emit `ticket.api.created` with hashed subject digests, the supplied correlation ID, tenant/brand identifiers, and
the custom field count. When the request originates from Filament or another internal surface the channel defaults to `agent` and
the specialised API log entry is skipped.

All responses include `correlation_id` metadata and redact PII in logs via hashed digests. Manual testing is available via Filament at `/admin/ticket-events`, which respects tenant and brand scopes.

### Ticket Merge Workflow

Merge duplicate tickets without losing historical context. The `tickets.merge` permission is provisioned for Admin and Agent roles; viewers cannot initiate merges.

**API**

- `GET /api/v1/ticket-merges` – paginate historical merges with optional `status`, `primary_ticket_id`, and `brand_id` filters. Responses include summary metrics plus eager-loaded ticket snapshots.
- `POST /api/v1/ticket-merges` – merge two tickets by supplying `primary_ticket_id`, `secondary_ticket_id`, and an optional `correlation_id`. Self-merges, cross-tenant IDs, and cross-brand pairs return `422 ERR_VALIDATION`.
- `GET /api/v1/ticket-merges/{id}` – inspect a single merge, including counts of migrated messages/events/attachments and the involved tickets.

All requests require the tenant headers:

```http
X-Tenant: <tenant-slug>
X-Brand: <brand-slug>
```

Provide an `X-Correlation-ID` (≤64 chars) to propagate the identifier into structured logs; the service generates a UUID when omitted. Successful responses follow the `{ "data": { ... } }` envelope, surface merge summaries under `attributes.summary`, and expose relationships to the primary/secondary tickets and initiator.

**Filament**

- `/admin/ticket-merges` offers a scoped creator and detail view with tenant/brand filters plus ticket pickers that honour the active brand.

**Observability & Safeguards**

- Structured logs emit `ticket.merge.created`, `.completed`, and `.failed` with tenant/brand IDs, correlation IDs, hashed failure reasons, and execution timings.
- Audit logs capture created/completed/failed states with snapshot payloads; failure states persist timestamps and sanitized reasons on the merge record.
- Merge executions automatically register `merge` ticket relationships for downstream analytics, and the demo seeder provisions a NON-PRODUCTION merge for exploration.

### Echo Broadcasting Stack

The realtime stack now exposes secure authentication, connection health monitoring, and admin tooling for Laravel Echo / Pusher-compatible consumers.

- **Environment toggles** – set `ECHO_ENABLED=true` alongside the `PUSHER_*` credentials to switch the broadcast driver from the default log channel to `pusher`. Optional flags `PUSHER_FORCE_TLS`, `PUSHER_ENCRYPTED`, and `PUSHER_CLIENT_TIMEOUT` control TLS enforcement and client timeouts.
- **Auth endpoint** – clients authenticate private and presence channels via `POST /api/v1/broadcasting/auth` (guards `auth:api,web`, `tenant`). Requests must include `channel_name`, `socket_id`, and the standard tenant headers; responses return the signed auth payload plus the `X-Correlation-ID` that is also written to structured logs.
- **Connection monitoring API** – `/api/v1/broadcast-connections` supports full CRUD with tenant/brand scoping, RBAC (`broadcast_connections.view` / `broadcast_connections.manage`), and correlation-aware responses. Payloads capture `connection_id`, `channel_name`, latency metrics, and anonymised metadata.
- **Filament UI** – administrators can inspect and manage connections at `/admin/broadcast-connections`, with status/brand filters and operations funneled through the same audit-logged service layer.
- **Observability** – all API and auth flows emit JSON logs (`broadcast_connection.*`, `broadcast.auth.*`) with hashed socket identifiers, tenant/brand identifiers, and correlation IDs. Audit entries record metadata key snapshots without persisting raw socket identifiers.
