# Changelog

## [Unreleased]
### Fixed
- Restore Pint compatibility and database credentials so CI linting and migrations succeed on MySQL and PostgreSQL.
- Declare the application as an ES module so Vite can import `laravel-vite-plugin` during CI builds without require() failures.
- Provide a committed `.env.testing` profile so `php artisan test` can run against SQLite during CI without requiring MySQL.
- Point PHPStan to the maintained Larastan extension file to prevent missing-include failures during linting.
