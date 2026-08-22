---
sidebar_position: 410
title: Attributes Reference
---

# Attributes System

Attributes (also known as PHP 8 Attributes or Annotations) provide a powerful way to add metadata and behavior to your REST API methods. This reference architecture includes two custom attributes that integrate with the ByJG RestServer framework.

## Table of Contents

- [Overview](#overview)
- [ValidateRequest Attribute](#validaterequest-attribute)
- [RequireRole Attribute](#requirerole-attribute)
- [Combining Attributes](#combining-attributes)
- [Creating Custom Attributes](#creating-custom-attributes)
- [Error Handling](#error-handling)

## Overview

Attributes are applied directly to REST controller methods using PHP 8 attribute syntax. They execute before your method runs, allowing you to:

- Validate incoming request data
- Enforce authentication and authorization
- Transform request payloads
- Apply cross-cutting concerns

## ValidateRequest Attribute

The `ValidateRequest` attribute automatically validates incoming requests against your OpenAPI schema definition.

### Location

`ByJG\Gluo\Attribute\ValidateRequest` (byjg/gluo-core)

### Usage

```php
use ByJG\Gluo\Attribute\ValidateRequest;

#[ValidateRequest]
public function postProject(HttpResponse $response, HttpRequest $request): void
{
    // Get the validated payload
    $payload = ValidateRequest::getPayload();

    $projectService = Config::get(ProjectService::class);
    $model = $projectService->create($payload);
    $response->write(["id" => $model->getId()]);
}
```

### How It Works

1. **Automatic Validation**: Validates the request body against the OpenAPI schema for this endpoint
2. **Content-Type Awareness**: Returns different formats based on the request content-type:
   - **XML**: Returns `XmlDocument` object
   - **JSON/Other**: Returns associative array
3. **Error Response**: Throws `Error400Exception` if validation fails

### Constructor Parameters

```php
#[ValidateRequest(preserveNullValues: true)]
```

- **`preserveNullValues`** (bool, default: `false`): If `false`, null values are removed from the payload. If `true`, null values are preserved.

### Retrieving the Validated Payload

After validation, retrieve the payload using the static method:

```php
$payload = ValidateRequest::getPayload();
```

### Example: JSON Request

```php
#[OA\Post(path: "/project", tags: ["Project"])]
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ["field"],
        properties: [
            new OA\Property(property: "field", type: "string"),
            new OA\Property(property: "optional", type: "integer")
        ]
    )
)]
#[ValidateRequest]
public function postProject(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();
    // $payload = ['field' => 'value', 'optional' => 123]

    $field = $payload['field'];  // Guaranteed to exist due to validation
}
```

### Example: XML Request

```php
#[OA\Post(path: "/project", tags: ["Project"])]
#[OA\RequestBody(
    required: true,
    content: new OA\XmlContent(
        xml: new OA\Xml(name: "ProjectRequest")
    )
)]
#[ValidateRequest]
public function postProjectXml(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();
    // $payload = XmlDocument object

    $field = $payload->xpath("//field")[0]->nodeValue;
}
```

### Example: Partial Update (Default - Recommended)

```php
#[OA\Put(path: "/clients/{id}", tags: ["Clients"])]
#[ValidateRequest]  // preserveNullValues defaults to false (recommended for updates)
public function updateClient(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();

    // Client sends partial update:
    // {"name": "Updated Name", "email": null, "phone": "555-1234"}

    // With preserveNullValues: false (default)
    // Payload becomes: ["name" => "Updated Name", "phone" => "555-1234"]
    // Note: "email" with null is REMOVED

    // Add primary key to payload (service will use it to fetch the record)
    $payload['id'] = $request->attribute('id');

    // Service update() does: getOrFail() + ObjectCopy::copy() + save()
    $clientService = Config::get(ClientService::class);
    $client = $clientService->update($payload);
    // Result: name and phone updated, email unchanged (not set to null)

    $response->write($client);
}
```

### Example: Full Update with Explicit Nulls

```php
#[OA\Put(path: "/clients/{id}", tags: ["Clients"])]
#[ValidateRequest(preserveNullValues: true)]  // Keep null values
public function updateClient(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();

    // Client sends:
    // {"name": "Updated Name", "email": null, "phone": "555-1234"}

    // With preserveNullValues: true
    // Payload stays: ["name" => "Updated Name", "email" => null, "phone" => "555-1234"]
    // Note: "email" with null is KEPT

    // Add primary key to payload (service will use it to fetch the record)
    $payload['id'] = $request->attribute('id');

    // Service update() does: getOrFail() + ObjectCopy::copy() + save()
    $clientService = Config::get(ClientService::class);
    $client = $clientService->update($payload);
    // Result: name and phone updated, email set to null (cleared)

    $response->write($client);
}
```

### When to Use Each

**Use `preserveNullValues: false` (default)** - Partial updates:
```php
// Client wants to update only name and phone, leave email as-is
PUT /clients/123
{"name": "John Doe", "phone": "555-1234"}

// With preserveNullValues: false
// Only name and phone are updated, other fields unchanged ✓
```

**Use `preserveNullValues: true`** - Explicit field clearing:
```php
// Client wants to clear the email field
PUT /clients/123
{"name": "John Doe", "email": null, "phone": "555-1234"}

// With preserveNullValues: true
// Email is explicitly set to null (cleared) ✓

// With preserveNullValues: false
// Email would be ignored, not cleared ✗
```

### What Gets Validated

The attribute validates:

- **Required fields**: Ensures all required properties are present
- **Data types**: Verifies types match the schema (string, integer, boolean, etc.)
- **Format constraints**: Validates formats like email, date-time, uuid
- **Enums**: Checks values against allowed enum values
- **Nested objects**: Recursively validates nested structures
- **Arrays**: Validates array items against schema

### Validation Error Response

If validation fails, the client receives a 400 error:

```json
{
    "error": "Bad Request",
    "message": "Validation failed: field 'email' must be a valid email address"
}
```

## RequireRole Attribute

The `RequireRole` attribute enforces role-based access control (RBAC) for protected endpoints.

### Location

`ByJG\Gluo\Attribute\RequireRole` (byjg/gluo-core)

### Usage

```php
use ByJG\Gluo\Attribute\RequireRole;
use RestReferenceArchitecture\Model\User;

#[RequireRole(User::ROLE_ADMIN)]
public function postProject(HttpResponse $response, HttpRequest $request): void
{
    // This method is only accessible to users with ROLE_ADMIN
}
```

### How It Works

1. **JWT Token Required**: User must be authenticated with a valid JWT token
2. **Role Extraction**: Extracts the `role` claim from the JWT payload
3. **Role Comparison**: Compares against the required role
4. **Access Denied**: Throws `Error403Exception` if role doesn't match

### Constructor Parameters

```php
#[RequireRole("admin")]
```

- **`role`** (string, required): The role required to access this endpoint

### Predefined Roles

The `User` model defines standard roles:

```php
namespace RestReferenceArchitecture\Model;

class User
{
    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';
}
```

### Example: Admin-Only Endpoint

```php
#[OA\Delete(path: "/project/{id}", tags: ["Project"])]
#[RequireRole(User::ROLE_ADMIN)]
public function deleteProject(HttpResponse $response, HttpRequest $request): void
{
    // Only admins can delete
    $projectService = Config::get(ProjectService::class);
    $projectService->delete($request->attribute('id'));
}
```

### Example: Custom Roles

```php
class MyUser extends User
{
    const ROLE_MODERATOR = 'moderator';
    const ROLE_GUEST = 'guest';
}

// In your controller
#[RequireRole(MyUser::ROLE_MODERATOR)]
public function moderateContent(HttpResponse $response, HttpRequest $request): void
{
    // Only moderators can access
}
```

### JWT Token Structure

The JWT token must contain a `role` claim:

```json
{
    "userid": 1,
    "name": "John Doe",
    "role": "admin"
}
```

See [JWT Advanced Guide](../guides/jwt-advanced.md) for customizing JWT claims.

### Error Responses

**403 Forbidden** (wrong role):
```json
{
    "error": "Forbidden",
    "message": "Insufficient permissions"
}
```

**401 Unauthorized** (no token):
```json
{
    "error": "Unauthorized",
    "message": "Authentication required"
}
```

## Combining Attributes

Attributes can be combined to create powerful authorization and validation chains.

### Execution Order

Attributes execute in this order:
1. Authentication checks (`RequireAuthenticated`, `RequireRole`)
2. Request validation (`ValidateRequest`)
3. Your method logic

### Example: Admin-Only with Validation

```php
#[OA\Post(path: "/users", tags: ["Users"])]
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ["email", "name"],
        properties: [
            new OA\Property(property: "email", type: "string", format: "email"),
            new OA\Property(property: "name", type: "string")
        ]
    )
)]
#[RequireRole(User::ROLE_ADMIN)]
#[ValidateRequest]
public function createUser(HttpResponse $response, HttpRequest $request): void
{
    // 1. User is authenticated and has admin role
    // 2. Request body is validated
    // 3. Now execute your logic

    $payload = ValidateRequest::getPayload();
    // Create user...
}
```

### Example: Multiple Authorization Checks

```php
use ByJG\Gluo\Attribute\RequireAuthenticated;

#[RequireAuthenticated]  // Must be logged in
#[RequireRole(User::ROLE_ADMIN)]  // Must be admin
#[ValidateRequest]  // Request must be valid
public function sensitiveOperation(HttpResponse $response, HttpRequest $request): void
{
    // Triple protection
}
```

## Creating Custom Attributes

You can create custom attributes to implement your own cross-cutting concerns.

### Step 1: Create the Attribute Class

```php
<?php

namespace RestReferenceArchitecture\Attribute;

use Attribute;
use ByJG\RestServer\Attributes\BeforeRouteInterface;
use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpResponse;
use ByJG\RestServer\Exception\Error429Exception;

#[Attribute(Attribute::TARGET_METHOD)]
class RateLimit implements BeforeRouteInterface
{
    protected int $maxRequests;
    protected int $windowSeconds;

    public function __construct(int $maxRequests = 100, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function processBefore(HttpResponse $response, HttpRequest $request): void
    {
        $clientId = $request->server('REMOTE_ADDR');

        // Check rate limit (pseudo-code)
        if ($this->isRateLimited($clientId)) {
            throw new Error429Exception("Rate limit exceeded");
        }

        $this->recordRequest($clientId);
    }

    protected function isRateLimited(string $clientId): bool
    {
        // Implement your rate limiting logic
        // Could use Redis, Memcached, or database
        return false;
    }

    protected function recordRequest(string $clientId): void
    {
        // Record the request timestamp
    }
}
```

### Step 2: Use Your Custom Attribute

```php
use RestReferenceArchitecture\Attribute\RateLimit; // your custom attribute (not part of gluo-core)

#[OA\Post(path: "/api/heavy", tags: ["API"])]
#[RateLimit(maxRequests: 10, windowSeconds: 60)]
public function heavyOperation(HttpResponse $response, HttpRequest $request): void
{
    // This endpoint is rate-limited to 10 requests per minute
}
```

### Custom Attribute with Payload

```php
<?php

namespace RestReferenceArchitecture\Attribute;

use Attribute;
use ByJG\RestServer\Attributes\BeforeRouteInterface;
use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpResponse;

#[Attribute(Attribute::TARGET_METHOD)]
class SanitizeInput implements BeforeRouteInterface
{
    protected static ?array $sanitizedData = null;

    public function processBefore(HttpResponse $response, HttpRequest $request): void
    {
        $payload = $request->payload();

        self::$sanitizedData = $this->sanitize($payload);
    }

    protected function sanitize(array $data): array
    {
        // Strip HTML tags, trim whitespace, etc.
        return array_map(function($value) {
            if (is_string($value)) {
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
            return $value;
        }, $data);
    }

    public static function getData(): ?array
    {
        return self::$sanitizedData;
    }
}
```

### Usage:

```php
#[SanitizeInput]
#[ValidateRequest]
public function createPost(HttpResponse $response, HttpRequest $request): void
{
    $sanitized = SanitizeInput::getData();
    $validated = ValidateRequest::getPayload();

    // Use sanitized and validated data
}
```

## Error Handling

### Validation Errors

When `ValidateRequest` fails:

```php
try {
    // ValidateRequest attribute runs automatically
} catch (Error400Exception $e) {
    // {
    //     "error": "Bad Request",
    //     "message": "Validation failed: ..."
    // }
}
```

### Authorization Errors

When `RequireRole` fails:

```php
try {
    // RequireRole attribute runs automatically
} catch (Error403Exception $e) {
    // {
    //     "error": "Forbidden",
    //     "message": "Insufficient permissions"
    // }
}
```

### Customizing Error Messages

Override the error handling in your custom attributes:

```php
public function processBefore(HttpResponse $response, HttpRequest $request): void
{
    if (!$this->validateSomething()) {
        throw new Error400Exception("Custom error message with details");
    }
}
```

### Global Error Handler

Configure global error handling in `config/<env>/03-api.php`:

```php
use ByJG\RestServer\ErrorHandler\ErrorHandler;

$errorHandler = new ErrorHandler();
$errorHandler->addHandler(function(\Throwable $ex, $request, $response) {
    // Custom error logging
    error_log($ex->getMessage());

    // Custom error response format
    return [
        'status' => 'error',
        'code' => $ex->getCode(),
        'message' => $ex->getMessage(),
        'timestamp' => date('c')
    ];
});
```

## Best Practices

### 1. Always Validate User Input

```php
// Good
#[ValidateRequest]
public function createResource(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();
    // Guaranteed valid data
}

// Bad - No validation
public function createResource(HttpResponse $response, HttpRequest $request): void
{
    $payload = $request->payload();
    // Could be malicious or malformed
}
```

### 2. Use Role Constants

```php
// Good
#[RequireRole(User::ROLE_ADMIN)]

// Bad - Magic strings
#[RequireRole("admin")]
```

### 3. Order Attributes Logically

```php
// Good - Authentication before validation
#[RequireRole(User::ROLE_ADMIN)]
#[ValidateRequest]
public function createResource(...) { }

// Works but less efficient - validates before checking auth
#[ValidateRequest]
#[RequireRole(User::ROLE_ADMIN)]
public function createResource(...) { }
```

### 4. Document Required Roles in OpenAPI

```php
#[OA\Post(
    path: "/admin/users",
    security: [["jwt-token" => []]],
    tags: ["Admin"]
)]
#[OA\Response(
    response: 403,
    description: "Requires admin role"
)]
#[RequireRole(User::ROLE_ADMIN)]
public function manageUsers(...) { }
```

## Related Documentation

- [JWT Authentication Advanced Guide](../guides/jwt-advanced.md)
- [REST API Development & OpenAPI Integration](../guides/rest-controllers.md)
- [Testing Guide](../guides/testing.md)
