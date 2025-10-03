# Changelog

## [Unreleased]
### Fixed
- Declare the application as an ES module so Vite can import `laravel-vite-plugin` during CI builds without require() failures.
- Provide a committed `.env.testing` profile so `php artisan test` can run against SQLite during CI without requiring MySQL.
