.PHONY: up down seed test lint pint stan reindex migrate logs ci-check reset

up:
docker-compose up -d --build

down:
docker-compose down

seed:
php artisan db:seed

test:
./vendor/bin/pest

lint:
npm run lint || true

pint:
./vendor/bin/pint

stan:
./vendor/bin/phpstan analyse

reindex:
php artisan scout:import "App\\Models\\Ticket"

migrate:
php artisan migrate --force

logs:
docker-compose logs -f

ci-check: pint stan test

reset:
php artisan migrate:fresh --seed
