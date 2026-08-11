---
sidebar_position: 190
title: Testing
---

# Testing Guide

This guide covers all aspects of testing your REST API, from unit tests to integration tests, using FakeApiRequester for in-process API testing.

## Overview

The reference architecture provides a complete testing framework that allows you to test your API **without** running a web server.

### Testing Approach

- **Integration Tests**: Test complete API endpoints using `FakeApiRequester`
- **Unit Tests**: Test services and business logic in isolation
- **Schema Validation**: Automatically validate responses against OpenAPI schema
- **In-Process**: No web server required, tests run directly in PHPUnit

### Key Components

| Component          | Purpose                  | Location                         |
|--------------------|--------------------------|----------------------------------|
| `FakeApiRequester` | In-process API testing   | `ByJG\Gluo\Util\FakeApiRequester` (byjg/gluo-core)  |
| `BaseApiTestCase`  | Base class for API tests | `tests/Controller/BaseApiTestCase.php` |
| `Credentials`      | Test user credentials    | `tests/Controller/Credentials.php`     |

## Test Structure

### Directory Layout

```
tests/
└── Controller/
    ├── BaseApiTestCase.php     # Base test case with schema + DB reset
    ├── Credentials.php         # Helper for authenticating test users
    ├── ProjectTest.php         # Repository pattern example CRUD tests (int PK)
    ├── TaskTest.php            # Repository pattern UUID example
    ├── NoteTest.php            # ActiveRecord pattern example
    ├── LoginTest.php           # Authentication flow
    └── ... (add your own files here)
```

:::info Want unit tests?
Add additional directories (e.g., `tests/Service`) as needed—PHPUnit's configuration already looks at the whole `tests/` tree.
:::

### Running Tests

```bash
# Create or reset the testing database
APP_ENV=test composer migrate -- reset --yes

# Run all tests using the composer script
APP_ENV=test composer run test

# Run a specific test file
APP_ENV=test vendor/bin/phpunit tests/Controller/ProjectTest.php

# Run a single test method
APP_ENV=test vendor/bin/phpunit --filter testFullCrud tests/Controller/ProjectTest.php

# Generate coverage (optional)
APP_ENV=test vendor/bin/phpunit --coverage-html coverage/
```

:::info Database resets automatically
`tests/Controller/BaseApiTestCase.php` already calls `Migration::reset()` the first time a test runs, but pre-resetting with the command above avoids surprises if you run the suite outside PHPUnit (e.g., invoking migrations manually).
:::

## FakeApiRequester

The `FakeApiRequester` class enables in-process API testing without a web server.

**Location**: `ByJG\Gluo\Util\FakeApiRequester` (byjg/gluo-core)

`BaseApiTestCase` extends `PHPUnit\Framework\TestCase` and mixes in the `OpenApiValidation` trait, so every call to `sendRequest()` validates the result against `public/docs/openapi.json`. All routing happens in-memory via `FakeApiRequester`, so you don't need a running web server—only a configured database.

### How It Works

1. **Creates PSR-7 Request**: Builds HTTP request object
2. **Routes to Controller**: Uses OpenAPI routing
3. **Executes Middleware**: Applies JWT authentication, validation
4. **Returns PSR-7 Response**: Returns HTTP response object
5. **Validates Schema**: Checks response against OpenAPI schema

### Basic Usage

```php
use ByJG\Gluo\Util\FakeApiRequester;

$request = (new FakeApiRequester())
    ->withPsr7Request($this->getPsr7Request())
    ->withMethod('GET')
    ->withPath('/project/1')
    ->withRequestHeader(['Authorization' => 'Bearer ' . $token])
    ->expectStatus(200);

$response = $this->sendRequest($request);
$data = json_decode($response->getBody()->getContents(), true);
```

### FakeApiRequester Methods

```php
// HTTP Method & Path
->withMethod('GET')             // GET, POST, PUT, DELETE, PATCH
->withPath('/api/products')     // API endpoint path

// Request Body
->withRequestBody(json_encode(['name' => 'Product']))

// Headers
->withRequestHeader(['Authorization' => 'Bearer token'])
->withRequestHeader(['Content-Type' => 'application/json'])

// Query Parameters
->withQuery(['page' => 2, 'size' => 50])

// Expected Response
->expectStatus(200)             // Assert HTTP status code
->expectJsonContains(['name' => 'Product'])
```

### Sending Body Data

```php
public function testPingWithBody()
{
    $request = (new FakeApiRequester())
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('POST')
        ->withPath('/sample/ping')
        ->withRequestBody(json_encode([
            'name' => 'John Doe'
        ]));

    $this->sendRequest($request);
}
```

### Sending Query Parameters

```php
public function testPingWithQuery()
{
    $request = (new FakeApiRequester())
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('GET')
        ->withPath('/sample/ping')
        ->withQuery(['name' => 'John Doe']);

    $this->sendRequest($request);
}
```

### Expecting a Specific Status Code

```php
public function testPingNotFound()
{
    $request = (new FakeApiRequester())
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('GET')
        ->withPath('/sample/ping')
        ->expectStatus(404);

    $this->sendRequest($request);
}
```

### Expecting a Specific Response Body

```php
public function testPingResponse()
{
    $request = (new FakeApiRequester())
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('GET')
        ->withPath('/sample/ping')
        ->expectJsonContains(['result' => 'pong']);

    $this->sendRequest($request);
}
```

## Writing API Tests

### BaseApiTestCase

All API tests should extend `Test\Controller\BaseApiTestCase`. The heavy
lifting (schema loading, OpenAPI validation, database reset, PSR-7 request
factory) lives in `ByJG\Gluo\Testing\BaseApiTestCase` (byjg/gluo-core);
your local class only points it at your project:

**Location**: `tests/Controller/BaseApiTestCase.php`

```php title="tests/Controller/BaseApiTestCase.php"
namespace Test\Controller;

use ByJG\Gluo\Testing\BaseApiTestCase as GluoBaseApiTestCase;

class BaseApiTestCase extends GluoBaseApiTestCase
{
    protected function getOpenApiPath(): string
    {
        return __DIR__ . '/../../public/docs/openapi.json';
    }
}
```

The package base class exposes overridable hooks:

```php
abstract protected function getOpenApiPath(): string;   // your openapi.json
protected function getDatabaseClass(): string;           // default: MySqlDatabase::class
protected function getMigrationsPath(): string;          // default: <project>/db
```

### What BaseApiTestCase Provides

```php
// PSR-7 Request Factory
$psr7Request = $this->getPsr7Request();

// Send a FakeApiRequester and validate against OpenAPI automatically
$response = $this->sendRequest($request);

// Reset the database once per test process
$this->resetDb();
```

### Basic Test Example

```php
<?php

namespace Test\Controller;

use ByJG\Gluo\Util\FakeApiRequester;

class SampleTest extends BaseApiTestCase
{
    public function testPing(): void
    {
        $request = (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath('/sample/ping')
            ->expectStatus(200);

        $this->sendRequest($request);
    }
}
```

## Testing Authentication

### Test User Credentials

**Location**: `tests/Controller/Credentials.php`

```php
use Test\Controller\Credentials;

// Admin user
$adminCreds = Credentials::getAdminUser();
// Returns: ['username' => 'admin@example.com', 'password' => '!P4ssw0rdstr!']

// Regular user
$userCreds = Credentials::getRegularUser();
// Returns: ['username' => 'user@example.com', 'password' => '!P4ssw0rdstr!']

// Login request
$loginRequest = Credentials::requestLogin(Credentials::getAdminUser());
$response = $this->sendRequest($loginRequest);
$data = json_decode($response->getBody()->getContents(), true);
$token = $data['token'];
```

You can override the default credentials via environment variables:

```bash
export TEST_ADMIN_USER=admin@example.com
export TEST_ADMIN_PASSWORD='!P4ssw0rdstr!'
export TEST_REGULAR_USER=user@example.com
export TEST_REGULAR_PASSWORD='!P4ssw0rdstr!'
```

### Testing Unauthorized Access

```php
public function testGetUnauthorized()
{
    $this->expectException(Error401Exception::class);
    $this->expectExceptionMessage('Absent authorization token');

    $request = new FakeApiRequester();
    $request
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('GET')
        ->withPath('/project/1')
        ->expectStatus(401);

    $this->sendRequest($request);
}
```

### Testing Invalid Credentials

```php
public function testLoginInvalidCredentials()
{
    $this->expectException(Error401Exception::class);
    $this->expectExceptionMessage('Username or password is invalid');

    $this->sendRequest(Credentials::requestLogin([
        'username' => 'invalid',
        'password' => 'wrong'
    ]));
}
```

### Testing Token Expiration

```php
public function testExpiredToken()
{
    $expiredToken = JwtWrapper::createToken([
        'userid' => 1,
        'name' => 'Test User',
        'role' => 'user'
    ], -3600); // Expired 1 hour ago

    $this->expectException(Error401Exception::class);

    $request = new FakeApiRequester();
    $request
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('GET')
        ->withPath('/products')
        ->withRequestHeader(['Authorization' => "Bearer {$expiredToken}"])
        ->expectStatus(401);

    $this->sendRequest($request);
}
```

## Testing Authorization

### Testing Role Requirements

```php
public function testInsufficientPrivileges()
{
    $this->expectException(Error403Exception::class);
    $this->expectExceptionMessage('Insufficient privileges');

    $loginResponse = $this->sendRequest(
        Credentials::requestLogin(Credentials::getRegularUser())
    );
    $data = json_decode($loginResponse->getBody()->getContents(), true);
    $token = $data['token'];

    $request = new FakeApiRequester();
    $request
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('DELETE')
        ->withPath('/products/1')
        ->withRequestHeader(['Authorization' => "Bearer {$token}"])
        ->expectStatus(403);

    $this->sendRequest($request);
}
```

## Testing CRUD Operations

### Complete CRUD Test

**Location**: `tests/Controller/ProjectTest.php`

Reads (`GET`) require `#[RequireAuthenticated]`, while writes (`POST`/`PUT`) require
`#[RequireRole(User::ROLE_ADMIN)]`, so the create/update steps below log in as the admin user.

```php
public function testFullCrud()
{
    // Login as admin (writes require the admin role)
    $loginResponse = $this->sendRequest(
        Credentials::requestLogin(Credentials::getAdminUser())
    );
    $loginData = json_decode($loginResponse->getBody()->getContents(), true);
    $token = $loginData['token'];

    // CREATE
    $createRequest = new FakeApiRequester();
    $createRequest
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('POST')
        ->withPath('/project')
        ->withRequestBody(json_encode(['name' => 'name', 'description' => 'description']))
        ->withRequestHeader(['Authorization' => "Bearer {$token}"])
        ->expectStatus(200);

    $createResponse = $this->sendRequest($createRequest);
    $created = json_decode($createResponse->getBody()->getContents(), true);
    $id = $created['id'];

    // READ
    $getRequest = new FakeApiRequester();
    $getRequest
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('GET')
        ->withPath("/project/{$id}")
        ->withRequestHeader(['Authorization' => "Bearer {$token}"])
        ->expectStatus(200);

    $getResponse = $this->sendRequest($getRequest);
    $retrieved = json_decode($getResponse->getBody()->getContents(), true);

    $this->assertEquals($id, $retrieved['id']);
    $this->assertEquals('name', $retrieved['name']);

    // UPDATE
    $retrieved['description'] = 'updated value';

    $updateRequest = new FakeApiRequester();
    $updateRequest
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('PUT')
        ->withPath('/project')
        ->withRequestBody(json_encode($retrieved))
        ->withRequestHeader(['Authorization' => "Bearer {$token}"])
        ->expectStatus(200);

    $this->sendRequest($updateRequest);

    // Verify update
    $verifyRequest = new FakeApiRequester();
    $verifyRequest
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('GET')
        ->withPath("/project/{$id}")
        ->withRequestHeader(['Authorization' => "Bearer {$token}"])
        ->expectStatus(200);

    $verifyResponse = $this->sendRequest($verifyRequest);
    $verified = json_decode($verifyResponse->getBody()->getContents(), true);

    $this->assertEquals('updated value', $verified['description']);
}
```

### Testing List & Pagination

```php
public function testList()
{
    $loginResponse = $this->sendRequest(
        Credentials::requestLogin(Credentials::getRegularUser())
    );
    $data = json_decode($loginResponse->getBody()->getContents(), true);

    $request = new FakeApiRequester();
    $request
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('GET')
        ->withPath('/project?page=0&size=20')
        ->withRequestHeader(['Authorization' => "Bearer {$data['token']}"])
        ->expectStatus(200);

    $response = $this->sendRequest($request);
    $list = json_decode($response->getBody()->getContents(), true);

    $this->assertIsArray($list);
    if (count($list) > 0) {
        $this->assertArrayHasKey('id', $list[0]);
    }
}
```

## Unit Testing Services

### Service Test Example

```php
<?php

namespace Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use RestReferenceArchitecture\Service\ProductService;
use RestReferenceArchitecture\Repository\ProductRepository;
use RestReferenceArchitecture\Model\Product;

class ProductServiceTest extends TestCase
{
    protected ProductService $service;
    protected ProductRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProductRepository::class);
        $this->service = new ProductService($this->repository);
    }

    public function testGetOrFail()
    {
        $product = new Product();
        $product->setId(1);
        $product->setName('Test Product');

        $this->repository
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $result = $this->service->getOrFail(1);

        $this->assertEquals(1, $result->getId());
        $this->assertEquals('Test Product', $result->getName());
    }

    public function testGetOrFailThrowsException()
    {
        $this->expectException(Error404Exception::class);

        $this->repository
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn(null);

        $this->service->getOrFail(999);
    }
}
```

## Test Data Management

### Using Helper Methods

```php
class ProductTest extends BaseApiTestCase
{
    protected function getSampleData(bool $array = false)
    {
        $sample = [
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 100
        ];

        if ($array) {
            return $sample;
        }

        $model = new Product();
        ObjectCopy::copy($sample, $model);
        return $model;
    }
}
```

### Seeding Test Data

```php
protected function seedTestData()
{
    $productService = Config::get(ProductService::class);

    for ($i = 1; $i <= 10; $i++) {
        $productService->create([
            'name' => "Product {$i}",
            'price' => $i * 10.00,
            'stock' => $i * 5
        ]);
    }
}
```

## Best Practices

### 1. Test One Thing Per Test

```php
// Good - Single responsibility
public function testCreateProduct() { /* ... */ }
public function testGetProduct() { /* ... */ }
public function testUpdateProduct() { /* ... */ }
```

### 2. Use Descriptive Test Names

```php
// Good - Clear intent
public function testCreateProductWithNegativePriceThrowsException() { }
public function testUserCannotDeleteOtherUsersProducts() { }
```

### 3. Arrange-Act-Assert Pattern

```php
public function testCreateProduct()
{
    // ARRANGE
    $loginResponse = $this->sendRequest(
        Credentials::requestLogin(Credentials::getAdminUser())
    );
    $token = json_decode($loginResponse->getBody()->getContents(), true)['token'];

    // ACT
    $request = new FakeApiRequester();
    $request
        ->withPsr7Request($this->getPsr7Request())
        ->withMethod('POST')
        ->withPath('/products')
        ->withRequestBody(json_encode($this->getSampleData(true)))
        ->withRequestHeader(['Authorization' => "Bearer {$token}"])
        ->expectStatus(200);

    $response = $this->sendRequest($request);

    // ASSERT
    $data = json_decode($response->getBody()->getContents(), true);
    $this->assertArrayHasKey('id', $data);
    $this->assertGreaterThan(0, $data['id']);
}
```

### 4. Use Data Providers for Similar Tests

```php
/**
 * @dataProvider invalidProductDataProvider
 */
public function testCreateWithInvalidData($payload, $expectedMessage)
{
    $this->expectException(Error400Exception::class);
    $this->expectExceptionMessage($expectedMessage);

    $productService = Config::get(ProductService::class);
    $productService->create($payload);
}

public function invalidProductDataProvider(): array
{
    return [
        'missing name' => [
            ['price' => 99.99],
            'Product name is required'
        ],
        'negative price' => [
            ['name' => 'Product', 'price' => -10],
            'Price cannot be negative'
        ],
    ];
}
```

### 5. Test Both Success and Failure Cases

```php
public function testCreateProductSuccess() { /* ... */ }
public function testCreateProductWithoutName() { /* ... */ }
public function testCreateProductWithInvalidPrice() { /* ... */ }
public function testCreateProductWithoutAuthentication() { /* ... */ }
public function testCreateProductWithoutAuthorization() { /* ... */ }
```

## Related Documentation

- [REST Controllers](rest-controllers.md)
- [Attributes System](../reference/attributes.md) - Testing validation attributes
- [Error Handling](error-handling.md) - Testing error responses
- [Service Layer](services.md) - Unit testing services
- [JWT Authentication Advanced](jwt-advanced.md) - Testing authentication
