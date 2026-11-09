# Request/Response Pipeline: OpenAPI, Routing, and Content Negotiation

## How it all connects

```
src/ PHP attributes
    │
    ▼  php gluo openapi
public/docs/openapi.json   ←── single source of truth for routing + validation (and contract tests)
    │
    ▼  compiled by the same command
generated/routes.php  ──► CompiledRouteList   routes URL+method → Controller::method
generated/openapi.php ──► Schema::class       validates request bodies in #[ValidateRequest]
```

`openapi.json` is generated from the PHP attributes in `src/`, and `generated/` from
`openapi.json`. **You never hand-edit either.** Run `php gluo openapi` after any model or
controller attribute change and commit all three files — routing AND validation both break if
they are stale; `php gluo openapi:check` (run by `php gluo test`) fails when one is.

The compiled files are PHP arrays that OPcache keeps in memory: a request neither parses the
JSON nor builds the dispatcher. `config/dev/03-api.php` binds them:

```php
RouteListInterface::class => DI::bind(CompiledRouteList::class)
    ->withConstructorArgs([__DIR__ . '/../../generated/routes.php'])
    ->toSingleton(),

Schema::class => DI::bind(Schema::class)
    ->withFactoryMethod('fromFile', [__DIR__ . '/../../generated/openapi.php'])
    ->toSingleton(),
```

`Application` and `FakeApiRequester` get the routes by the `RouteListInterface::class` key.
There is no route cache to configure (`OpenApiRouteList::withCache()` is not used).

---

## Route registration

Routes are declared entirely through OpenAPI attributes on controller methods. The
`operationId` that `zircote/swagger-php` generates becomes the routing key:

```
operationId: "App\\Controller\\ProjectController::getProject"
                 └── class (fully qualified)      └── method
```

`php gluo openapi` reads `openapi.json` with `OpenApiRouteList`, builds the route table from
`operationId`, registers the correct output processor per route (derived from the
`responses.200.content` keys), and writes it to `generated/routes.php`. No manual route
registration is needed — adding an `#[OA\Get(...)]` attribute and running `php gluo openapi`
is sufficient.

### How operationId is set

`zircote/swagger-php` derives `operationId` automatically from the class and method name when
`operationId.hash` is false (the setting used here). You never write it manually.

### Default output processor

The output processors are fixed when the routes are compiled, not in `03-api.php`.
`BaseScripts::getOpenApiRouteList()` (byjg/gluo-core) sets `JsonCleanOutputProcessor` as the
default; to change it (a default, or a processor for one route or content type), override it
in the project's `builder/Scripts.php` and rerun `php gluo openapi`:

```php
// builder/Scripts.php (class Scripts extends BaseScripts)
protected function getOpenApiRouteList(string $openApiPath): RouteListInterface
{
    return (new OpenApiRouteList($openApiPath))->withDefaultProcessor(JsonCleanOutputProcessor::class);
}
```

`JsonCleanOutputProcessor` is `JsonOutputProcessor` with `buildNull = false` — it strips null
values from the JSON output automatically (you don't need to call `withDoNotParseNullValues()`
on `Serialize` first).

---

## Input validation: #[ValidateRequest]

Add `#[ValidateRequest]` to any method that accepts a body. It runs before the method,
validates JSON/multipart bodies against the OpenAPI `requestBody` schema, and stores the
result. XML bodies are parsed but not schema-validated here; see [XML](xml.md).

```php
#[OA\Post(path: "/product", security: [["jwt-token" => []]], tags: ["product"])]
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(ref: "#/components/schemas/Product")
)]
#[OA\Response(response: 200, ...)]
#[RequireAuthenticated]
#[ValidateRequest]
public function postProduct(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();   // pre-validated array
    $model   = Config::get(ProductService::class)->create($payload);
    $response->write($model);
}
```

### What getPayload() returns by Content-Type

| Request Content-Type | Return type | Notes |
|---|---|---|
| `application/json` | `array` | nulls stripped by default |
| `multipart/form-data` | `array` | POST fields + uploaded file keys |
| `application/xml` / `text/xml` | `XmlDocument` | cannot be passed to ObjectCopy |

**YAML input is not supported.** The request pipeline has no YAML parser.

### Null stripping (default)

Validation checks the original JSON body first. If it passes, keys explicitly set to
`null` are removed before your code sees them:

```json
{"name": "Widget", "price": null}
```
```php
// getPayload() returns ['name' => 'Widget']
```

This is intentional: `BaseService::update()` uses `ObjectCopy::copy($payload, $model)` to
merge changes — absent keys leave existing field values untouched. To keep explicit nulls:

```php
#[ValidateRequest(preserveNullValues: true)]
```

### Multipart / file uploads

Use `OA\MediaType` with `format: "binary"` for file fields. `#[ValidateRequest]` merges POST
text fields and uploaded file keys into one array for schema validation; read the actual file
separately via `$request->uploadedFiles()`:

```php
#[OA\Post(path: "/avatar", security: [["jwt-token" => []]], tags: ["user"])]
#[OA\RequestBody(required: true,
    content: new OA\MediaType(
        mediaType: "multipart/form-data",
        schema: new OA\Schema(
            required: ["avatar"],
            properties: [
                new OA\Property(property: "name",   type: "string"),
                new OA\Property(property: "avatar", type: "string", format: "binary"),
            ]
        )
    ))]
#[OA\Response(response: 200, description: "OK",
    content: new OA\JsonContent(ref: "#/components/schemas/UploadResult"))]
#[RequireAuthenticated]
#[ValidateRequest]
public function postAvatar(HttpResponse $response, HttpRequest $request): void
{
    // getPayload() = ['name' => 'Widget', 'avatar' => 'avatar']
    // file fields appear as key => key (presence confirmed, not the file itself)
    $payload = ValidateRequest::getPayload();

    $files = $request->uploadedFiles();

    if (!$files->isOk('avatar')) {
        throw new Error400Exception('File upload failed: ' . $files->getErrorCode('avatar'));
    }

    $ext     = pathinfo($files->getFileName('avatar'), PATHINFO_EXTENSION);
    $newName = uniqid('avatar_') . '.' . $ext;
    $files->saveTo('avatar', '/var/uploads', $newName);

    $response->write(['filename' => $newName]);
}
```

**`UploadedFiles` API** (`$request->uploadedFiles()`):

| Method | Purpose |
|---|---|
| `isOk('field')` | `true` if upload succeeded (no PHP error) |
| `getFileName('field')` | Original filename from the client |
| `getFileType('field')` | MIME type (don't trust — validate with `finfo`) |
| `getFileSize('field')` | Size in bytes |
| `getUploadedFile('field')` | Read file contents as a string |
| `saveTo('field', '/path', 'newname.ext')` | Move temp file to destination |
| `clearTemp('field')` | Delete the temp file manually |
| `getErrorCode('field')` | PHP upload error code when `isOk()` is false |

If a required file field is missing, `#[ValidateRequest]` throws `Error400Exception` before
your method is called — no manual presence check needed for declared required fields.

### Validation errors

If the body doesn't satisfy the OpenAPI `requestBody` schema (missing required fields, wrong
types), `#[ValidateRequest]` throws `Error400Exception` before your method is called.

---

## Output content negotiation

`$response->write($data)` serializes the data using an output processor selected from the
request's `Accept` header. The processor is constrained to the content types advertised in
the route's `responses.200.content` — what you document is what clients can actually request.

### Supported output formats

| Accept header | Output processor | Content-Type returned |
|---|---|---|
| `application/json` or `*/*` | `JsonCleanOutputProcessor` (default) | `application/json` |
| `application/xml` or `text/xml` | `XmlOutputProcessor` | `application/xml` |
| `text/csv` | `CsvOutputProcessor` | `text/csv` |
| `text/html` | `HtmlOutputProcessor` | `text/html` |
| `text/plain` | `PlainTextOutputProcessor` | `text/plain` |

**YAML is not a supported output format.** There is no YAML output processor.

If a client sends `Accept: application/yaml`, the framework throws `OperationIdInvalidException`.
Only advertise formats you support in your OpenAPI response attributes.

### Documenting multiple output formats for one endpoint

Declare multiple `content` entries in the `#[OA\Response]` attribute:

```php
#[OA\Response(
    response: 200,
    description: "Product list",
    content: [
        new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Product")),
        new OA\MediaType(mediaType: "text/csv", schema: new OA\Schema(type: "string")),
    ]
)]
```

The framework then picks the processor that matches the `Accept` header. A client sending
`Accept: text/csv` gets CSV; one sending `Accept: application/json` (or `*/*`) gets JSON.

---

## OpenAPI attribute cheat sheet

### Route + security

```php
#[OA\Get(path: "/product/{id}", security: [["jwt-token" => []]], tags: ["product"])]
#[OA\Post(path: "/product",     security: [["jwt-token" => []]], tags: ["product"])]
#[OA\Put(path: "/product/{id}", security: [["jwt-token" => []]], tags: ["product"])]
#[OA\Delete(path: "/product/{id}", security: [["jwt-token" => []]], tags: ["product"])]
```

### Parameters

```php
// Path param
#[OA\Parameter(name: "id", in: "path", required: true,
    schema: new OA\Schema(type: "integer", format: "int32"))]

// Query param (optional)
#[OA\Parameter(name: "page", in: "query", required: false,
    schema: new OA\Schema(type: "integer"))]
```

### Request body

```php
// Reference a model only when its schema matches the intended request fields
#[OA\RequestBody(required: true,
    content: new OA\JsonContent(ref: "#/components/schemas/Product"))]

// Current generated writes use inline schemas containing only writable fields
#[OA\RequestBody(required: true,
    content: new OA\JsonContent(
        required: ["name"],
        properties: [new OA\Property(property: "name", type: "string")]
    ))]

// Multipart/form-data (file upload + text fields)
#[OA\RequestBody(required: true,
    content: new OA\MediaType(
        mediaType: "multipart/form-data",
        schema: new OA\Schema(
            required: ["avatar"],
            properties: [
                new OA\Property(property: "name",   type: "string"),
                new OA\Property(property: "avatar", type: "string", format: "binary"),
            ]
        )
    ))]
```

### Responses

```php
// Model ref
#[OA\Response(response: 200, description: "OK",
    content: new OA\JsonContent(ref: "#/components/schemas/Product"))]

// Array of model
#[OA\Response(response: 200, description: "OK",
    content: new OA\JsonContent(type: "array",
        items: new OA\Items(ref: "#/components/schemas/Product")))]

// Error (always include for 4xx endpoints)
#[OA\Response(response: 404, description: "Not Found",
    content: new OA\JsonContent(ref: "#/components/schemas/error"))]

// No body (e.g. PUT that returns 200 with nothing)
#[OA\Response(response: 200, description: "Updated")]
```

### Security attribute on a method

`security: [["jwt-token" => []]]` in the route attribute documents the JWT requirement in
the spec. The actual enforcement is done by `#[RequireAuthenticated]` or `#[RequireRole]`.
Both are needed — the OA attribute for documentation/contract, the PHP attribute for runtime.

---

## Client IP, user agent and persistent workers (byjg/restserver 7.0)

Read the client from the `HttpRequest` the route receives: `$request->getRequestIp()` and
`$request->getUserAgent()`. The static `HttpRequest::ip()` / `HttpRequest::userAgent()` are
removed (they read `$_SERVER`, which is wrong for a PSR-7 request).

`getRequestIp()` returns `REMOTE_ADDR` and ignores `X-Forwarded-For` (any client can send it).
Behind a load balancer, reverse proxy or CDN, name the proxies on the `Server` binding in
`config/{env}/03-api.php`, or every request has the proxy's address (rate limits, lockouts,
audit logs):

```php
Server::class => DI::bind(Server::class)
    // ...
    ->withMethodCall('withTrustedProxies', [['10.0.0.0/8', '172.16.0.0/12']])
    // RFC 7239 proxies: add TrustedProxies::FORWARDED as the second argument
```

The header is then read from the right, and the first address that is not a trusted proxy is
the client. Other headers (`Client-IP`, `CF-Connecting-IP`, ...) are never read.

`public/app.php` calls `Config::get(Application::class)->handle()`. A persistent worker
(RoadRunner, Swoole, a CLI worker) gets the `Application` once and calls
`handle($psr7Request)` for each request, since `php://input` is not the body there.

---

## Full lifecycle summary

```
1. Developer adds/changes OA attributes on a model or controller
   └── php gluo openapi
       ├── scans src/, writes public/docs/openapi.json
       └── compiles it into generated/routes.php (route table + output processors)
           and generated/openapi.php (the spec as a PHP array)

2. App startup (public/app.php → Config::get(Application::class)->handle())
   ├── CompiledRouteList loads generated/routes.php
   └── Schema::class loads generated/openapi.php
       └── used by #[ValidateRequest] for request body validation

3. HTTP request arrives
   ├── JwtMiddleware    parses/validates JWT → stores claims as request params
   ├── CompiledRouteList matches URL+method → selects Controller, method, output processor
   ├── #[RequireAuthenticated] or #[RequireRole]  → 401/403 if fails
   ├── #[ValidateRequest]
   │   ├── reads Content-Type header
   │   ├── parses body (JSON → array, XML → XmlDocument)
   │   ├── validates against the requestBody schema → 400 if fails
   │   └── stores result in ValidateRequest::$payload
   └── Controller method runs
       └── $response->write($data)
           ├── reads Accept header
           └── output processor serializes $data to JSON/XML/CSV/…
```
