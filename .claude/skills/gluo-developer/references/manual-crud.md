# Manual CRUD — Repository Pattern

Check the installed core version and existing application conventions before using these
examples; see [the development workflow](../SKILL.md). The SQL below is a MySQL example;
adapt it to the selected database. Codegen is the preferred way to get a complete model.

## 1. Migration

For example, when the current version is 2, create `db/migrations/up/00003-create-product.sql`:
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

`db/migrations/down/00002-drop-product.sql`: `DROP TABLE product;`

The filename identifies the destination version: up to 3, down to 2. Choose the next
version from the existing migrations; do not reuse the up number for its rollback.

Apply: `php gluo --env=dev migrate:update`

## 2. Model

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

## 3. Repository

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

See `queries.md` for advanced query patterns (joins, ordering, filtering).

## 4. Service (Repository pattern only)

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

## 5. Register in DI Container

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

`config/test/` inherits these bindings from dev. Add only test-specific overrides or mocks there.

## 6. REST Controller

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

## 7. Regenerate OpenAPI

```bash
php gluo openapi
```

The spec is OpenAPI 3.1: `nullable: true` on an attribute becomes a type union in the JSON
(`"type": ["string", "null"]`). Always run this after adding or changing model or controller attributes. It updates `public/docs/openapi.json`
which drives both routing and contract testing. Forgetting it makes the endpoint 404 with no error —
`php gluo openapi:check` (run automatically by `php gluo test`) warns when the spec is older than
anything in `src/Controller/` or `src/Model/`.

`openapi.json` is the single source of truth: `OpenApiRouteList` reads it to build the route
table (URL+method → Controller::method), `#[ValidateRequest]` reads it to validate request
bodies, and `SchemaValidator` reads it to validate each model before it is saved. See `request-response.md` for the full pipeline, content negotiation, and
OpenAPI attribute patterns.

## 8. Tests

See `testing.md` for a complete test guide.

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
