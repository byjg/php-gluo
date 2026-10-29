---
name: gluo-developer
description: >
  Develop and maintain PHP REST APIs created with byjg/gluo: endpoints, models,
  persistence, DI configuration, migrations, authentication, OpenAPI, and tests.
  Use whenever working on a Gluo PHP application, even for generic requests such
  as "add a new endpoint" or "fix this bug" that do not name Gluo explicitly.
  Follow the project's installed dependency versions and existing conventions.
  Frontend-only work needs this skill only when it changes the API integration.
---

# Gluo — Development Skill

This is a production-ready PHP REST API template (not a framework). You own it completely
and modify it freely. The focus is clean separation of concerns, OpenAPI-first design, and
testability.

**Core libraries:** `byjg/restserver` (routing), `byjg/micro-orm` (ORM), `byjg/authuser`
(JWT auth), `byjg/config` (DI container), `byjg/migration` (DB migrations)

---

## How the Stack Connects

Understanding the request lifecycle prevents confusion when debugging or extending:

```
HTTP Request
    │
    ▼
JwtMiddleware          ← byjg/restserver middleware, parses/validates JWT,
    │                    stores decoded claims as request param "jwt.data"
    ▼
OpenApiRouteList       ← matches URL+method to controller class::method
    │                    (generated from public/docs/openapi.json)
    ▼
PHP Attribute Chain    ← run BEFORE the controller method:
  #[RequireAuthenticated]  → verifies JWT present; calls JwtContext::setRequest()
  #[RequireRole("admin")]  → verifies JWT role claim; calls JwtContext::setRequest()
  #[ValidateRequest]       → validates body against OpenAPI schema; stores payload
    │
    ▼
Controller method      ← receives (HttpResponse $response, HttpRequest $request)
    │   $this->productService               ← constructor-injected service
    │   ValidateRequest::getPayload()        ← validated, null-stripped array
    │   $request->attribute('id')            ← path param OR jwt.data sub-key
    │   $request->query('page')               ← query string param
    │
    ▼
Service                ← business logic; wraps repository (Repository pattern)
    │
    ▼
Repository             ← data access; wraps ByJG\MicroOrm\Repository
    │
    ▼
DatabaseExecutor       ← connection pool / transactions
    │
    ▼
MySQL
```

**DI container initialization order** (config files load in this exact sequence):
1. `01-infrastructure.php` — DB driver, DatabaseExecutor, ORM setup, logging
2. `02-security.php` — JWT, password policy, UsersService/Repository, CORS list
3. `03-api.php` — OpenApiRouteList, JwtMiddleware, CorsMiddleware, HttpRequestHandler
4. `04-repositories.php` — your feature repositories
5. `05-services.php` — your feature services
6. `06-external.php` — external services (mail, etc.)
7. `07-controllers.php` — Autowire rule covering the controller namespace

> ActiveRecord models rely on `"ORMInitialization"` (a `toEagerSingleton()` in `01-infrastructure.php`)
> which calls `ORM::defaultDbDriver()` at startup. This happens automatically — you don't call it yourself.

---

> **Monorepo note.** The repository root *is* the PHP application root: one `composer.json`,
> one `vendor/`, with `src/`, `config/`, `db/`, `public/`, `templates/` and `tests/` as its
> siblings. Every command (`test`, `migrate`, `codegen`, `openapi`, `psalm`, `terminal`) and
> every raw `php vendor/bin/*` call runs from the repo root. The optional Vite frontend is a
> sibling package in `frontend/`, with its own `package.json` and container.

### Before making changes

Read `composer.json`, `composer.lock`, and the relevant installed code under
`vendor/byjg/`. A published skill can be newer than an application's dependencies;
use the installed APIs and templates rather than assuming every example applies.
Check the application's namespace, selected database, environment configuration, and
existing feature pattern. The starter examples may have been removed during installation.

For a clone with a lockfile, install its locked dependencies with `composer install`.
Use `composer update` only when upgrading dependencies is part of the task. Fetching,
merging branches, and resetting databases are separate operations, not routine setup.

Start the services needed for the task with `docker compose up -d` if they are not
already running. Inspect the connection and migration status before applying pending
migrations with `php gluo --env=dev migrate:update`. Use `migrate:reset` only for an
explicitly disposable database or a requested reset: it destroys the existing data.
Leave existing containers running unless stopping them is part of the task.

For PHP behavior changes, run `composer psalm` and the relevant tests through
`php gluo test`; regenerate OpenAPI first when model or controller attributes change.
Documentation-only changes need documentation checks rather than a database reset.

---

## Project Structure

The project is a full-stack monorepo. **The repo root is the PHP application root**; an
optional Vite + React frontend is a sibling package under `frontend/`.

```
<repo root>/               # the PHP REST API — everything below is relative to the root
├── composer.json          # the single manifest (require, autoload, scripts)
├── docker-compose.yml     # API :8080, frontend :7080 (HTTPS :7443); includes docker-compose.database.yml
├── docker-compose.database.yml # the database server (`gluo-database`; none for SQLite)
├── docker/                # Dockerfile (API) + Dockerfile-html (frontend)
├── frontend/              # optional React 19 + Vite 6 + Tailwind SPA (see Frontend note below)

src/
├── Controller/           # HTTP controllers — attribute-based routing
├── Service/        # Business logic — wraps repositories (Repository pattern only)
├── Repository/     # Data access — queries and persistence
├── Model/          # Database models with ORM + OpenAPI attributes
└── OpenApiSpec.php # Root OpenAPI spec definition

# Attributes (RequireAuthenticated, ...), traits (OaCreatedAt, ...), base
# classes and utilities live in the byjg/gluo-core package (ByJG\Gluo\*).
# The examples use App\* for the application's namespace. The starter's own is
# RestReferenceArchitecture\*, which create-project renames to the one you choose.

config/{env}/
├── 01-infrastructure.php  # DB, cache, logging, ORM init
├── 02-security.php        # JWT, password policy, auth user stack
├── 03-api.php             # HTTP handler, middleware, routing
├── 04-repositories.php    # Repository DI bindings
├── 05-services.php        # Service DI bindings
├── 06-external.php        # External services (mail, etc.)
└── 07-controllers.php     # Controller autowire rule

db/
├── base.sql               # Base schema + seed users + users_property
└── migrations/
    ├── up/                # Forward SQL files (00001.sql, 00002.sql, ...)
    └── down/              # Rollback SQL files
```

**Bundled controllers:** `LoginController` (auth flow), `ProfileController`
(`GET`/`PUT /profile` — name/email plus a `language` property, `en`/`fr`/`pt`, stored in the
`users_property` table), and `SampleController` / `SampleProtectedController` (ping/demo).
The example CRUD entities are `Project` (Repository, int PK, table `project`),
`Task` (Repository, UUID PK, table `task`), and `Note` (ActiveRecord, table `note`).

> **Frontend (optional, `frontend/`).** A React 19 + Vite 6 + Tailwind SPA with login,
> password-reset, dashboard, and profile screens wired to the API over JWT. Built by
> `docker/Dockerfile-html` and served by byjg/static-httpserver on **:7080**, and over HTTPS on **:7443** (API on :8080).
> Kept or removed by the *Install Frontend* toggle at create-project time. See
> `docs/guides/frontend.md`. This skill covers the PHP side; the frontend is plain Vite.

---

## Architecture Decision: Repository Pattern vs ActiveRecord

Follow the user's choice or the existing feature's pattern. For a new entity, choose
the pattern that fits its responsibilities; ask only when a consequential choice
remains unresolved. Both patterns can coexist in one project.

### Repository Pattern (more layers, more control)
`Controller → Service → Repository → Model`
- Use when: complex business logic, validation, multiple repos, team projects
- Files: Model + Repository + Service + Controller (4 files + DI registrations + tests)
- Reference: `src/Controller/ProjectController.php`, `src/Repository/ProjectRepository.php` (int PK) or `src/Controller/TaskController.php` (UUID PK)

### ActiveRecord Pattern (fewer layers, simpler)
`Controller → Model (handles its own persistence)`
- Use when: simple CRUD, prototyping, admin panels
- Files: Model + Controller (2 files + no DI registrations needed + tests)
- Reference: `src/Controller/NoteController.php`, `src/Model/Note.php`
- The model uses `ByJG\Gluo\Trait\ActiveRecord` (MicroOrm's plus `getOrFail($id, ?$message)`, which
  answers 404), so the controller writes `Note::getOrFail($request->attribute('id'))`, no null check

---

## Creating a New Feature

### Option A: Codegen (recommended starting point)
```bash
# 1. Write the migration (see references/manual-crud.md), then apply it:
php gluo --env=dev migrate:update

# 2. Generate all classes for a new feature (Repository pattern):
php gluo --env=dev codegen:all --table=product --save

# Alternative for ActiveRecord (choose one pattern):
# php gluo --env=dev codegen:all --table=product --activerecord --save

# 3. Regenerate OpenAPI:
php gluo openapi
```

Codegen produces the classes for the selected pattern and tests. Edit to add business logic.
`--save` overwrites generated files. For an existing feature, omit `--save` to preview
output, inspect the differences, and preserve custom code, validation ranges, and tests.
The behavior below describes the current starter; check the installed templates first.

What the generated code already does, so you don't write it again:
- **Model**: `OA\Property` for each column, with `maxLength` from `char`/`varchar`, `minLength: 1`
  for required text with no default, `nullable` from `NULL`; the primary key and virtual columns
  are `readOnly`. The table attribute declares `validator: SchemaValidator::class`, so every
  `save()` is checked against this schema (400 on failure). Date columns become the
  `OaCreatedAt`/`OaUpdatedAt`/`OaDeletedAt` traits.
- **Controller**: `GET /product/{id}`, `GET /product` (paged, `orderBy`), `POST /product`, and
  `PUT /product/{id}`. POST and PUT share the body (writable fields only). Writes require
  `RequireRole(User::ROLE_ADMIN)`, reads `RequireAuthenticated`. No DELETE: write it by hand.
  ActiveRecord controllers use `Product::getOrFail()`.
- **Test**: auth (401/403), the full CRUD, list and ordering, and invalid bodies sent to the
  server (the key in the body, missing required fields, an empty text) on POST and PUT.
- **Not generated**: value ranges (`minimum`/`maximum`), the DELETE endpoint, business rules.

---

### Option B: Manual implementation

Read [Manual CRUD](references/manual-crud.md) for the migration, model, repository,
service, DI, controller and test walkthrough. Use it when writing custom endpoints
or adapting generated code. For advanced queries, read [Queries](references/queries.md).

### Essential conventions

- Controllers receive `(HttpResponse $response, HttpRequest $request)` and return `void`.
- Inject services through the constructor. Controllers are autowired; services and
  repositories have explicit bindings in `config/dev/`, inherited by `config/test/`.
- Current generated PUT routes take the key in the path and writable fields in the body.
- Use `getOrFail()` for missing-record 404s and `OrderBy::fromQueryString()` for client sorting.
- Regenerate OpenAPI after model or controller attribute changes. The generated JSON
  drives routing, request validation, save-time model validation, and contract tests.
- `ValidateRequest` strips nulls after validation by default; use
  `preserveNullValues: true` when callers must clear nullable fields.
- Invalid-body tests must reach the server without client-side schema validation;
  see [Testing](references/testing.md).

---

## Writing Responses and Converting Objects

```php
$response->write($model);              // object or array → JSON (serializer handles it)

// Object → array (for filtering fields before sending):
use ByJG\Serializer\Serialize;
$publicData = Serialize::from($model)->withIgnoreProperties(['secret'])->toArray();
$response->write($publicData);

// Only specific fields (public subset):
$data = Serialize::from($model)->withOnlyProperties(['id', 'name'])->toArray();

// Strip null fields from output:
$data = Serialize::from($model)->withDoNotParseNullValues()->toArray();

// Array/body → existing object (for create/update flows):
use ByJG\Serializer\ObjectCopy;
ObjectCopy::copy($arrayData, $product);    // copies matching properties by name
```

`BaseService::create()` hydrates through the mapper; `update($id, $payload)` uses
`ObjectCopy` on the loaded entity. Neither flow needs a separate copy in the controller.

See `references/serialization.md` for full patterns including role-based field selection,
null stripping, and case-transformation handlers.

---

## Authentication Patterns

JWT is decoded by `JwtMiddleware` and stored as request params. Access it in any
`#[RequireAuthenticated]` or `#[RequireRole]` protected method:

```php
use ByJG\Gluo\Util\JwtContext;

$userId = JwtContext::getUserId();   // JWT "userid" claim
$role   = JwtContext::getRole();     // JWT "role" claim
$name   = JwtContext::getName();     // JWT "name" claim
```

**Role constants:** `User::ROLE_ADMIN`, `User::ROLE_USER`

**Login flow** (implemented in `src/Controller/LoginController.php`):
1. `POST /login` with `{username, password}` → `JwtContext::createUserMetadata()` validates via `UsersService`
2. Returns `{token, data: {userid, name, role}}`
3. Client sends `Authorization: Bearer <token>` on subsequent requests
4. `JwtMiddleware` validates signature on every request, stores decoded claims

---

## Role-Based Responses

When an endpoint must return different fields by role, pick one approach first:

1. **Two endpoints** (recommended): `GET /product` (own data from JWT) + `GET /product/{id}` (admin)
2. **`oneOf` schema**: single URL with two documented schemas
3. **Nullable admin fields**: simpler but conflates two audiences

See [OpenAPI patterns](references/openapi-patterns.md#oneof--response-can-be-one-of-several-shapes) for a `oneOf` example.

---

## Error Handling

Throw to return the right HTTP status:
- `Error400Exception` — Bad Request
- `Error401Exception` — Unauthorized
- `Error403Exception` — Forbidden
- `Error404Exception` — Not Found (`getOrFail()` throws this automatically)
- `Error422Exception` — Unprocessable Entity (validation errors)
- `Error520Exception` — Internal error

---

## Modifying Existing Features

1. Read the existing code first (model, repository, service, controller, tests)
2. Make changes at the appropriate layer
3. Write a new migration if DB schema changes
4. `php gluo openapi` after model or controller attribute changes
5. `composer psalm` — fix type errors
6. Update tests for the changed behavior and run them with `php gluo test`

---

## Key Commands Reference

| Command | Purpose |
|---------|---------|
| `docker compose up -d` | Start the configured services (database choice varies) |
| `docker compose down` | Stop containers |
| `php gluo list` | List the built-in and project commands (`--check` validates them) |
| `php gluo <name> [args]` | Run a project command declared in `src/Command/Commands.php` |
| `php gluo --env=prod install <name>` | Install a project command as a system service |
| `composer install` | Install the locked PHP dependencies |
| `composer update` | Upgrade dependencies when requested |
| `composer psalm` | Run static analysis |
| `php gluo test` | Run test suite (phpunit) |
| `php gluo openapi` | Regenerate OpenAPI spec from attributes |
| `php gluo openapi:check` | Fail if the spec is out of date (auto-run by `php gluo test`) |
| `php gluo --env=dev migrate:update` | Apply pending migrations (normal dev) |
| `php gluo --env=dev migrate:reset` | Wipe and recreate the selected DB; only for an intended reset |
| `php gluo --env=dev codegen:all --table=X --save` | Scaffold full CRUD for table X |

---

## Verification

- [ ] `php gluo openapi` — if model or controller attributes changed
- [ ] `composer psalm` — for PHP changes
- [ ] `php gluo test` — relevant tests pass for changed behavior
- [ ] Documentation in `docs/` reflects changes if relevant

---

## Reference Files

Read only the references relevant to the task.

- [Manual CRUD](references/manual-crud.md) — complete Repository walkthrough and ActiveRecord differences

- `references/queries.md` — advanced ORM queries, joins, filtering, pagination
- `references/testing.md` — complete testing patterns and cheatsheet
- `references/serialization.md` — Serialize and ObjectCopy: response shaping, field filtering, hydrating entities
- `references/request-response.md` — OpenAPI routing, ValidateRequest, input/output content negotiation
- `references/openapi-patterns.md` — Edge-case OpenAPI patterns: enums, formats, oneOf, nullable, nested schemas, additionalProperties, custom attribute classes
- `references/http-client.md` — Outbound HTTP with byjg/uri + byjg/webrequest (prefer over Guzzle)
- `references/di-environments.md` — Environment system, config loading order, DI binding methods, Param::get()
- `references/xml.md` — XML input (XmlDocument) and output, OpenAPI attributes, pitfalls
- `references/cache.md` — Cache engines, DI wiring per environment, caching ORM queries and OpenAPI routes
- `references/users.md` — Extending User model, properties, password hashing, JWT payload customization
- `references/email.md` — Sending email, templates, provider URIs, FakeSenderWrapper for tests
- `references/jinja.md` — byjg/jinja-php syntax, supported filters/tags, and what is NOT available vs Python Jinja2
- `references/anydataset.md` — Unified row/iterator abstraction: in-memory, XML, JSON, CSV/fixed-width, NoSQL; install instructions for optional extensions
- `references/imageutil.md` — GD-based image manipulation: resize, crop, rotate, flip, watermark, text overlay, saving; install instructions
- `references/statemachine.md` — Finite state machine: workflow transitions, autoTransitionFrom for classification, DI registration pattern
- `references/messagequeue.md` — Message queues (RabbitMQ, Redis, mock): publish, consume, DLQ, ACK/NACK, DI wiring, test mock
- `references/scriptify.md` — Project commands (`php gluo <name>`, `src/Command/Commands.php`, DI-built) and `php gluo install` as a cron job or systemd service; `scriptify run` and `scriptify call` directly
- `references/featureflags.md` — Feature flags: PHP attribute approach, DI registration, dispatcher-per-handler-class pattern, testing with clearFlags()
