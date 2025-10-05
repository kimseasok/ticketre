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
