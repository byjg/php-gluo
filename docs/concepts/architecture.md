---
sidebar_position: 310
title: Architecture Decisions
---

# Architecture Decision Guide

Guide to choosing between architectural patterns and understanding when to use each approach.

:::note Monorepo layout
A Gluo project is a monorepo: the repository root *is* the PHP application root (`src/`, `config/`, `db/`, `public/`, `templates/`, `tests/` and a single `composer.json`), while an optional Vite SPA is a sibling package under `frontend/`. The patterns below all concern the PHP backend. See [Repository layout](repo-layout.md) for the full picture, and the [Frontend guide](../guides/frontend.md) for the SPA that consumes it.
:::

## Table of Contents

- [Repository vs ActiveRecord](#repository-vs-activerecord)
- [Service Layer Usage](#service-layer-usage)
- [When to Use Attributes](#when-to-use-attributes)
- [Validation Strategies](#validation-strategies)
- [Authentication Approaches](#authentication-approaches)

## Repository vs ActiveRecord

### Overview

The reference architecture supports both Repository and ActiveRecord patterns.

### Repository Pattern

**Structure**: Model → Repository → Service → REST Controller

```
Client Request
    ↓
REST Controller
    ↓
Service (Business Logic)
    ↓
Repository (Data Access)
    ↓
Database
```

#### Advantages

- **Separation of Concerns**: Clear boundaries between layers
- **Testability**: Easy to mock repositories in service tests
- **Flexibility**: Can swap data sources without changing services
- **Complex Queries**: Repository centralizes complex database logic
- **Multiple Data Sources**: Can coordinate between different databases

#### Disadvantages

- **More Boilerplate**: Requires Repository, Service, and REST classes
- **Indirection**: More layers to navigate
- **Slower Initial Development**: More files to create initially

#### When to Use Repository

✅ **Use Repository Pattern When:**

- Building large, complex applications
- Need to support multiple data sources
- Require extensive unit testing
- Team has multiple developers
- Business logic is complex
- Data access patterns are sophisticated
- Need to centralize query logic

#### Example

```php
// Model
class Product { }

// Repository
class ProductRepository extends BaseRepository
{
    public function findByCategory(int $categoryId): array { }
    public function findInStock(): array { }
}

// Service
class ProductService extends BaseService
{
    public function __construct(ProductRepository $repository) { }

    public function applyDiscount(int $productId, float $percentage) { }
}

// REST Controller
class ProductController
{
    public function listProducts(HttpResponse $response, HttpRequest $request) {
        $service = Config::get(ProductService::class);
        $products = $service->list();
        $response->write($products);
    }
}
```

### ActiveRecord Pattern

**Structure**: Model (with persistence methods) → REST Controller

```
Client Request
    ↓
REST Controller
    ↓
Model (Active Record)
    ↓
Database
```

#### Advantages

- **Simplicity**: Less boilerplate code
- **Rapid Development**: Faster to build CRUD operations
- **Intuitive**: Model methods directly interact with database
- **Less Indirection**: Fewer layers to navigate

#### Disadvantages

- **Tight Coupling**: Business logic mixed with persistence
- **Harder to Test**: Models are tightly coupled to database
- **Less Flexible**: Difficult to swap data sources
- **Can Become Bloated**: Models can grow large with complex logic

#### When to Use ActiveRecord

✅ **Use ActiveRecord Pattern When:**

- Building small to medium applications
- CRUD operations dominate
- Rapid prototyping required
- Single data source
- Simple business logic
- Small team or solo developer
- Database structure is stable

#### Example

```php
// Model with ActiveRecord
class Product
{
    use ActiveRecord;

    public function save(): static { }
    public function delete(): void { }

    public static function get(int $id): ?static { }
    public static function findByCategory(int $categoryId): ?array { }
}

// REST Controller
class ProductController
{
    public function getProduct(HttpResponse $response, HttpRequest $request) {
        $id = $request->attribute('id');
        $product = Product::get($id);

        if (!$product) {
            throw new Error404Exception('Product not found');
        }

        $response->write($product);
    }

    public function createProduct(HttpResponse $response, HttpRequest $request) {
        $payload = ValidateRequest::getPayload();

        $product = new Product();
        $product->setName($payload['name']);
        $product->setPrice($payload['price']);
        $product->save();

        $response->write(['id' => $product->getId()]);
    }
}
```

### Comparison Matrix

| Aspect                | Repository          | ActiveRecord        |
|-----------------------|---------------------|---------------------|
| **Code Complexity**   | High                | Low                 |
| **Boilerplate**       | More                | Less                |
| **Testability**       | Excellent           | Moderate            |
| **Flexibility**       | High                | Low                 |
| **Learning Curve**    | Steep               | Gentle              |
| **Development Speed** | Slower initially    | Faster initially    |
| **Maintenance**       | Easier (large apps) | Easier (small apps) |
| **Coupling**          | Loose               | Tight               |
| **Best For**          | Enterprise apps     | Small-medium apps   |

### Migration Between Patterns

You can migrate from ActiveRecord to Repository as your application grows:

```php
// Step 1: Create Repository
class ProductRepository extends BaseRepository
{
    public function findInStock(): array
    {
        return Product::query(
            Query::getInstance()
                ->table('products')
                ->where('stock > 0')
        );
    }
}

// Step 2: Create Service
class ProductService extends BaseService
{
    public function __construct(ProductRepository $repository)
    {
        parent::__construct($repository);
    }
}

// Step 3: Update REST Controller
class ProductController
{
    public function listProducts(HttpResponse $response, HttpRequest $request)
    {
        // Changed from: Product::getAll()
        $service = Config::get(ProductService::class);
        $products = $service->list();
        $response->write($products);
    }
}
```

## Service Layer Usage

### Architectural Rule: Always Call Service from Controllers

**This reference architecture follows a strict layering pattern:**

```
REST Controller → Service Layer → Repository → Database
```

**Rule**: Controllers ALWAYS call the Service layer, never Repository directly.

### Why Always Use Services?

**1. Consistency**
- No decision fatigue: "Should I use Service or Repository?"
- Predictable code structure across all endpoints
- Single entry point for all business operations

**2. Extensibility**
- Service methods can evolve without changing controllers
- Even "simple" CRUD operations might need business logic later
- Example: `get()` is simple now, but you might add caching, audit logging, or access control later

**3. Testability**
- Mock the Service layer consistently
- Controllers don't need to know about data access details

**4. Maintainability**
- Clear separation: Controllers handle HTTP, Services handle business logic, Repositories handle data
- Easier onboarding for new developers

### BaseService as a Wrapper

Many `BaseService` methods are simple wrappers around Repository methods with no additional logic. **This is intentional and good**:

```php
// Simple wrapper - no additional logic (yet)
public function get(array|string|int|LiteralInterface $id): mixed
{
    return $this->repository->get($id);
}
```

Benefits:
- Provides consistent interface for controllers
- Allows adding logic later without breaking controllers
- Makes the codebase easier to understand

See [Service Layer Patterns](../guides/services.md#advanced-orchestration) for detailed examples.

### When to Add Custom Logic in Services

✅ **Add custom service methods for:**

- **Complex Business Logic**: More than simple CRUD
- **Multi-Entity Operations**: Coordinating between multiple repositories
- **Transactions**: Transaction boundaries across operations
- **Validation**: Business rule validation beyond schema
- **Reusability**: Logic used by multiple endpoints

### Example: Service with Business Logic

```php
class OrderService extends BaseService
{
    public function __construct(
        OrderRepository $orderRepository,
        ProductService $productService,
        PaymentService $paymentService
    ) {
        parent::__construct($orderRepository);
        $this->productService = $productService;
        $this->paymentService = $paymentService;
    }

    public function placeOrder(array $orderData): Order
    {
        $executor = $this->repository->getExecutorWrite();

        try {
            $executor->beginTransaction();

            // 1. Validate products
            foreach ($orderData['items'] as $item) {
                if (!$this->productService->isInStock($item['product_id'])) {
                    throw new Error400Exception('Product out of stock');
                }
            }

            // 2. Create order
            $order = $this->create($orderData);

            // 3. Reduce inventory
            foreach ($orderData['items'] as $item) {
                $this->productService->reduceStock(
                    $item['product_id'],
                    $item['quantity']
                );
            }

            // 4. Process payment
            $this->paymentService->charge($order);

            $executor->commitTransaction();

            return $order;

        } catch (\Exception $e) {
            $executor->rollbackTransaction();
            throw $e;
        }
    }
}
```

## When to Use Attributes

### RequireAuthenticated

✅ **Use When:** Endpoint requires any authenticated user

```php
#[RequireAuthenticated]
public function getProfile(...) { }
```

### RequireRole

✅ **Use When:** Endpoint requires specific role

```php
#[RequireRole(User::ROLE_ADMIN)]
public function deleteUser(...) { }
```

### ValidateRequest

✅ **Use When:** Need to validate request against OpenAPI schema

```php
#[ValidateRequest]
public function createProduct(...) {
    $payload = ValidateRequest::getPayload();  // Validated
}
```

### Custom Attributes

✅ **Create Custom Attributes When:**

- Cross-cutting concern applies to multiple endpoints
- Logic should execute before method
- Want declarative approach

```php
// Example: Rate limiting
#[RateLimit(maxRequests: 10, windowSeconds: 60)]
public function heavyOperation(...) { }
```

## Validation Strategies

### Multi-Layer Validation

Apply validation at appropriate layers:

```
1. OpenAPI Schema Validation (Syntax)
   ↓
2. Service Business Rules (Semantics)
   ↓
3. Database Constraints (Data Integrity)
```

#### Example

```php
// Layer 1: OpenAPI (via ValidateRequest attribute)
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ["email", "password"],
        properties: [
            new OA\Property(property: "email", type: "string", format: "email"),
            new OA\Property(property: "password", type: "string", minLength: 8)
        ]
    )
)]
#[ValidateRequest]
public function createUser(HttpResponse $response, HttpRequest $request) {
    $payload = ValidateRequest::getPayload();  // Schema validated

    $userService = Config::get(UserService::class);
    $user = $userService->create($payload);  // Business rules validated

    $response->write(['id' => $user->getId()]);
}

// Layer 2: Service business rules
class UserService extends BaseService
{
    public function create(array $payload)
    {
        // Business rule: Email must be unique
        if ($this->repository->existsByEmail($payload['email'])) {
            throw new Error409Exception('Email already exists');
        }

        // Business rule: Password strength
        if (!$this->isPasswordStrong($payload['password'])) {
            throw new Error400Exception('Password not strong enough');
        }

        return parent::create($payload);
    }
}

// Layer 3: Database constraints
CREATE TABLE users (
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    CONSTRAINT check_email_format CHECK (email REGEXP '^[^@]+@[^@]+\.[^@]+$')
);
```

## Authentication Approaches

### Option 1: JWT Tokens (Recommended)

**Location**: Implemented in reference architecture

✅ **Advantages:**
- Stateless
- Scalable
- Standard
- Built-in expiration

❌ **Disadvantages:**
- Cannot revoke before expiration (without blacklist)
- Token size larger than session ID

#### When to Use JWT

- Microservices architecture
- Mobile apps
- Distributed systems
- API-first applications

### Option 2: Session-Based

⚠️ **Not included in reference architecture**

✅ **Advantages:**
- Easy to revoke
- Smaller token size
- Server controls state

❌ **Disadvantages:**
- Requires session storage
- Not stateless
- Harder to scale

#### When to Use Sessions

- Traditional web applications
- Single server deployment
- Need immediate revocation

### Option 3: API Keys

⚠️ **Not included in reference architecture**

✅ **Advantages:**
- Simple
- Long-lived
- Good for integrations

❌ **Disadvantages:**
- No expiration
- Security concerns if leaked
- Less granular permissions

#### When to Use API Keys

- Server-to-server communication
- Third-party integrations
- Simple authentication needs

## Related Documentation

- [Repository Patterns](../guides/repository-advanced.md)
- [Service Patterns](../guides/services.md)
- [JWT Authentication](../guides/jwt-advanced.md)
- [Testing Guide](../guides/testing.md)
- [Code Generator](../reference/code-generator.md)
