---
sidebar_position: 15
title: Coming from another PHP framework
---

# Coming from another PHP framework

Gluo is a REST API starter, not a full framework. Most of what you know transfers —
PSR-4, Composer, PHPUnit, controllers, repositories, services, a DI container. This
page maps the vocabulary of Laravel, Symfony, Drupal, CakePHP and CodeIgniter, and calls
out the handful of places where Gluo genuinely works differently, so you do not lose an
afternoon to a surprise.

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
./gluo openapi
```

Skip step 2 and the endpoint returns **404 with no error** — the route simply does not
exist yet. To catch this, `composer test` runs a freshness check first, and you can run
it any time:

```bash
./gluo openapi:check
```

It warns (never blocks) when the spec is older than anything in `src/Controller/` or
`src/Model/`.

> **Laravel:** there is no `routes/api.php`. **Symfony:** it resembles `#[Route]`, except
> the "cache rebuild" is explicit and you own it. **Drupal:** it plays the role of
> `mymodule.routing.yml`, except you never edit it by hand. **CakePHP:** it replaces
> `config/routes.php`. **CodeIgniter:** it replaces `app/Config/Routes.php`. As with
> auto-routing turned off (the default since 4.2), every route is declared, but the
> declaration lives on the controller method.

### 2. Autowire is a developer decision

Gluo does not impose one style. For each namespace you choose: let the container resolve
classes for you with a pattern rule, or accept only the bindings you declared. It is a
trade-off between performance and convenience. An explicit binding resolves with a plain
lookup. A class that is only matched by a rule costs a `class_exists()` check and a pattern
match on each resolution, and it hides the decisions an explicit binding would show.

Constructor injection works the way you expect. Controllers declare what they need and
the container provides it:

```php
class ProjectController
{
    public function __construct(protected ProjectService $projectService)
    {
    }

    public function getProject(HttpResponse $response, HttpRequest $request): void
    {
        $response->write($this->projectService->getOrFail($request->attribute('id')));
    }
}
```

The starter autowires controllers, because they are terminal classes with nothing to
decide: one implementation, named directly by the router, always per-request. One pattern
rule covers the namespace:

```php
// config/dev/07-controllers.php
'RestReferenceArchitecture\Controller\*' => Autowire::rule()
    ->withInjectedConstructor()   // resolve constructor args from their type hints
    ->toInstance(),               // per-request, not shared
```

Services and repositories are declared one by one, because those bindings carry
decisions a pattern cannot make: which implementation, singleton or not, scalar
constructor arguments:

```php
// config/dev/05-services.php
ProjectService::class => DI::bind(ProjectService::class)
    ->withInjectedConstructor()
    ->toSingleton(),
```

These are the starter's defaults, not rules. You can add an `Autowire::rule()` for any
other namespace, or remove the controller rule and bind each controller yourself. An
explicit binding always wins over a pattern, so you can also use a rule and still declare
the complex cases by hand. `./gluo codegen:all` writes the service and repository entries
for what it generates.

`config/test/` inherits `config/dev/`, so you register once for both.

> **Symfony:** the controller rule is your `App\Controller\: resource:` line; the
> difference is that the starter declares each service instead. **Laravel:** think
> explicit `$this->app->bind()` for services, with controllers resolved for you.
> **Drupal:** like `mymodule.services.yml`, except constructor arguments resolve from type
> hints and controllers need no entry. **CakePHP:** the same choice you make in
> `Application::services()`. With `league/container`, autowiring stays off until you
> delegate to a `ReflectionContainer`. **CodeIgniter:** there is no container. The
> `Config\Services` factories wire dependencies by hand, and here the container resolves
> them from type hints.

### 3. Controllers write to a response, they do not return one

The signature is fixed and the return type is `void`:

```php
public function listProject(HttpResponse $response, HttpRequest $request): void
{
    $response->write($result);   // arrays and objects are serialized for you
}
```

No `return response()->json(...)`, no `return new JsonResponse(...)`, no render arrays,
no `viewBuilder()->setOption('serialize', ...)`, no `return $this->response->setJSON(...)`.

### 4. The database comes first, and migrations are plain SQL

The code generator reads your **existing table** and writes the PHP:

```bash
./gluo --env=dev codegen:all --table=product --save
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
> and `hook_update_N()`. **CakePHP:** the direction will feel familiar, because
> `bin/cake bake all` also starts from an existing table. The difference is the
> migrations: SQL files instead of `config/Migrations` classes. **CodeIgniter:**
> `make:scaffold` creates empty classes, while codegen reads the table and fills them in.
> Migrations are SQL files instead of Forge classes.

## Persistence: Repository or ActiveRecord, per entity

`byjg/micro-orm` implements the three patterns you already know: **Data Mapper**,
**Repository** and **ActiveRecord**. The mapper is built from the attributes on the model
(`#[TableAttribute]`, `#[FieldAttribute]`), and both styles use it. You pick a style
**per entity**, not per project:

- **Repository + Data Mapper:** Controller → Service → Repository → Model. The model is a
  plain object, and the repository owns the queries. Use it when there is business logic.
- **ActiveRecord:** Controller → Model. The model uses the `ActiveRecord` trait and saves
  itself, with no service or repository class. Use it for simple CRUD.

| You are used to | Pattern | Closest Gluo style |
|---|---|---|
| Laravel — Eloquent | ActiveRecord | ActiveRecord |
| Symfony — Doctrine | Data Mapper + Repository | Repository + Data Mapper |
| Drupal — Entity API + `EntityStorage` | Repository | Repository + Data Mapper |
| CakePHP — `Table` + `Entity` | Repository (Table) + Entity | Repository + Data Mapper |
| CodeIgniter — `Model` | Table gateway + Query Builder | ActiveRecord, Repository once logic grows |

Both styles live in the same project, so a Laravel developer can keep the Eloquent feel
for simple tables while the complex ones get a service layer. See
[Architecture Decisions](../concepts/architecture.md) for when to choose which.

## Vocabulary

| Laravel | Symfony | Drupal | CakePHP | CodeIgniter | Gluo |
|---|---|---|---|---|---|
| `routes/api.php` | `#[Route]` | `*.routing.yml` | `config/routes.php` | `app/Config/Routes.php` | `#[OA\Get]` + `./gluo openapi` |
| `app/Models` | `src/Entity` | Entity API | `src/Model/Entity` | `app/Models` + `app/Entities` | `src/Model` + `#[TableAttribute]` |
| `app/Http/Controllers` | `src/Controller` | Controller plugin | `src/Controller` | `app/Controllers` | `src/Controller` |
| Eloquent | Doctrine ORM | Entity/Field API | CakePHP ORM | Model + Query Builder | `byjg/micro-orm` |
| — (Eloquent is the repo) | `src/Repository` | `EntityStorage` | `src/Model/Table` | — (the Model is the repo) | `src/Repository` |
| `database/migrations` | `migrations/` | `hook_update_N` | `config/Migrations` | `app/Database/Migrations` | `db/migrations/{up,down}` |
| `config/*.php` | `config/services.yaml` | `*.services.yml` | `config/app.php` | `app/Config/*.php` | `config/{env}/*.php` |
| `.env` | `.env` | `settings.php` | `config/app_local.php` | `.env` | `config/{env}/credentials.env` |
| `app()->make()` | autowiring | `\Drupal::service()` | `Application::services()` | `Config\Services` / `service()` | constructor injection (`Autowire::rule()` or explicit `DI::bind()`, your choice) |
| Gate / Policy | `#[IsGranted]` | `_permission` route key | Authorization policy | Shield filter | `#[RequireRole]` |
| FormRequest | Validator + DTO | Form API | `validationDefault()` | `$this->validate()` | `#[ValidateRequest]` + OpenAPI schema |
| `php artisan tinker` | — | `drush php` | `bin/cake console` | — | `./gluo terminal` |

## Commands

| Laravel | Symfony | CakePHP | CodeIgniter | Gluo |
|---|---|---|---|---|
| `php artisan serve` | `symfony serve` | `bin/cake server` | `php spark serve` | `docker compose up -d` |
| `php artisan migrate` | `doctrine:migrations:migrate` | `bin/cake migrations migrate` | `php spark migrate` | `./gluo --env=dev migrate:update` |
| `php artisan migrate:fresh` | `doctrine:schema:drop --force` | — | `php spark migrate:refresh` | `./gluo --env=dev migrate:reset` |
| `php artisan make:model -mcr` | `make:entity` / `make:crud` | `bin/cake bake all` | `php spark make:scaffold` | `./gluo codegen:all --table=x --save` |
| `php artisan test` | `bin/phpunit` | `vendor/bin/phpunit` | `vendor/bin/phpunit` | `composer test` |
| — | `cache:clear` | — | — | `./gluo openapi` |
| Pint / PHPStan | PHPStan | PHPStan | — | `composer psalm` |

## Where you are already at home

- **Layout.** The repository root *is* the PHP application root — one `composer.json`,
  one `vendor/`, with `src/`, `config/`, `db/`, `public/`, `tests/` beside it. See
  [Repository layout](../concepts/repo-layout.md).
- **Layering.** Controller → Service → Repository → Model is the Symfony playbook. See
  [Persistence](#persistence-repository-or-activerecord-per-entity) above for the
  ActiveRecord alternative.
- **Authorisation attributes.** `#[RequireAuthenticated]` and `#[RequireRole('admin')]`
  behave like Symfony's `#[IsGranted]`.
- **Testing.** Functional tests run against the OpenAPI contract, so a response that
  drifts from the documented schema fails the test. See [Testing](../guides/testing.md).

## What is not included, and where to find it

Gluo's core is deliberately small. There is no queue/job system, no event dispatcher, no
scheduler, and no template layer for HTML. These live in the ByJG ecosystem as standalone
components. They use the same conventions and plug into the same DI container, so you add
one when you need it and nothing is wired in by default:

| Need | Component | Status |
|---|---|---|
| Queues and jobs | [`byjg/message-queue-client`](https://opensource.byjg.com/docs/php/message-queue-client/), with RabbitMQ and Redis connectors | add it |
| Workers and cron jobs | [`byjg/scriptify`](https://opensource.byjg.com/docs/php/scriptify/) installs a class method or an app command as a `systemd` or `crond` service | already installed |
| HTML templates | [`byjg/jinja-php`](https://opensource.byjg.com/docs/php/jinja/) | already installed (renders the email templates) |
| Caching | [`byjg/cache-engine`](https://opensource.byjg.com/docs/php/cache-engine/) (PSR-6 / PSR-16) | already installed |
| Outbound HTTP | [`byjg/webrequest`](https://opensource.byjg.com/docs/php/webrequest/) (PSR-18) | add it |
| Feature flags | [`byjg/featureflag`](https://opensource.byjg.com/docs/php/featureflag/) | add it |
| Retries, circuit breaker | [`byjg/resilience`](https://opensource.byjg.com/docs/php/resilience/) | add it |
| Workflows | [`byjg/statemachine`](https://opensource.byjg.com/docs/php/statemachine/) | add it |

There is no generic event bus. The hooks that cover the usual reasons for one are
already there:

- **After a record changes:** MicroOrm [observers](https://opensource.byjg.com/docs/php/micro-orm/observers)
  fire on insert, update and delete of a table, whatever code path made the write.
- **Around every request:** RestServer before/after middleware.
- **On a workflow step:** a state machine `TransitionActionInterface` runs the side
  effects of a transition.
- **Across processes:** publish a message with `message-queue-client` and consume it in a
  worker installed by `scriptify`.

## See it side by side

[Gluo vs Laravel vs Symfony](https://opensource.byjg.com/docs/php/gluo-framework-comparison/)
builds the same small application with each framework, each one following what its own
framework recommends. It lists every command and every hand edit, so you can compare how
each feature is actually done.

## Next

1. [Installation](installation.md) — create the project
2. [Your first table](first-table.md) — migration then codegen
3. [Your first endpoint](first-endpoint.md) — attributes, and the regeneration step
