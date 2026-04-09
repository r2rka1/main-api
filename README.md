# main-api

Laravel 11 API on **PHP 8.3**. Owns users + auth (Sanctum) and acts as the
client-facing facade in front of [`wiki-service`](../wiki-service).

Frontend client: [`frontend-angular`](../frontend-angular).

See [PLAN.md](./PLAN.md) for the full implementation plan.

## Status

Scaffolding only — not yet implemented. Skills available under `.claude/skills/`.

## PHP version

Pinned to 8.3 via `.php-version`. The shell wrapper in this environment will
automatically pick `/usr/bin/php8.3` when invoked from this directory.

## Database

Uses MySQL database `main_api` on the shared MySQL instance also used by
`wiki-service` (which uses database `wiki_service`).

## Quick start (after implementation)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve   # http://localhost:8000
```
