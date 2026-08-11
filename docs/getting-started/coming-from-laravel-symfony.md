---
sidebar_position: 15
title: Coming from Laravel, Symfony or Drupal
---

# Coming from Laravel, Symfony or Drupal

Gluo is a REST API starter, not a full framework. Most of what you know transfers —
PSR-4, Composer, PHPUnit, controllers, repositories, services, a DI container. This
page maps the vocabulary and calls out the handful of places where Gluo genuinely
works differently, so you do not lose an afternoon to a surprise.

If you only read one section, read **The router is generated** below. It is the single
most common source of "why does my endpoint 404".

## The four real differences

### 1. The router is generated — attributes are the source, JSON is the truth

Routes are not read from your PHP classes at runtime. `public/app.php` hands
`OpenApiRouteList` the file `public/docs/openapi.json`, and *that* file is the routing
table. It is produced from the `#[OA\...]` attributes on your controllers.

```php
#[OA\Get(path: "/project/{id}", tags: ["Project"])]
#[RequireAuthenticated]
public function getProject(HttpResponse $response, HttpRequest $request): void
```

So adding or changing an endpoint is a two-step operation:

```bash
# 1. edit the controller attributes
# 2. rebuild the routing table
composer openapi
```

Skip step 2 and the endpoint returns **404 with no error** — the route simply does not
exist yet. To catch this, `composer test` runs a freshness check first, and you can run
it any time:

```bash
composer openapi:check
```

It warns (never blocks) when the spec is older than anything in `src/Controller/` or
`src/Model/`.

> **Laravel:** there is no `routes/api.php`. **Symfony:** it resembles `#[Route]`, except
> the "cache rebuild" is explicit and you own it. **Drupal:** it plays the role of
> `mymodule.routing.yml`, except you never edit it by hand.

### 2. Dependencies are pulled, not injected

Controllers are plain classes with no constructor. You fetch collaborators from the
container inside the method:

```php
public function getProject(HttpResponse $response, HttpRequest $request): void
{
    $projectService = Config::get(ProjectService::class);
    $response->write($projectService->getOrFail($request->attribute('id')));
}
```

Bindings are declared explicitly — there is no autowire-by-convention:

```php
// config/dev/05-services.php
ProjectService::class => DI::bind(ProjectService::class)
    ->withInjectedConstructor()
    ->toSingleton(),
```

`config/test/` inherits `config/dev/`, so you register a service once for both.

> **Drupal:** this is `\Drupal::service()` — you will feel at home. **Symfony/Laravel:**
> this is the adjustment. Expect a service locator where you are used to autowiring or
> auto-resolved type hints.

### 3. Controllers write to a response, they do not return one

The signature is fixed and the return type is `void`:

```php
public function listProject(HttpResponse $response, HttpRequest $request): void
{
    $response->write($result);   // arrays and objects are serialized for you
}
```

No `return response()->json(...)`, no `return new JsonResponse(...)`, no render arrays.

### 4. The database comes first, and migrations are plain SQL

The code generator reads your **existing table** and writes the PHP:

```bash
composer codegen -- --env=dev --table=product all --save
```

That emits the model, repository, service, controller, functional test and DI bindings.
Migrations are hand-written numbered SQL pairs — you write both directions:

```
db/migrations/up/00002-create-products.sql
db/migrations/down/00001-rollback-products.sql
```

> **Laravel:** the direction is reversed. You are used to model → migration → table; here
> it is table → codegen → model. There is no schema builder. **Symfony:** closer to
> `doctrine:mapping:import` than to `make:entity`. **Drupal:** replaces `hook_schema()`
> and `hook_update_N()`.

## Vocabulary

| Laravel | Symfony | Drupal | Gluo |
|---|---|---|---|
| `routes/api.php` | `#[Route]` | `*.routing.yml` | `#[OA\Get]` + `composer openapi` |
| `app/Models` | `src/Entity` | Entity API | `src/Model` + `#[TableAttribute]` |
| `app/Http/Controllers` | `src/Controller` | Controller plugin | `src/Controller` |
| Eloquent | Doctrine ORM | Entity/Field API | `byjg/micro-orm` |
| — (Eloquent is the repo) | `src/Repository` | `EntityStorage` | `src/Repository` |
| `database/migrations` | `migrations/` | `hook_update_N` | `db/migrations/{up,down}` |
| `config/*.php` | `config/services.yaml` | `*.services.yml` | `config/{env}/*.php` |
| `.env` | `.env` | `settings.php` | `config/{env}/credentials.env` |
| `app()->make()` | autowiring | `\Drupal::service()` | `Config::get()` |
| Gate / Policy | `#[IsGranted]` | `_permission` route key | `#[RequireRole]` |
| FormRequest | Validator + DTO | Form API | `#[ValidateRequest]` + OpenAPI schema |
| `php artisan tinker` | — | `drush php` | `composer terminal` |

## Commands

| Laravel | Symfony | Gluo |
|---|---|---|
| `php artisan serve` | `symfony serve` | `docker compose up -d` |
| `php artisan migrate` | `doctrine:migrations:migrate` | `composer migrate -- --env=dev update` |
| `php artisan migrate:fresh` | `doctrine:schema:drop --force` | `composer migrate -- --env=dev reset` |
| `php artisan make:model -mcr` | `make:entity` / `make:crud` | `composer codegen -- --table=x all --save` |
| `php artisan test` | `bin/phpunit` | `composer test` |
| — | `cache:clear` | `composer openapi` |
| Pint / PHPStan | PHPStan | `composer psalm` |

## Where you are already at home

- **Layout.** The repository root *is* the PHP application root — one `composer.json`,
  one `vendor/`, with `src/`, `config/`, `db/`, `public/`, `tests/` beside it. See
  [Repository layout](../concepts/repo-layout.md).
- **Layering.** Controller → Service → Repository → Model is the Symfony playbook. Simple
  CRUD can skip the service via the ActiveRecord pattern — see
  [Architecture Decisions](../concepts/architecture.md).
- **Authorisation attributes.** `#[RequireAuthenticated]` and `#[RequireRole('admin')]`
  behave like Symfony's `#[IsGranted]`.
- **Testing.** Functional tests run against the OpenAPI contract, so a response that
  drifts from the documented schema fails the test. See [Testing](../guides/testing.md).

## What is not included

Gluo is deliberately small. There is no queue/job system, no event dispatcher, no
scheduler, and no template layer for HTML (email templates use jinja-php). If your
application needs those, you bring the library.

## Next

1. [Installation](installation.md) — create the project
2. [Your first table](first-table.md) — migration then codegen
3. [Your first endpoint](first-endpoint.md) — attributes, and the regeneration step
