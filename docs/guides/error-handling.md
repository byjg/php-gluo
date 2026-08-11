---
sidebar_position: 210
title: Error Handling
---

# Error Handling Guide

This guide covers error handling patterns, exception types, and best practices for returning consistent error responses in your REST API.

## Table of Contents

- [Overview](#overview)
- [HTTP Exception Classes](#http-exception-classes)
- [Throwing Exceptions](#throwing-exceptions)
- [Error Response Format](#error-response-format)
- [Custom Exceptions](#custom-exceptions)
- [Validation Errors](#validation-errors)
- [Global Error Handling](#global-error-handling)
- [Error Logging](#error-logging)
- [Best Practices](#best-practices)

## Overview

The reference architecture uses the ByJG RestServer exception system to automatically convert exceptions into appropriate HTTP responses.

### Exception Hierarchy

```
Throwable
└── Exception
    └── Error*Exception (ByJG\RestServer\Exception)
        ├── Error400Exception (Bad Request)
        ├── Error401Exception (Unauthorized)
        ├── Error403Exception (Forbidden)
        ├── Error404Exception (Not Found)
        ├── Error405Exception (Method Not Allowed)
        ├── Error409Exception (Conflict)
        ├── Error415Exception (Unsupported Media Type)
        ├── Error422Exception (Unprocessable Entity)
        ├── Error429Exception (Too Many Requests)
        └── Error500Exception (Internal Server Error)
```

## HTTP Exception Classes

### Available Exceptions

| Exception           | HTTP Code | Use Case                                  |
|---------------------|-----------|-------------------------------------------|
| `Error400Exception` | 400       | Invalid request data, validation failures |
| `Error401Exception` | 401       | Authentication required or failed         |
| `Error403Exception` | 403       | Authenticated but not authorized          |
| `Error404Exception` | 404       | Resource not found                        |
| `Error405Exception` | 405       | HTTP method not allowed                   |
| `Error409Exception` | 409       | Conflict (duplicate resource)             |
| `Error415Exception` | 415       | Wrong content-type                        |
| `Error422Exception` | 422       | Validation error (semantic)               |
| `Error429Exception` | 429       | Rate limit exceeded                       |
| `Error500Exception` | 500       | Server error                              |

### Import Statements

```php
use ByJG\RestServer\Exception\Error400Exception;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error403Exception;
use ByJG\RestServer\Exception\Error404Exception;
use ByJG\RestServer\Exception\Error409Exception;
use ByJG\RestServer\Exception\Error422Exception;
use ByJG\RestServer\Exception\Error429Exception;
use ByJG\RestServer\Exception\Error500Exception;
```

## Throwing Exceptions

### 400 - Bad Request

Use for invalid input data:

```php
public function createProduct(array $payload)
{
    if (empty($payload['name'])) {
        throw new Error400Exception('Product name is required');
    }

    if ($payload['price'] < 0) {
        throw new Error400Exception('Price cannot be negative');
    }

    if (!in_array($payload['status'], ['active', 'inactive'])) {
        throw new Error400Exception(
            'Status must be either "active" or "inactive"'
        );
    }

    return $this->create($payload);
}
```

### 401 - Unauthorized

Authentication is required but missing or invalid:

```php
public function validateToken(string $token): User
{
    if (empty($token)) {
        throw new Error401Exception('Authentication token required');
    }

    try {
        $decoded = JwtWrapper::decode($token);
    } catch (\Exception $e) {
        throw new Error401Exception('Invalid or expired token');
    }

    return $this->getUserFromToken($decoded);
}
```

**Note**: The `RequireAuthenticated` attribute automatically throws this exception.

### 403 - Forbidden

User is authenticated but lacks permissions:

```php
public function deleteProduct(int $productId, User $user): void
{
    if ($user->getRole() !== User::ROLE_ADMIN) {
        throw new Error403Exception('Only administrators can delete products');
    }

    $this->delete($productId);
}
```

**Note**: The `RequireRole` attribute automatically throws this exception.

### 404 - Not Found

Resource doesn't exist:

```php
public function getProduct(int $id): Product
{
    $product = $this->repository->get($id);

    if ($product === null) {
        throw new Error404Exception("Product with ID {$id} not found");
    }

    return $product;
}

// Or use getOrFail() helper
public function getProduct(int $id): Product
{
    // Automatically throws Error404Exception if not found
    return $this->getOrFail($id);
}
```

### 409 - Conflict

Resource already exists (duplicate):

```php
public function createUser(array $payload): User
{
    $existing = $this->repository->findByEmail($payload['email']);

    if ($existing !== null) {
        throw new Error409Exception(
            "User with email {$payload['email']} already exists"
        );
    }

    return $this->create($payload);
}
```

### 422 - Unprocessable Entity

Semantically incorrect data:

```php
public function scheduleDelivery(int $orderId, string $date): void
{
    $order = $this->getOrFail($orderId);

    if ($order->getStatus() !== Order::STATUS_CONFIRMED) {
        throw new Error422Exception(
            'Cannot schedule delivery for unconfirmed order'
        );
    }

    $deliveryDate = strtotime($date);
    if ($deliveryDate < time()) {
        throw new Error422Exception(
            'Delivery date cannot be in the past'
        );
    }

    $order->setDeliveryDate($date);
    $this->save($order);
}
```

### 429 - Rate Limit Exceeded

Too many requests:

```php
public function checkRateLimit(string $clientId): void
{
    $requests = $this->countRecentRequests($clientId);

    if ($requests > 100) {
        throw new Error429Exception(
            'Rate limit exceeded. Maximum 100 requests per minute.'
        );
    }
}
```

### 500 - Internal Server Error

Unexpected server errors:

```php
public function processPayment(int $orderId): void
{
    try {
        $paymentGateway = Config::get(PaymentGateway::class);
        $paymentGateway->charge($orderId);
    } catch (\Exception $e) {
        // Log the actual error
        error_log("Payment processing failed: {$e->getMessage()}");

        // Return generic error to client
        throw new Error500Exception(
            'Payment processing failed. Please try again later.'
        );
    }
}
```

## Error Response Format

All exceptions are automatically converted to JSON responses:

### Standard Error Response

```json
{
    "error": "Bad Request",
    "message": "Product name is required"
}
```

### Error Response with Details

```json
{
    "error": "Validation Failed",
    "message": "Multiple validation errors occurred",
    "details": {
        "name": "Product name is required",
        "price": "Price must be a positive number",
        "category": "Invalid category selected"
    }
}
```

### REST Controller Example

```php
#[OA\Post(path: "/products", tags: ["Products"])]
#[OA\Response(
    response: 200,
    description: "Product created successfully"
)]
#[OA\Response(
    response: 400,
    description: "Validation error",
    content: new OA\JsonContent(ref: "#/components/schemas/error")
)]
#[OA\Response(
    response: 409,
    description: "Product already exists",
    content: new OA\JsonContent(ref: "#/components/schemas/error")
)]
#[ValidateRequest]
public function createProduct(HttpResponse $response, HttpRequest $request): void
{
    try {
        $payload = ValidateRequest::getPayload();
        $productService = Config::get(ProductService::class);
        $product = $productService->create($payload);

        $response->write(["id" => $product->getId()]);
    } catch (Error400Exception $e) {
        // Automatically returns 400 with error message
        throw $e;
    } catch (Error409Exception $e) {
        // Automatically returns 409 with error message
        throw $e;
    }
}
```

## Custom Exceptions

### Creating Domain-Specific Exceptions

```php
<?php

namespace RestReferenceArchitecture\Exception;

use ByJG\RestServer\Exception\Error400Exception;

class InsufficientStockException extends Error400Exception
{
    public function __construct(string $productName, int $available, int $requested)
    {
        parent::__construct(
            "Insufficient stock for '{$productName}'. " .
            "Available: {$available}, Requested: {$requested}"
        );
    }
}
```

#### Usage

```php
use RestReferenceArchitecture\Exception\InsufficientStockException;

public function createOrder(array $orderData): Order
{
    foreach ($orderData['items'] as $item) {
        $product = $this->productService->getOrFail($item['product_id']);

        if ($product->getStock() < $item['quantity']) {
            throw new InsufficientStockException(
                $product->getName(),
                $product->getStock(),
                $item['quantity']
            );
        }
    }

    return $this->create($orderData);
}
```

### Exception with Additional Data

```php
<?php

namespace RestReferenceArchitecture\Exception;

use ByJG\RestServer\Exception\Error422Exception;

class ValidationException extends Error422Exception
{
    protected array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $message = "Validation failed: " . implode(', ', array_keys($errors));
        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return [
            'error' => 'Validation Failed',
            'message' => $this->getMessage(),
            'details' => $this->errors
        ];
    }
}
```

#### Usage

```php
public function create(array $payload)
{
    $errors = [];

    if (empty($payload['name'])) {
        $errors['name'] = 'Name is required';
    }

    if (empty($payload['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (!empty($errors)) {
        throw new ValidationException($errors);
    }

    return parent::create($payload);
}
```

## Validation Errors

### OpenAPI Validation

The `ValidateRequest` attribute automatically validates against OpenAPI schema:

```php
#[OA\Post(path: "/products", tags: ["Products"])]
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ["name", "price"],
        properties: [
            new OA\Property(property: "name", type: "string", minLength: 3),
            new OA\Property(property: "price", type: "number", minimum: 0)
        ]
    )
)]
#[ValidateRequest]
public function createProduct(HttpResponse $response, HttpRequest $request): void
{
    // If validation fails, Error400Exception is automatically thrown
    $payload = ValidateRequest::getPayload();

    $productService = Config::get(ProductService::class);
    $product = $productService->create($payload);

    $response->write(["id" => $product->getId()]);
}
```

#### Validation Error Response

```json
{
    "error": "Bad Request",
    "message": "Validation failed: property 'name' must be at least 3 characters"
}
```

### Service-Level Validation

Add business rule validation in services:

```php
class ProductService extends BaseService
{
    public function create(array $payload)
    {
        // Schema validation already passed (via ValidateRequest)
        // Now apply business rules

        $this->validateUniqueSKU($payload['sku'] ?? null);
        $this->validateCategory($payload['category_id'] ?? null);
        $this->validatePriceRange($payload['price'] ?? null);

        return parent::create($payload);
    }

    protected function validateUniqueSKU(?string $sku): void
    {
        if ($sku && $this->repository->existsBySKU($sku)) {
            throw new Error409Exception("Product with SKU '{$sku}' already exists");
        }
    }

    protected function validateCategory(?int $categoryId): void
    {
        if ($categoryId) {
            $categoryService = Config::get(CategoryService::class);
            try {
                $categoryService->getOrFail($categoryId);
            } catch (Error404Exception $e) {
                throw new Error400Exception("Invalid category ID: {$categoryId}");
            }
        }
    }

    protected function validatePriceRange(?float $price): void
    {
        if ($price !== null) {
            if ($price < 0) {
                throw new Error400Exception('Price cannot be negative');
            }
            if ($price > 1000000) {
                throw new Error400Exception('Price exceeds maximum allowed value');
            }
        }
    }
}
```

## Global Error Handling

### Custom Error Handler

Configure in `config/<env>/03-api.php`:

```php
use ByJG\RestServer\ErrorHandler\ErrorHandler;
use ByJG\RestServer\HttpResponse;

$errorHandler = new ErrorHandler();

// Log all errors
$errorHandler->addHandler(function(\Throwable $ex, $request, HttpResponse $response) {
    error_log(sprintf(
        "[%s] %s: %s in %s:%d",
        date('Y-m-d H:i:s'),
        get_class($ex),
        $ex->getMessage(),
        $ex->getFile(),
        $ex->getLine()
    ));

    // Let default handler format the response
    return null;
});

// Customize error response format
$errorHandler->addHandler(function(\Throwable $ex, $request, HttpResponse $response) {
    $statusCode = $ex->getCode() >= 400 && $ex->getCode() < 600
        ? $ex->getCode()
        : 500;

    return [
        'success' => false,
        'error' => [
            'code' => $statusCode,
            'message' => $ex->getMessage(),
            'type' => (new \ReflectionClass($ex))->getShortName(),
            'timestamp' => date('c')
        ]
    ];
});

return [
    'errorHandler' => fn() => $errorHandler
];
```

### Environment-Specific Error Details

```php
$errorHandler->addHandler(function(\Throwable $ex, $request, HttpResponse $response) {
    $isDevelopment = Config::get('environment') === 'dev';

    $error = [
        'error' => $ex->getMessage(),
        'code' => $ex->getCode()
    ];

    // Include stack trace in development
    if ($isDevelopment) {
        $error['file'] = $ex->getFile();
        $error['line'] = $ex->getLine();
        $error['trace'] = $ex->getTraceAsString();
    }

    return $error;
});
```

## Error Logging

### Simple Logging

```php
public function processOrder(int $orderId): void
{
    try {
        // Process order...
    } catch (\Exception $e) {
        error_log("Order processing failed for order {$orderId}: {$e->getMessage()}");
        throw new Error500Exception('Order processing failed');
    }
}
```

### Structured Logging

```php
use Psr\Log\LoggerInterface;

class OrderService extends BaseService
{
    protected LoggerInterface $logger;

    public function __construct(
        OrderRepository $repository,
        LoggerInterface $logger
    ) {
        parent::__construct($repository);
        $this->logger = $logger;
    }

    public function processOrder(int $orderId): void
    {
        try {
            // Process order...
        } catch (\Exception $e) {
            $this->logger->error('Order processing failed', [
                'order_id' => $orderId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new Error500Exception('Order processing failed');
        }
    }
}
```

### Context-Rich Logging

```php
public function updateProduct(int $productId, array $payload): Product
{
    $this->logger->info('Product update initiated', [
        'product_id' => $productId,
        'fields' => array_keys($payload)
    ]);

    try {
        $product = $this->update($payload);

        $this->logger->info('Product updated successfully', [
            'product_id' => $productId
        ]);

        return $product;

    } catch (Error404Exception $e) {
        $this->logger->warning('Product not found', [
            'product_id' => $productId
        ]);
        throw $e;

    } catch (\Exception $e) {
        $this->logger->error('Product update failed', [
            'product_id' => $productId,
            'error' => $e->getMessage()
        ]);
        throw new Error500Exception('Failed to update product');
    }
}
```

## Best Practices

### 1. Use Appropriate HTTP Status Codes

```php
// Good - Specific status codes
throw new Error409Exception('Email already exists');     // 409 Conflict
throw new Error422Exception('Cannot cancel shipped order'); // 422 Unprocessable

// Bad - Generic 400 for everything
throw new Error400Exception('Email already exists');
throw new Error400Exception('Cannot cancel shipped order');
```

### 2. Provide Clear Error Messages

```php
// Good - Descriptive and actionable
throw new Error400Exception(
    'Invalid date format. Expected YYYY-MM-DD, got: ' . $date
);

// Bad - Vague
throw new Error400Exception('Invalid input');
```

### 3. Don't Expose Internal Details in Production

```php
// Good - Generic message to client, detailed log
try {
    $this->paymentGateway->charge($amount);
} catch (\Exception $e) {
    error_log("Payment gateway error: " . $e->getMessage());
    throw new Error500Exception('Payment processing failed');
}

// Bad - Exposes internal implementation
throw new Error500Exception(
    'MySQL connection to 192.168.1.100 failed: ' . $e->getMessage()
);
```

### 4. Use Custom Exceptions for Domain Logic

```php
// Good - Self-documenting exception
throw new InsufficientStockException($product->getName(), 5, 10);

// Bad - Generic exception
throw new Error400Exception('Not enough stock');
```

### 5. Validate at Multiple Layers

```php
// Layer 1: OpenAPI schema validation (syntax)
#[ValidateRequest]

// Layer 2: Service validation (business rules)
public function create(array $payload) {
    $this->validateBusinessRules($payload);
    return parent::create($payload);
}

// Layer 3: Model validation (data integrity)
public function save($model): void {
    $model->validate();
    parent::save($model);
}
```

### 6. Document Errors in OpenAPI

```php
#[OA\Post(path: "/products", tags: ["Products"])]
#[OA\Response(response: 200, description: "Success")]
#[OA\Response(
    response: 400,
    description: "Invalid input data",
    content: new OA\JsonContent(ref: "#/components/schemas/error")
)]
#[OA\Response(
    response: 409,
    description: "Product SKU already exists",
    content: new OA\JsonContent(ref: "#/components/schemas/error")
)]
public function createProduct(...) { }
```

### 7. Use getOrFail() Instead of Manual Checks

```php
// Good - Concise
$product = $this->getOrFail($id);

// Bad - Verbose
$product = $this->get($id);
if ($product === null) {
    throw new Error404Exception('Product not found');
}
```

### 8. Catch Specific Exceptions

```php
// Good - Handle specific cases
try {
    $product = $this->productService->create($payload);
} catch (Error409Exception $e) {
    // Handle duplicate
} catch (Error400Exception $e) {
    // Handle validation error
}

// Bad - Generic catch-all
try {
    $product = $this->productService->create($payload);
} catch (\Exception $e) {
    throw new Error500Exception('Something went wrong');
}
```

## Related Documentation

- [Attributes System](../reference/attributes.md) - ValidateRequest error handling
- [Service Patterns](services.md) - Service-level validation
- [REST API Development](rest-controllers.md) - REST error responses
- [Testing Guide](testing.md) - Testing error scenarios
