# Testing Guide

## Overview

Tests are in-process (no real HTTP, no Docker port binding). `FakeApiRequester` routes
requests through the same `JwtMiddleware` + `OpenApiRouteList` as production, so they
exercise routing, auth attributes, controllers, and OpenAPI contracts. The harness builds
its own `MockServer` and attaches JWT middleware; it does not inherit all middleware from
the application's `Server` binding. Test CORS or custom middleware separately when relevant.

**Base class:** `Tests\Controller\BaseApiTestCase` — a thin subclass of `ByJG\Gluo\Testing\BaseApiTestCase` overriding only `getOpenApiPath()`; the gluo base provides the PHPUnit TestCase + OpenApiValidation ancestry, database reset, and the `getDatabaseClass()`/`getMigrationsPath()` hooks
**Request class:** `ByJG\Gluo\Util\FakeApiRequester`

## Test class setup

```php
namespace Tests\Controller;

use ByJG\RestServer\Exception\Error401Exception;
use ByJG\Gluo\Util\FakeApiRequester;

class ProductTest extends BaseApiTestCase
{
    // parent::setUp() in BaseApiTestCase calls $this->resetDb() automatically.
    // resetDb() resets the DB once per test run (subsequent calls are no-ops).
    // Only override setUp() if you need to seed additional data.
}
```

## Sending requests

`$this->sendRequest($request)` returns a PSR-7 `ResponseInterface`. It also validates
the request/response against the OpenAPI schema — if your response shape doesn't match
the documented schema, the test fails.

```php
$response = $this->sendRequest($request);
$body     = $response->getBody()->getContents();    // raw JSON string
$data     = json_decode($body, true);               // decoded array
```

Note: `assertRequest()`, the former name of `sendRequest()`, is removed in byjg/swagger-test 7.0.

## Login helper pattern

```php
private function loginAs(array $credentials): string
{
    $response = $this->sendRequest(Credentials::requestLogin($credentials));
    $data = json_decode($response->getBody()->getContents(), true);
    return $data['token'];
}

// Usage:
$adminToken = $this->loginAs(Credentials::getAdminUser());
$userToken  = $this->loginAs(Credentials::getRegularUser());
```

## FakeApiRequester fluent API

```php
$request = (new FakeApiRequester())
    ->withPsr7Request($this->getPsr7Request())  // base URI from config
    ->withMethod('POST')
    ->withPath('/product')
    ->withRequestBody(json_encode(['name' => 'Widget']))
    ->withRequestHeader(['Authorization' => "Bearer $token"])
    ->expectStatus(200);                         // default is 200

$response = $this->sendRequest($request);
```

**Other methods:**
- `->withQuery(['page' => 0, 'size' => 10])` — query string params
- `->expectStatus(404)` — asserts the HTTP status code
- `->expectBodyContains('Widget')` — asserts substring in response body
- `->expectJsonContains(['name' => 'Widget'])` — asserts JSON key presence
- `->expectJsonPath('data.0.name', 'Widget')` — asserts dot-notation path value

## Common test patterns

### Unauthenticated → 401
```php
public function testGetUnauthorized(): void
{
    $this->expectException(Error401Exception::class);
    $this->expectExceptionMessage('Absent authorization token');

    $this->sendRequest(
        (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')->withPath('/product/1')
            ->expectStatus(401)
    );
}
```

### Insufficient role → 403
```php
public function testPostForbiddenForRegularUser(): void
{
    $this->expectException(Error403Exception::class);

    $token = $this->loginAs(Credentials::getRegularUser());

    $this->sendRequest(
        (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')->withPath('/product')
            ->withRequestBody(json_encode(['name' => 'Widget']))
            ->withRequestHeader(['Authorization' => "Bearer $token"])
            ->expectStatus(403)
    );
}
```

### Full CRUD flow
```php
public function testFullCrud(): void
{
    $result = json_decode(
        $this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))
            ->getBody()->getContents(),
        true
    );
    $auth = ['Authorization' => "Bearer " . $result['token']];

    // Create
    $createBody = json_decode($this->sendRequest(
        (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')->withPath('/product')
            ->withRequestBody(json_encode(['name' => 'Widget', 'price' => 9.99]))
            ->withRequestHeader($auth)
            ->expectStatus(200)
    )->getBody()->getContents(), true);
    $id = $createBody['id'];

    // Read
    $getBody = json_decode($this->sendRequest(
        (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')->withPath("/product/$id")
            ->withRequestHeader($auth)
            ->expectStatus(200)
    )->getBody()->getContents(), true);
    $this->assertEquals('Widget', $getBody['name']);

    // Update (PUT /product/{id}: the key in the path, the same fields as the POST in the body)
    $this->sendRequest(
        (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('PUT')->withPath("/product/$id")
            ->withRequestBody(json_encode(['name' => 'Widget Pro', 'price' => 9.99]))
            ->withRequestHeader($auth)
            ->expectStatus(200)
    );
}
```

### List with pagination
```php
public function testList(): void
{
    $result = json_decode(
        $this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))
            ->getBody()->getContents(),
        true
    );

    $response = $this->sendRequest(
        (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')->withPath('/product')
            ->withQuery(['page' => 0, 'size' => 10])
            ->withRequestHeader(['Authorization' => "Bearer " . $result['token']])
            ->expectStatus(200)
    );
    $this->assertIsArray(json_decode($response->getBody()->getContents(), true));
}
```

## Invalid bodies must reach the server

`sendRequest()` attaches the OpenAPI contract and can reject an invalid body on the client
side before the controller runs. To verify server-side validation, create a fresh requester
and call `send()` directly, without passing it through `sendRequest()`:

```php
$token = $this->loginAs(Credentials::getAdminUser());
$request = (new FakeApiRequester())
    ->withPsr7Request($this->getPsr7Request())
    ->withMethod('POST')->withPath('/product')
    ->withRequestBody(json_encode(['name' => '']))
    ->withRequestHeader(['Authorization' => "Bearer $token"]);

$this->expectException(\ByJG\RestServer\Exception\Error400Exception::class);
$request->send();
```

The in-process harness propagates `Error400Exception`; this checks the server validation
path, not serialization of an HTTP error response. For assertions that storage is unchanged,
catch the expected exception and then compare database state. Test both POST and PUT,
using a real existing key in the PUT path. Keep response-contract tests through
`sendRequest()` for valid requests.

## Credentials

Default test users (can be overridden with env vars):
- Admin: `admin@example.com` / `!P4ssw0rdstr!`
- Regular: `user@example.com` / `!P4ssw0rdstr!`

Seeded in `db/base.sql`. The first `resetDb()` call resets the database once per PHP
process; later calls are no-ops. Tests share database state, so seed or clean the data each
test needs and avoid depending on execution order.

## Running tests

```bash
docker compose up -d          # if the selected database requires a running service
php gluo test                # all tests, with the OpenAPI freshness check first

# Single file:
php vendor/bin/phpunit tests/Controller/ProductTest.php

# Single method:
php vendor/bin/phpunit --filter testCreate tests/Controller/ProductTest.php
```

## Reference test files

| File | What it tests |
|------|--------------|
| `tests/Controller/ProjectTest.php` | Repository pattern, int PK (auth, CRUD, list) |
| `tests/Controller/NoteTest.php` | ActiveRecord pattern |
| `tests/Controller/TaskTest.php` | Repository pattern, UUID PK |
| `tests/Controller/LoginTest.php` | Login, refresh token, password reset |
