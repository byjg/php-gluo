---
sidebar_position: 40
title: Create Your First Endpoint
---

# Add a New REST Method

In this tutorial, we'll create a new REST method to update the status of the `example_crud` table.

We'll cover the following topics:

- OpenAPI Attributes
- Protecting the endpoint
- Validating input
- Saving to the database
- Returning results
- Unit testing

## OpenAPI Attributes

First, we'll add OpenAPI attributes to our REST method using 
the [zircote/swagger-php](https://zircote.github.io/swagger-php/guide/) library.

The OpenAPI specification offers many attributes. At minimum, define these three essential sets:

### 1. Method Attribute

This defines the HTTP method:

- `OA\Get` - For retrieving data
- `OA\Post` - For creating data
- `OA\Put` - For updating data
- `OA\Delete` - For deleting/canceling data

Example:

```php
#[OA\Put(
    path: "/example/crud/status",
    security: [
        ["jwt-token" => []]
    ],
    tags: ["Example"],
    description: "Update the status of the ExampleCrud"
)]
```

The `security` attribute defines the security schema. Without it, the endpoint remains public.

### 2. Request Attribute

This defines the input to the method using `OA\RequestBody` or `OA\Parameter`.

Example:

```php
#[OA\RequestBody(
    description: "The status to be updated",
    required: true,
    content: new OA\JsonContent(
        required: ["status"],
        properties: [
            new OA\Property(property: "id", type: "integer", format: "int32"),
            new OA\Property(property: "status", type: "string")
        ]
    )
)]
```

### 3. Response Attribute

This defines the expected output using `OA\Response`.

```php
#[OA\Response(
    response: 200,
    description: "The operation result",
    content: new OA\JsonContent(
        required: ["result"],
        properties: [
            new OA\Property(property: "result", type: "string")
        ]
    )
)]
```

Place these attributes at the beginning of your method. Following our pattern, we'll add this method at the end of the `ExampleCrudController` class:

```php
#[OA\Put()]                 // complete with the attributes above
#[OA\RequestBody()]         // complete with the attributes above
#[OA\Response()]            // complete with the attributes above
public function putExampleCrudStatus(HttpResponse $response, HttpRequest $request)
{
    // Code to be added
}
```

## Protecting the Endpoint

If you've set the `security` property in your OpenAPI attributes, protect the endpoint using attributes:

```php
<?php

use ByJG\Gluo\Attribute\RequireAuthenticated;
use ByJG\Gluo\Attribute\RequireRole;
use ByJG\Gluo\Attribute\ValidateRequest;
use RestReferenceArchitecture\Model\User;

// Option a: Require admin role
#[RequireRole(User::ROLE_ADMIN)]
public function putExampleCrudStatus(HttpResponse $response, HttpRequest $request)
{
    // Your code here
}

// Option b: Require any authenticated user
#[RequireAuthenticated]
public function putExampleCrudStatus(HttpResponse $response, HttpRequest $request)
{
    // Your code here
}

// Option c: Public endpoint (no attribute)
public function putExampleCrudStatus(HttpResponse $response, HttpRequest $request)
{
    // Your code here
}
```

:::tip Attribute-Based Security
Using attributes is cleaner and more declarative than manual JWT validation. The attributes automatically:
- Validate JWT tokens
- Check user roles
- Throw appropriate exceptions (401 for invalid token, 403 for insufficient permissions)
:::

### Access JWT Data

If you need to access the current user's data:

```php
<?php

use ByJG\Gluo\Util\JwtContext;

// JwtContext helpers expose the middleware-populated JWT information
$userId = JwtContext::getUserId();
$userRole = JwtContext::getRole();  // "admin" or "user"
```

## Validating Input

Use the `#[ValidateRequest]` attribute to automatically validate the incoming request against your OpenAPI specification:

```php
<?php

#[RequireAuthenticated]
#[ValidateRequest]
public function putExampleCrudStatus(HttpResponse $response, HttpRequest $request)
{
    // Get the validated payload
    $payload = ValidateRequest::getPayload();

    // The payload is already validated against your OpenAPI spec
    // If validation fails, an Error400Exception is thrown automatically
}
```

## Updating Status Using the Service Layer

After validating the payload, use the service layer to update the record:

```php
<?php

use RestReferenceArchitecture\Service\ExampleCrudService;
use ByJG\Gluo\Attribute\RequireAuthenticated;
use ByJG\Gluo\Attribute\ValidateRequest;

// Declare the dependency once, in the constructor
public function __construct(protected ExampleCrudService $exampleCrudService)
{
}

/**
 * Update the status of an Example CRUD record
 */
#[RequireAuthenticated]
#[ValidateRequest]
public function putExampleCrudStatus(HttpResponse $response, HttpRequest $request)
{
    $payload = ValidateRequest::getPayload();

    // Use the service layer for business logic
    $model = $this->exampleCrudService->getOrFail($payload['id']);
    $model->setStatus($payload['status']);
    $this->exampleCrudService->save($model);

    $response->write(['result' => 'ok']);
}
```

:::tip No registration needed
`config/dev/07-controllers.php` autowires the whole controller namespace with a single
`Autowire::rule()`, so a new controller is resolved without an entry of its own.

Three things to keep in mind:

- **No-constructor controllers work too.** A controller that declares no constructor
  degrades to `withConstructorNoArgs()` automatically — an ActiveRecord-style controller
  needs no special case.
- **An explicit binding wins.** If a controller needs something the rule cannot express
  (a scalar constructor argument, say), add one by hand in the same file and it takes
  precedence over the pattern.
- **The namespace matters.** A controller placed outside the pattern's namespace is not
  covered and the route will fail with **501** rather than being built without its
  dependencies.

Keep your controllers in the expected namespace and the constructor is injected for you.
:::

:::tip Service Layer
Always use the Service Layer instead of directly accessing repositories. Services handle business logic and make your code more maintainable.
:::

## Returning the Response

After updating the record, we need to return a standardized response as specified in our OpenAPI schema:

```php
public function putExampleCrudStatus(HttpResponse $response, HttpRequest $request) 
{
    // Previous code for update logic...
    
    // Return standardized response
    $response->write([
        "result" => "ok"
    ]);
}
```

## Functional Testing

Create a functional test to ensure your endpoint works correctly and continues to function as expected.

Create or update the test file `tests/Controller/ExampleCrudTest.php`:

```php
<?php

namespace Test\Controller;

use ByJG\Config\Config;
use RestReferenceArchitecture\Service\ExampleCrudService;
use ByJG\Gluo\Util\FakeApiRequester;
use Test\Controller\BaseApiTestCase;
use Test\Controller\Credentials;

class ExampleCrudTest extends BaseApiTestCase
{
    public function testUpdateStatus()
    {
        // Authenticate to get a valid token (if the endpoint requires auth)
        $authResult = json_decode(
            $this->assertRequest(Credentials::requestLogin(Credentials::getAdminUser()))
                ->getBody()
                ->getContents(),
            true
        );

        // Prepare test data
        $recordId = 1;
        $newStatus = 'active';

        // Create mock API request
        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('PUT')
            ->withPath("/example/crud/status")
            ->withBody([
                'id' => $recordId,
                'status' => $newStatus
            ])
            ->withRequestHeader([
                "Authorization" => "Bearer " . $authResult['token']
            ])
            ->assertResponseCode(200);

        // Execute the request
        // assertRequest automatically validates response against OpenAPI spec
        $this->assertRequest($request);

        // Verify the database was updated correctly
        $service = Config::get(ExampleCrudService::class);
        $updatedRecord = $service->get($recordId);
        $this->assertEquals($newStatus, $updatedRecord->getStatus());
    }
}
```

:::tip Automatic Validation
The `assertRequest()` method automatically validates:
- Response status code matches OpenAPI specification
- Response body structure matches OpenAPI schema
- No need for manual assertions on response format!
:::

## Run the Tests

Update the OpenAPI specification and run the tests:

```bash
composer run openapi
APP_ENV=test composer run test
```

All tests should pass successfully!

:::caution Regeneration is not optional
Routing is read from `public/docs/openapi.json`, not from your PHP. Change a controller
attribute without running `composer run openapi` and the endpoint returns **404 with no
error message**. `composer test` runs `composer openapi:check` first, which warns when the
spec is older than your controllers or models — you can also run it on its own.
:::
