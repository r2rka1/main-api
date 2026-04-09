# main-api

Laravel 11 API on **PHP 8.3**. Owns users + auth (Laravel Sanctum) and acts as
the client-facing facade in front of
[`wiki-service`](https://github.com/r2rka1/wiki-service).

Frontend client:
[`frontend-angular`](https://github.com/r2rka1/frontend-angular).

See [PLAN.md](./PLAN.md) for the full implementation plan.

## Prerequisites

- PHP **8.3** (`php -v` should report 8.3.x — pinned via `.php-version`)
- Composer
- A running [`wiki-service`](https://github.com/r2rka1/wiki-service) (for end-to-end article fetching)
- One of:
  - MySQL 8 reachable on `127.0.0.1:3306` with a `main_api` database, **or**
  - SQLite (set `DB_CONNECTION=sqlite` in `.env`)

> The shared MySQL host is also used by `wiki-service`, but the two services
> use different databases (`main_api` here, `wiki_service` there). `main-api`
> never reads `wiki_service` tables directly — it only calls wiki-service
> over HTTP.

## Setup

```bash
git clone git@github.com:r2rka1/main-api.git
cd main-api

composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set at minimum:

```dotenv
DB_DATABASE=main_api
DB_USERNAME=<your mysql user>
DB_PASSWORD=<your mysql password>

# Where wiki-service is running
WIKI_SERVICE_URL=http://localhost:8001
# This MUST match INTERNAL_SHARED_SECRET in wiki-service/.env
WIKI_SERVICE_SECRET=some-long-random-string

# Where the Angular dev server runs (CORS allow-list)
FRONTEND_URL=http://localhost:4200
```

If using MySQL, create the database first:

```bash
mysql -uroot -p -e "CREATE DATABASE main_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Then run migrations:

```bash
php artisan migrate
```

## Run locally

```bash
php artisan serve --port=8000
```

The API is now reachable at `http://localhost:8000`.

## Run tests

```bash
php artisan test
```

Tests use **sqlite in-memory** and `Http::fake()` to stub `wiki-service`, so
neither MySQL nor a running wiki-service is required for the test suite. You
should see 8 passing tests.

## Smoke test with curl

```bash
# Register
TOKEN=$(curl -s -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Ada","email":"ada@example.com","password":"password123","password_confirmation":"password123"}' \
  | jq -r .token)

# Who am I?
curl -s http://localhost:8000/api/me -H "Authorization: Bearer $TOKEN" | jq

# Dispatch a fetch job (requires wiki-service to be running)
curl -s -X POST http://localhost:8000/api/articles/fetch-job \
  -H "Authorization: Bearer $TOKEN" | jq

# List articles
curl -s http://localhost:8000/api/articles \
  -H "Authorization: Bearer $TOKEN" | jq
```

## API surface

| Method | Path                       | Auth     | Purpose                              |
|--------|----------------------------|----------|--------------------------------------|
| POST   | `/api/register`            | guest    | Create user, return token            |
| POST   | `/api/login`               | guest    | Issue token                          |
| POST   | `/api/logout`              | sanctum  | Revoke current token                 |
| GET    | `/api/me`                  | sanctum  | Current user                         |
| POST   | `/api/articles/fetch-job`  | sanctum  | Dispatch job via wiki-service        |
| GET    | `/api/jobs/{id}`           | sanctum  | Status of an owned job               |
| GET    | `/api/articles`            | sanctum  | List articles (proxied)              |
| GET    | `/api/articles/{id}`       | sanctum  | Article detail (proxied)             |

## PHP version

Pinned to **8.3** via `.php-version`. The wrapper installed in this dev
environment automatically picks `/usr/bin/php8.3` when invoked from this
directory.
