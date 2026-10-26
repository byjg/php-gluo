---
name: gluo-developer
description: >
  Expert guide for working on a Gluo project: a PHP REST API created from byjg/php-gluo
  (composer create-project byjg/gluo) on the byjg/gluo-core framework. Use this skill
  whenever the user is working on such a project — including setting up the dev environment,
  creating new CRUD features, scaffolding with the code generator, modifying or fixing
  existing features, writing or updating tests, running migrations, and maintaining OpenAPI
  documentation. Trigger this skill for any task related to models, repositories, services,
  REST controllers, attributes, authentication, DI configuration, or the byjg framework stack
  in this codebase. Even if the user says something generic like "add a new endpoint" or
  "fix this bug", use this skill if they're working in this project.
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
php gluo --env=dev migrate:reset   # creates schema from scratch
composer psalm                        # or: php85 vendor/bin/psalm
php gluo test                         # or: vendor/bin/phpunit
```

### Every subsequent development cycle
```bash
git fetch && git pull && git merge origin/master
composer update
docker compose up -d
php gluo --env=dev migrate:update  # applies only pending migrations
composer psalm
php gluo test
```

When done: `docker compose down`

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
- The model uses `ByJG\Gluo\Trait\ActiveRecord` (MicroOrm's plus `getOrFail($id, ?$message)`, which
  answers 404), so the controller writes `Note::getOrFail($request->attribute('id'))`, no null check

---

## Creating a New Feature

### Option A: Codegen (recommended starting point)
```bash
# 1. Write the migration (see below), then apply it:
php gluo --env=dev migrate:update

# 2. Generate all classes:
php gluo --env=dev codegen:all --table=product --save

# 3. Regenerate OpenAPI:
php gluo openapi
```

Codegen produces: model, repository, service, controller, and tests. Edit to add business logic.

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

Apply: `php gluo --env=dev migrate:update`

#### 2. Model

```php
#[OA\Schema(required: ["id", "name"], type: "object")]
#[TableAttribute("product", validator: SchemaValidator::class)]
class Product
{
    use OaCreatedAt, OaUpdatedAt, OaDeletedAt;

    #[OA\Property(type: "integer", format: "int32", readOnly: true)]
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    protected int|null $id = null;

    #[OA\Property(type: "string", maxLength: 120, minLength: 1)]
    #[FieldAttribute(fieldName: "name")]
    protected string|null $name = null;

    public function getId(): int|null { return $this->id; }
    public function setId(int|null $id): static { $this->id = $id; return $this; }
    public function getName(): string|null { return $this->name; }
    public function setName(string|null $name): static { $this->name = $name; return $this; }
}
```

**Available traits:** `use OaCreatedAt;` / `use OaUpdatedAt;` / `use OaDeletedAt;`

**Validated on every save:** `validator: SchemaValidator::class` (`ByJG\Gluo\Util\SchemaValidator`)
checks the model against its own OpenAPI schema before `save()` writes it, so a command, a
consumer or a service gets the same 400 as `#[ValidateRequest]`. Mark what the server generates
`readOnly: true` (an auto-increment or seeded key, virtual columns): it is then not required on
insert. A key the client supplies stays writable. Only `save()` is checked, not `UpdateQuery`,
`InsertBulkQuery` or raw SQL.

**UUID primary key:** use `#[TableUuidPKAttribute("product", validator: SchemaValidator::class)]` and `#[FieldUuidAttribute(primaryKey: true)]`
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
    // Inherited: getOrFail($id, ?$message), list($page, $size), save($model), create($data),
    //            update($id, $data, ?$message), delete($id, ?$message); a missing record is a 404 "<Model> not found"
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

    // A list is paged and ordered by the API: ?page=0&size=20&orderBy=name desc,id
    #[OA\Get(path: "/product", security: [["jwt-token" => []]], tags: ["product"])]
    #[OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "size", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "orderBy", in: "query", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "Success",
        content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Product")))]
    #[RequireAuthenticated]
    public function listProduct(HttpResponse $response, HttpRequest $request): void
    {
        $response->write($this->productService->list(
            (int)($request->queryString('page') ?? 0),
            (int)($request->queryString('size') ?? 50),
            OrderBy::fromQueryString($request->queryString('orderBy'), Product::class)
        ));
    }

    // POST and PUT take the same body: the writable fields only, declared inline. The key is
    // generated on POST and taken from the path on PUT, so a key in the body is refused (400).
    #[OA\Post(path: "/product", security: [["jwt-token" => []]], tags: ["product"])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ["name"],
        properties: [new OA\Property(property: "name", type: "string", format: "string", maxLength: 120, minLength: 1)]
    ))]
    #[OA\Response(response: 200, description: "Created",
        content: new OA\JsonContent(properties: [new OA\Property(property: "id", type: "integer")]))]
    #[RequireRole(User::ROLE_ADMIN)]
    #[ValidateRequest]
    public function postProduct(HttpResponse $response, HttpRequest $request): void
    {
        $model = $this->productService->create(ValidateRequest::getPayload());
        $response->write(["id" => $model->getId()]);
    }

    #[OA\Put(path: "/product/{id}", security: [["jwt-token" => []]], tags: ["product"])]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ["name"],
        properties: [new OA\Property(property: "name", type: "string", format: "string", maxLength: 120, minLength: 1)]
    ))]
    #[OA\Response(response: 200, description: "Nothing to return")]
    #[RequireRole(User::ROLE_ADMIN)]
    #[ValidateRequest]
    public function putProduct(HttpResponse $response, HttpRequest $request): void
    {
        // 404 "Product not found" when there is no such record
        $this->productService->update($request->attribute('id'), ValidateRequest::getPayload());
    }

    // codegen does not write a DELETE; this is the hand-written one
    #[OA\Delete(path: "/product/{id}", security: [["jwt-token" => []]], tags: ["product"])]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Nothing to return")]
    #[RequireRole(User::ROLE_ADMIN)]
    public function deleteProduct(HttpResponse $response, HttpRequest $request): void
    {
        $this->productService->delete($request->attribute('id'));   // 404 when missing
    }
}
```

In the ActiveRecord pattern the same endpoints call the model: `Product::getOrFail($id)` for GET,
`$model = Product::getOrFail($id); $model->fill(ValidateRequest::getPayload()); $model->save();`
for PUT, and `Product::getOrFail($id)->delete()` for DELETE.

`OrderBy::fromQueryString()` (`ByJG\Gluo\Util\OrderBy`) accepts fields of the model, by
property or column name, each optionally followed by `asc` or `desc`; anything else is a 400.

**Request helpers:**
- `$request->attribute('id')` — path param (e.g. `{id}`) or JWT decoded claim (e.g. `$request->attribute('jwt.data')`)
- `$request->queryString('page')` — one query string param as a string (`$request->query()` returns them all)
- `ValidateRequest::getPayload()` — validated request body (array for JSON)

**Security attributes:**
- `#[RequireAuthenticated]` — any valid JWT token
- `#[RequireRole(User::ROLE_ADMIN)]` — JWT + specific role
- `#[ValidateRequest]` — validates body against OpenAPI schema

#### 7. Regenerate OpenAPI

```bash
php gluo openapi
```

The spec is OpenAPI 3.1: `nullable: true` on an attribute becomes a type union in the JSON
(`"type": ["string", "null"]`). Always run this after adding or changing controller attributes. It updates `public/docs/openapi.json`
which drives both routing and contract testing. Forgetting it makes the endpoint 404 with no error —
`php gluo openapi:check` (run automatically by `php gluo test`) warns when the spec is older than
anything in `src/Controller/` or `src/Model/`.

`openapi.json` is the single source of truth: `OpenApiRouteList` reads it to build the route
table (URL+method → Controller::method), `#[ValidateRequest]` reads it to validate request
bodies, and `SchemaValidator` reads it to validate each model before it is saved. See `references/request-response.md` for the full pipeline, content negotiation, and
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
4. `php gluo openapi` after any controller attribute changes
5. `composer psalm` — fix type errors (or `php85 vendor/bin/psalm`)
6. `php gluo test` — all tests must pass
7. Update tests to reflect new behavior

---

## Key Commands Reference

| Command | Purpose |
|---------|---------|
| `docker compose up -d` | Start MySQL + PHP (+ frontend) containers |
| `docker compose down` | Stop containers |
| `php gluo list` | List the built-in and project commands (`--check` validates them) |
| `php gluo <name> [args]` | Run a project command declared in `src/Command/Commands.php` |
| `php gluo --env=prod install <name>` | Install a project command as a system service |
| `composer update` | Update PHP dependencies (live in vendor) |
| `composer psalm` | Run static analysis |
| `php85 vendor/bin/psalm` | Psalm fallback (if default is buggy) |
| `php gluo test` | Run test suite (phpunit) |
| `php gluo openapi` | Regenerate OpenAPI spec from attributes |
| `php gluo openapi:check` | Warn if the spec is stale (auto-run by `php gluo test`) |
| `php gluo --env=dev migrate:update` | Apply pending migrations (normal dev) |
| `php gluo --env=dev migrate:reset` | Wipe and recreate DB (first install / CI) |
| `php gluo --env=dev codegen:all --table=X --save` | Scaffold full CRUD for table X |

---

## After Every Change Checklist

- [ ] `php gluo openapi` — if any controller attributes changed
- [ ] `composer psalm` — static analysis passes
- [ ] `php gluo test` — all tests pass
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
- `references/scriptify.md` — Project commands (`php gluo <name>`, `src/Command/Commands.php`, DI-built) and `php gluo install` as a cron job or systemd service; `scriptify run` and `scriptify call` directly
- `references/featureflags.md` — Feature flags: PHP attribute approach, DI registration, dispatcher-per-handler-class pattern, testing with clearFlags()