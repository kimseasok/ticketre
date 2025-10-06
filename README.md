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

## Ticket Message Visibility & API

- Internal notes use the `visibility` flag on `messages` records. Agents and admins can create internal notes by sending `visibility="internal"`.
- Customer portal integrations should request `GET /api/v1/tickets/{ticket}/messages?audience=portal` to automatically filter out internal notes.
- Authenticated operators can create messages with `POST /api/v1/tickets/{ticket}/messages` using Sanctum tokens. Provide an optional `X-Correlation-ID` header for traceable JSON logs.
- Filament admin users can manage ticket messages under **Ticketing → Messages**, with tenant and brand scoping applied automatically.
- The REST contract for these endpoints is documented in `docs/OPENAPI.yaml`.

## Docker
- `make up` to start services (db, redis, meilisearch, queue, app, nginx)
- `make down` to stop

## Testing
- `make test`
- `make ci-check` to run lint, static analysis, and tests

> PHPUnit loads `.env.testing` to exercise the suite against in-memory SQLite, so `php artisan test` runs without external services.
