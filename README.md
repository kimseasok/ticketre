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

- `GET /api/v1/kb-articles` – list articles with optional `status`, `locale`, or `category_id` filters.
- `POST /api/v1/kb-articles` – create a draft or published article tied to a category; the authenticated user becomes the author unless overridden.
- `GET /api/v1/kb-articles/{id}` – retrieve an article with category and author context.
- `PATCH /api/v1/kb-articles/{id}` – update metadata, status, or category assignments.
- `DELETE /api/v1/kb-articles/{id}` – soft delete the article while retaining audit history.

Audit logs capture every create, update, and delete operation with hashed payload digests for observability, and structured JSON logs include correlation IDs by default. Filament exposes the same functionality via `/admin/kb-categories` and `/admin/kb-articles`, featuring tenant/brand scoped queries, validation rules, and soft-delete management. Demo data is seeded through `DemoDataSeeder` for NON-PRODUCTION environments only.

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

## Ticket Lifecycle Broadcasting

Ticket lifecycle events are persisted, audited, and broadcast over Echo-compatible websockets so agent consoles can react in real time.

- `GET /api/v1/tickets` – list tenant-scoped tickets (requires `tickets.view`).
- `POST /api/v1/tickets` – create a ticket and emit a `ticket.created` event (requires `tickets.manage`).
- `GET /api/v1/tickets/{ticket}/events` – retrieve the lifecycle event history for a ticket.
- `POST /api/v1/tickets/{ticket}/events` – manually broadcast a lifecycle event with a custom payload (agents only).

All responses include `correlation_id` metadata and redact PII in logs via hashed digests. Manual testing is available via Filament at `/admin/ticket-events`, which respects tenant and brand scopes.
