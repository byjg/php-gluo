---
sidebar_position: 120
title: REST Controllers
---

# REST Controllers

REST controllers in this architecture map HTTP routes to PHP methods using PHP 8 attributes. Every controller method receives `HttpRequest` and `HttpResponse` objects and delegates business logic to the Service layer.

## Defining Routes with PHP Attributes

Annotate controller classes with `zircote/swagger-php` attributes to describe each endpoint. The tooling generates `public/docs/openapi.json` from these annotations, and `OpenApiRouteList` uses that file to dispatch requests at runtime.

```php
namespace RestReferenceArchitecture\Controller;

use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpResponse;
use OpenApi\Attributes as OA;

class LoginController
{
    /**
     * Do log in
     */
    #[OA\Post(
        path: "/login",
        tags: ["Login"],
    )]
    #[OA\RequestBody(
        description: "The Login Data",
        required: true,
        content: new OA\JsonContent(
            required: [ "username", "password" ],
            properties: [
                new OA\Property(property: "username", description: "The Username", type: "string", format: "string"),
                new OA\Property(property: "password", description: "The Password",  type: "string", format: "string")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Login result",
        content: new OA\JsonContent(
            required: [ "token" ],
            properties: [
                new OA\Property(property: "token", type: "string"),
                new OA\Property(property: "data", properties: [
                    new OA\Property(property: "userid", type: "string"),
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "role", type: "string"),
                ])
            ]
        )
    )]
    public function mymethod(HttpRequest $request, HttpResponse $response): void
    {
        // ...
    }
}
```

## Path and Query Parameters

Declare path parameters directly in the path string and use `#[OA\Parameter]` to describe them:

```php
#[OA\Get(path: "/project/{id}", tags: ["Project"])]
#[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
#[OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"))]
#[OA\Response(response: 200, description: "The project object")]
public function getProject(HttpResponse $response, HttpRequest $request): void
{
    $id = $request->attribute('id');
    $page = $request->queryString('page');
    // ...
}
```

## Request Bodies

Use `#[OA\RequestBody]` with `#[OA\JsonContent]` to define the expected payload shape:

```php
#[OA\Post(path: "/project", tags: ["Project"])]
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(ref: "#/components/schemas/Project")
)]
#[OA\Response(response: 200, description: "Created project")]
public function postProject(HttpResponse $response, HttpRequest $request): void
{
    // ...
}
```

## Response Schemas

Document every response status code so the OpenAPI spec (and test validation) stays accurate:

```php
#[OA\Response(
    response: 200,
    description: "Success",
    content: new OA\JsonContent(ref: "#/components/schemas/Project")
)]
#[OA\Response(response: 404, description: "Not found")]
#[OA\Response(response: 422, description: "Validation error")]
```

## Request Validation with `#[ValidateRequest]`

Add `#[ValidateRequest]` to a controller method to automatically validate the incoming request body against the OpenAPI schema before the method executes. Invalid payloads receive a 422 response.

```php
use ByJG\Gluo\Attribute\ValidateRequest;

#[ValidateRequest]
public function postProject(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();
    $model = $this->projectService->create($payload);
    $response->write($model);
}
```

## Requiring Authentication with `#[RequireAuthenticated]`

Add `#[RequireAuthenticated]` to protect an endpoint. Requests without a valid JWT receive a 401 response.

```php
use ByJG\Gluo\Attribute\RequireAuthenticated;

#[RequireAuthenticated]
public function getProject(HttpResponse $response, HttpRequest $request): void
{
    $result = $this->projectService->getOrFail($request->attribute('id'));
    $response->write($result);
}
```

## Getting Services via Constructor Injection

The Server resolves controllers from the PSR-11 container, so declare what the controller
needs in its constructor and use it from any method:

```php
use RestReferenceArchitecture\Service\ProjectService;

class ProjectController
{
    public function __construct(protected ProjectService $projectService)
    {
    }

    public function getProject(HttpResponse $response, HttpRequest $request): void
    {
        $result = $this->projectService->getOrFail($request->attribute('id'));
        $response->write($result);
    }
}
```

No registration is needed. `config/dev/07-controllers.php` carries one pattern rule that
covers the whole controller namespace:

```php
'RestReferenceArchitecture\Controller\*' => Autowire::rule()
    ->withInjectedConstructor()   // resolve constructor args from type hints
    ->toInstance(),               // per-request, not shared
```

A controller with no constructor (an ActiveRecord one, for instance) degrades to
`withConstructorNoArgs()` automatically. If a controller needs something the rule cannot
express — a scalar constructor argument, say — add an explicit binding in the same file
and it wins over the pattern.

A controller placed outside that namespace is not covered, and the route fails with
**501** naming the class rather than being built without its dependencies.

## Working with `HttpRequest` and `HttpResponse`

```php
// Path parameter (from the URL pattern, e.g. /project/{id})
$id = $request->attribute('id');

// Query string parameter (from ?page=2)
$page = $request->queryString('page');

// Request body as string
$rawBody = $request->payload();

// Write a model or array to the response (serialized to JSON)
$response->write($model);

// Write a scalar
$response->write(['result' => 'ok']);
```

## Using an Existing OpenAPI Specification

If you already have an OpenAPI JSON spec, place it at `public/docs/openapi.json`. Set the `operationId` for each path to route requests to the correct controller method:

```json
{
    "paths": {
        "/login": {
            "post": {
                "operationId": "POST::/login::RestReferenceArchitecture\\Controller\\LoginController::mymethod"
            }
        }
    }
}
```

The `operationId` format is:
```
<HTTP Method>::<path>::<Fully\Qualified\ClassName>::<methodName>
```

## Related Documentation

- [OpenAPI Integration](../concepts/openapi-integration.md) - How the spec is generated and used at runtime
- [Attributes Reference](../reference/attributes.md) - Full attribute reference
- [Authentication](authentication.md) - JWT and RBAC setup
- [Service Layer](services.md) - Keeping controllers thin
