# main-api — Implementation Plan

Laravel 11 (PHP 8.3) API responsible for users, authentication, and acting as the
client-facing facade in front of the wiki-service.

## Stack

- PHP 8.3 (pinned via `.php-version`)
- Laravel 11.x
- Laravel Sanctum (token auth)
- MySQL 8 (database `main_api`)
- Guzzle (already bundled in Laravel HTTP client) for service-to-service calls
- PHPUnit / Pest for tests
- Docker (php-fpm + nginx)

## Responsibilities

- Register/login/logout users
- Issue & validate Sanctum tokens
- Forward "fetch articles" job requests to wiki-service
- Proxy reads of jobs and articles from wiki-service, scoped to current user
- Never read wiki-service tables directly

## Database (`main_api`)

- `users` (Laravel default): id, name, email, password
- `personal_access_tokens` (Sanctum default)
- `job_references`:
  - `id`, `user_id` (FK), `external_job_id` (string, from wiki-service),
    `status` (enum: pending|running|done|failed), `created_at`, `updated_at`

## Configuration

`config/database.php` — single connection `mysql_main`:
```
DB_CONNECTION=mysql_main
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=main_api
DB_USERNAME=app
DB_PASSWORD=secret
```

`config/services.php`:
```php
'wiki_service' => [
    'base_url' => env('WIKI_SERVICE_URL', 'http://wiki-service'),
    'shared_secret' => env('WIKI_SERVICE_SECRET'),
],
```

## API endpoints

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/api/register` | guest | Create user, return token |
| POST | `/api/login` | guest | Issue token |
| POST | `/api/logout` | sanctum | Revoke current token |
| GET  | `/api/me` | sanctum | Current user |
| POST | `/api/articles/fetch-job` | sanctum | Dispatch job via wiki-service |
| GET  | `/api/jobs/{id}` | sanctum | Status of a job (own only) |
| GET  | `/api/articles` | sanctum | List articles for current user |
| GET  | `/api/articles/{id}` | sanctum | Single article (own only) |

## Service-to-service auth

Outbound HTTP calls add header:
```
X-Internal-Secret: <WIKI_SERVICE_SECRET>
X-User-Id: <auth user id>
```
Wiki-service validates the secret and uses `X-User-Id` to scope data.

## Layout (key files)

```
app/
  Http/
    Controllers/
      Auth/{RegisterController,LoginController,LogoutController,MeController}.php
      ArticlesController.php
      JobsController.php
    Requests/
      Auth/{RegisterRequest,LoginRequest}.php
    Resources/
      UserResource.php
      ArticleResource.php
      JobResource.php
  Models/
    User.php
    JobReference.php
  Services/
    WikiServiceClient.php   # wraps Http::withHeaders()
routes/api.php
database/migrations/*_create_job_references_table.php
config/services.php
tests/Feature/Auth/*.php
tests/Feature/ArticlesProxyTest.php
docker/{Dockerfile,nginx.conf,php.ini}
```

## WikiServiceClient (sketch)

```php
public function dispatchFetchJob(int $userId): array {
    return Http::baseUrl($this->baseUrl)
        ->withHeaders($this->headers($userId))
        ->post('/internal/jobs/fetch')
        ->throw()->json();
}
public function getJob(int $userId, string $jobId): array { ... }
public function listArticles(int $userId, int $page): array { ... }
public function getArticle(int $userId, int $id): array { ... }
```

## Implementation steps

1. **Scaffold**: `composer create-project laravel/laravel main-api --prefer-dist`
2. **Pin PHP**: `.php-version` already set to 8.3; add `"php": "^8.3"` in composer.json
3. **Install Sanctum**: `composer require laravel/sanctum`, publish config, run migrations
4. **CORS**: enable for frontend origin in `config/cors.php`
5. **Auth controllers + requests + routes**
6. **JobReference model + migration**
7. **WikiServiceClient + ArticlesController + JobsController**
8. **Resources** for consistent response shapes
9. **Tests**:
   - Feature: register, login, logout, me
   - Feature: fetch-job dispatches and stores reference (mock HTTP)
   - Feature: articles proxy returns wiki-service payload
10. **Dockerfile**: php:8.3-fpm + nginx + composer
11. **README + .env.example**

## Done criteria

- `php artisan test` green
- `php artisan migrate` succeeds against `main_api` DB
- Manual curl flow against running stack works end-to-end
- No direct queries against `wiki_service` DB anywhere in code
