# Testing Guide

## Overview

Tests are in-process (no real HTTP, no Docker port binding). `FakeApiRequester` routes
requests through the same `JwtMiddleware` + `OpenApiRouteList` as production, so they
test the full stack including auth attribute processing and OpenAPI contract validation.

**Base class:** `Test\Controller\BaseApiTestCase` — a thin subclass of `ByJG\Gluo\Testing\BaseApiTestCase` overriding only `getOpenApiPath()`; the gluo base provides the PHPUnit TestCase + OpenApiValidation ancestry, database reset, and the `getDatabaseClass()`/`getMigrationsPath()` hooks
**Request class:** `ByJG\Gluo\Util\FakeApiRequester`

## Test class setup

```php
namespace Test\Controller;

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

Note: `assertRequest()` still works (delegates to `sendRequest()`), but it's deprecated
since 6.0 — prefer `sendRequest()`.

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

    // Update (PUT sends full object back)
    $this->sendRequest(
        (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('PUT')->withPath('/product')
            ->withRequestBody(json_encode($getBody + ['name' => 'Widget Pro']))
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

## Credentials

Default test users (can be overridden with env vars):
- Admin: `admin@example.com` / `!P4ssw0rdstr!`
- Regular: `user@example.com` / `!P4ssw0rdstr!`

Seeded in `db/base.sql`, recreated on every `resetDb()` call.

## Running tests

```bash
docker compose up -d          # MySQL must be running
php vendor/bin/phpunit        # all tests

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