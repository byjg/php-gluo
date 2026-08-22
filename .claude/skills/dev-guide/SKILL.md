---
name: dev-guide
description: >
  Expert guide for working with the byjg/php-gluo project. Use this skill
  whenever the user is working on this project — including setting up the dev environment,
  creating new CRUD features, scaffolding with the code generator, modifying or fixing
  existing features, writing or updating tests, running migrations, and maintaining OpenAPI
  documentation. Trigger this skill for any task related to models, repositories, services,
  REST controllers, attributes, authentication, DI configuration, or the byjg framework stack
  in this codebase. Even if the user says something generic like "add a new endpoint" or
  "fix this bug", use this skill if they're working in this project.
---

# PHP REST Reference Architecture — Development Skill

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
    │   Config::get(ProductService::class)   ← PSR-11 DI container lookup
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

### First-time clone / full reset
```bash
git fetch && git pull && git merge origin/master
composer update     # PHP deps live in vendor
docker compose up -d
composer migrate -- --env=dev reset   # creates schema from scratch
composer psalm                        # or: php85 vendor/bin/psalm
composer test                         # or: vendor/bin/phpunit
```

### Every subsequent development cycle
```bash
git fetch && git pull && git merge origin/master
composer update
docker compose up -d
composer migrate -- --env=dev update  # applies only pending migrations
composer psalm
composer test
```

When done: `docker compose down`

---

## Project Structure

The project is a full-stack monorepo. **The repo root is the PHP application root**; an
optional Vite + React frontend is a sibling package under `frontend/`.

```
<repo root>/               # the PHP REST API — everything below is relative to the root
├── composer.json          # the single manifest (require, autoload, scripts)
├── docker-compose.yml     # API :8080, frontend :7080, MySQL :3306
├── docker/                # Dockerfile (API) + Dockerfile-html (frontend)
├── frontend/              # optional React 19 + Vite 6 + Tailwind SPA (see Frontend note below)

src/
├── Controller/           # HTTP controllers — attribute-based routing
├── Service/        # Business logic — wraps repositories (Repository pattern only)
├── Repository/     # Data access — queries and persistence
├── Model/          # Database models with ORM + OpenAPI attributes
├── Generator/      # UuidSeedGenerator (UUID PK helper)
└── OpenApiSpec.php # Root OpenAPI spec definition

# Attributes (RequireAuthenticated, ...), traits (OaCreatedAt, ...), base
# classes and utilities live in the byjg/gluo-core package (ByJG\Gluo\*).
# The PHP namespace is still RestReferenceArchitecture\* (renamed on create-project).

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
> `docker/Dockerfile-html` and served by byjg/static-httpserver on **:7080** (API on :8080).
> Kept or removed by the *Install Frontend* toggle at create-project time. See
> `docs/guides/frontend.md`. This skill covers the PHP side; the frontend is plain Vite.

---

## Architecture Decision: Repository Pattern vs ActiveRecord

**Always ask the user which they want before building.**

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

---

## Creating a New Feature

### Option A: Codegen (recommended starting point)
```bash
# 1. Write the migration (see below), then apply it:
composer migrate -- --env=dev update

# 2. Generate all classes:
composer codegen -- --env=dev --table=product all --save

# 3. Regenerate OpenAPI:
composer run openapi
```

Codegen produces: model, repository, service, controller, and tests. Edit to add business logic.

---

### Option B: Manual — Repository Pattern

#### 1. Migration

`db/migrations/up/XXXX.sql` (increment from last file):
```sql
CREATE TABLE product (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    price DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) ENGINE=InnoDB;
```

`db/migrations/down/XXXX.sql`: `DROP TABLE product;`

Apply: `composer migrate -- --env=dev update`

#### 2. Model

```php
#[OA\Schema(required: ["name"], type: "object")]
#[TableAttribute("product")]
class Product
{
    use OaCreatedAt, OaUpdatedAt, OaDeletedAt;

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    protected int|null $id = null;

    #[OA\Property(type: "string", maxLength: 120)]
    #[FieldAttribute(fieldName: "name")]
    protected string|null $name = null;

    public function getId(): int|null { return $this->id; }
    public function setId(int|null $id): static { $this->id = $id; return $this; }
    public function getName(): string|null { return $this->name; }
    public function setName(string|null $name): static { $this->name = $name; return $this; }
}
```

**Available traits:** `use OaCreatedAt;` / `use OaUpdatedAt;` / `use OaDeletedAt;`

**UUID primary key:** use `#[TableMySqlUuidPKAttribute("product")]` and `#[FieldUuidAttribute(primaryKey: true)]`
(see `src/Model/Task.php` for the complete UUID model pattern)

#### 3. Repository

```php
class ProductRepository extends BaseRepository
{
    public function __construct(DatabaseExecutor $executor)
    {
        $this->repository = new Repository($executor, Product::class);
    }
}
```

`BaseRepository` provides: `get($id)`, `list($page, $size)`, `save($model)`, `delete($id)`, `getByQuery($query)`.

**Custom query:**
```php
use ByJG\MicroOrm\Query;

public function getByName(string $name): array
{
    $query = Query::getInstance()
        ->table('product')
        ->where('product.name = :name', ['name' => $name]);
    return $this->repository->getByQuery($query);
}
```

See `references/queries.md` for advanced query patterns (joins, ordering, filtering).

#### 4. Service (Repository pattern only)

```php
class ProductService extends BaseService
{
    public function __construct(ProductRepository $repository)
    {
        parent::__construct($repository);
    }
    // Inherited: getOrFail($id), list($page, $size), save($model), create($data), update($data), delete($id)
}
```

#### 5. Register in DI Container

`config/dev/04-repositories.php`:
```php
use ByJG\Config\DependencyInjection as DI;

ProductRepository::class => DI::bind(ProductRepository::class)
    ->withInjectedConstructor()
    ->toSingleton(),
```

`config/dev/05-services.php`:
```php
ProductService::class => DI::bind(ProductService::class)
    ->withInjectedConstructor()
    ->toSingleton(),
```

No controller entry is needed: `config/dev/07-controllers.php` autowires the whole
controller namespace with one `Autowire::rule()`. Keep the controller in that namespace
and its constructor is injected. Add an explicit binding there only if a controller needs
something the rule cannot express (a scalar constructor argument, say) — explicit wins.

Repeat for `config/test/` (required for tests to work).

#### 6. REST Controller

```php
class ProductController
{
    public function __construct(protected ProductService $productService)
    {
    }

    #[OA\Get(path: "/product/{id}", security: [["jwt-token" => []]], tags: ["product"])]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Success",
        content: new OA\JsonContent(ref: "#/components/schemas/Product"))]
    #[OA\Response(response: 404, description: "Not Found",
        content: new OA\JsonContent(ref: "#/components/schemas/error"))]
    #[RequireAuthenticated]
    public function getProduct(HttpResponse $response, HttpRequest $request): void
    {
        $result = $this->productService->getOrFail($request->attribute('id'));
        $response->write($result);
    }

    #[OA\Post(path: "/product", security: [["jwt-token" => []]], tags: ["product"])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: "#/components/schemas/Product"))]
    #[OA\Response(response: 200, description: "Created",
        content: new OA\JsonContent(ref: "#/components/schemas/Product"))]
    #[RequireAuthenticated]
    #[ValidateRequest]
    public function postProduct(HttpResponse $response, HttpRequest $request): void
    {
        $model = $this->productService->create(ValidateRequest::getPayload());
        $response->write($model);
    }
}
```

**Request helpers:**
- `$request->attribute('id')` — path param (e.g. `{id}`) or JWT decoded claim (e.g. `$request->attribute('jwt.data')`)
- `$request->query('page')` — query string param
- `ValidateRequest::getPayload()` — validated request body (array for JSON)

**Security attributes:**
- `#[RequireAuthenticated]` — any valid JWT token
- `#[RequireRole(User::ROLE_ADMIN)]` — JWT + specific role
- `#[ValidateRequest]` — validates body against OpenAPI schema

#### 7. Regenerate OpenAPI

```bash
composer run openapi
```

Always run this after adding or changing controller attributes. It updates `public/docs/openapi.json`
which drives both routing and contract testing. Forgetting it makes the endpoint 404 with no error —
`composer openapi:check` (run automatically by `composer test`) warns when the spec is older than
anything in `src/Controller/` or `src/Model/`.

`openapi.json` is the single source of truth: `OpenApiRouteList` reads it to build the route
table (URL+method → Controller::method), and `#[ValidateRequest]` reads it to validate request
bodies. See `references/request-response.md` for the full pipeline, content negotiation, and
OpenAPI attribute patterns.

#### 8. Tests

See `references/testing.md` for a complete test guide.

Quick pattern — extend `BaseApiTestCase` and use `FakeApiRequester`:

```php
class ProductTest extends BaseApiTestCase
{
    public function testGetUnauthorized(): void
    {
        $this->expectException(Error401Exception::class);
        $request = (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')->withPath('/product/1')
            ->expectStatus(401);
        $this->sendRequest($request);
    }

    public function testCreate(): void
    {
        // Login to get token
        $loginResult = json_decode(
            $this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))
                ->getBody()->getContents(),
            true
        );
        $token = $loginResult['token'];

        // POST
        $body = $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('POST')->withPath('/product')
                ->withRequestBody(json_encode(['name' => 'Widget']))
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(200)
        );
        $result = json_decode($body->getBody()->getContents(), true);
        $this->assertNotEmpty($result['id']);
    }
}
```

Look at `tests/Controller/ProjectTest.php` for a complete reference implementation.

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

`BaseService::create()` and `update()` already call `ObjectCopy` internally — you don't need
to call it manually in those flows.

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

See the skill's existing documentation in the current SKILL for full `oneOf` example.

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
4. `composer run openapi` after any controller attribute changes
5. `composer psalm` — fix type errors (or `php85 vendor/bin/psalm`)
6. `composer test` — all tests must pass
7. Update tests to reflect new behavior

---

## Key Commands Reference

| Command | Purpose |
|---------|---------|
| `docker compose up -d` | Start MySQL + PHP (+ frontend) containers |
| `docker compose down` | Stop containers |
| `composer update` | Update PHP dependencies (live in vendor) |
| `composer psalm` | Run static analysis |
| `php85 vendor/bin/psalm` | Psalm fallback (if default is buggy) |
| `composer test` | Run test suite (phpunit) |
| `composer run openapi` | Regenerate OpenAPI spec from attributes |
| `composer openapi:check` | Warn if the spec is stale (auto-run by `composer test`) |
| `composer migrate -- --env=dev update` | Apply pending migrations (normal dev) |
| `composer migrate -- --env=dev reset` | Wipe and recreate DB (first install / CI) |
| `composer codegen -- --env=dev --table=X all --save` | Scaffold full CRUD for table X |

---

## After Every Change Checklist

- [ ] `composer run openapi` — if any controller attributes changed
- [ ] `composer psalm` — static analysis passes
- [ ] `composer test` — all tests pass
- [ ] Documentation in `docs/` reflects changes if relevant

---

## Reference Files

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
- `references/scriptify.md` — CLI scripts, cron jobs, and systemd services: run any class::method from the command line or install as a daemon
- `references/featureflags.md` — Feature flags: PHP attribute approach, DI registration, dispatcher-per-handler-class pattern, testing with clearFlags()