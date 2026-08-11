---
sidebar_position: 420
title: Traits Reference
---

# Traits Reference

Traits provide reusable functionality for your models, particularly for automatic timestamp management and soft deletes.

## Table of Contents

- [Overview](#overview)
- [OaCreatedAt Trait](#oacreatedat-trait)
- [OaUpdatedAt Trait](#oaupdatedat-trait)
- [OaDeletedAt Trait](#oadeletedat-trait)
- [Using Multiple Traits](#using-multiple-traits)
- [Database Setup](#database-setup)
- [Custom Timestamp Fields](#custom-timestamp-fields)
- [Best Practices](#best-practices)

## Overview

The reference architecture provides three timestamp traits that combine:

- **ORM Functionality**: Automatic timestamp management from ByJG MicroOrm
- **OpenAPI Documentation**: Automatic schema generation for timestamps

**Location**: `ByJG\Gluo\Trait\` (byjg/gluo-core)

### Available Traits

| Trait          | Purpose                        | Field Name   | Auto-Updated     |
|----------------|--------------------------------|--------------|------------------|
| `OaCreatedAt`  | Track record creation time     | `created_at` | On INSERT        |
| `OaUpdatedAt`  | Track record modification time | `updated_at` | On INSERT/UPDATE |
| `OaDeletedAt`  | Soft delete support            | `deleted_at` | Manual           |

## OaCreatedAt Trait

Automatically sets the creation timestamp when a record is inserted.

**Location**: `ByJG\Gluo\Trait\OaCreatedAt` (byjg/gluo-core)

### Usage

```php
<?php

namespace RestReferenceArchitecture\Model;

use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\Gluo\Trait\OaCreatedAt;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ["id", "name"], type: "object")]
#[TableAttribute("products")]
class Product
{
    use OaCreatedAt;  // Adds created_at functionality

    protected int $id;
    protected string $name;

    // Getters and setters...
}
```

### What It Provides

```php
// Property definition
protected string|null $createdAt = null;

// Getter
public function getCreatedAt(): ?string

// Setter
public function setCreatedAt(?string $value): static

// ORM Attribute
#[FieldAttribute(fieldName: "created_at", syncWithDb: false)]

// OpenAPI Attribute
#[OA\Property(type: "string", format: "date-time", nullable: true)]
```

### Behavior

```php
$product = new Product();
$product->setName('Widget');

// created_at is null
echo $product->getCreatedAt(); // null

$repository->save($product);

// created_at is automatically set
echo $product->getCreatedAt(); // "2024-01-15 10:30:45"

// Later updates don't change created_at
$product->setName('Updated Widget');
$repository->save($product);
echo $product->getCreatedAt(); // Still "2024-01-15 10:30:45"
```

### OpenAPI Schema

When using this trait, the OpenAPI schema automatically includes:

```yaml
Product:
  type: object
  properties:
    created_at:
      type: string
      format: date-time
      nullable: true
```

## OaUpdatedAt Trait

Automatically updates the timestamp whenever a record is inserted or updated.

**Location**: `ByJG\Gluo\Trait\OaUpdatedAt` (byjg/gluo-core)

### Usage

```php
<?php

namespace RestReferenceArchitecture\Model;

use ByJG\Gluo\Trait\OaUpdatedAt;

class Product
{
    use OaUpdatedAt;  // Adds updated_at functionality

    // Other properties...
}
```

### What It Provides

```php
// Property definition
protected string|null $updatedAt = null;

// Getter
public function getUpdatedAt(): ?string

// Setter
public function setUpdatedAt(?string $value): static

// ORM Attribute
#[FieldAttribute(fieldName: "updated_at", syncWithDb: false)]

// OpenAPI Attribute
#[OA\Property(type: "string", format: "date-time", nullable: true)]
```

### Behavior

```php
$product = new Product();
$product->setName('Widget');

$repository->save($product);
echo $product->getUpdatedAt(); // "2024-01-15 10:30:45"

// Wait a moment...
sleep(2);

$product->setName('Updated Widget');
$repository->save($product);
echo $product->getUpdatedAt(); // "2024-01-15 10:30:47" (updated!)
```

## OaDeletedAt Trait

Provides soft delete functionality by marking records as deleted instead of removing them from the database.

**Location**: `ByJG\Gluo\Trait\OaDeletedAt` (byjg/gluo-core)

### Usage

```php
<?php

namespace RestReferenceArchitecture\Model;

use ByJG\Gluo\Trait\OaDeletedAt;

class Product
{
    use OaDeletedAt;  // Adds deleted_at functionality

    // Other properties...
}
```

### What It Provides

```php
// Property definition
protected string|null $deletedAt = null;

// Getter
public function getDeletedAt(): ?string

// Setter
public function setDeletedAt(?string $value): static
```

### Behavior

```php
$product = $repository->get($id);
echo $product->getDeletedAt(); // null (not deleted)

// Soft delete
$product->setDeletedAt(date('Y-m-d H:i:s'));
$repository->save($product);

echo $product->getDeletedAt() !== null; // true (deleted)

// Restore
$product->setDeletedAt(null);
$repository->save($product);
echo $product->getDeletedAt() !== null; // false (restored)
```

### Service Layer Integration

Implement soft delete in your service:

```php
class ProductService extends BaseService
{
    public function softDelete(int $id): void
    {
        $product = $this->getOrFail($id);
        $product->setDeletedAt(date('Y-m-d H:i:s'));
        $this->save($product);
    }

    public function restore(int $id): void
    {
        $product = $this->getOrFail($id);
        $product->setDeletedAt(null);
        $this->save($product);
    }

    /**
     * List only non-deleted records
     */
    public function list(?int $page = null, ?int $size = null): array
    {
        $query = $this->repository->listQuery(
            page: $page,
            size: $size,
            filter: [
                ['deleted_at IS NULL', []]
            ]
        );

        return $this->repository->getRepository()->getByQuery($query);
    }
}
```

## Using Multiple Traits

Combine traits for complete timestamp tracking:

```php
<?php

namespace RestReferenceArchitecture\Model;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use OpenApi\Attributes as OA;
use ByJG\Gluo\Trait\OaCreatedAt;
use ByJG\Gluo\Trait\OaUpdatedAt;
use ByJG\Gluo\Trait\OaDeletedAt;

#[OA\Schema(required: ["id", "name"], type: "object")]
#[TableAttribute("products")]
class Product
{
    use OaCreatedAt;   // Track creation
    use OaUpdatedAt;   // Track updates
    use OaDeletedAt;   // Soft deletes

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    protected int|null $id = null;

    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "name")]
    protected string $name;

    // Getters and setters...
}
```

### Complete Lifecycle Example

```php
// CREATE
$product = new Product();
$product->setName('Widget');

$repository->save($product);

echo $product->getCreatedAt(); // "2024-01-15 10:30:45"
echo $product->getUpdatedAt(); // "2024-01-15 10:30:45"
echo $product->getDeletedAt(); // null

// UPDATE
sleep(2);
$product->setName('Updated Widget');
$repository->save($product);

echo $product->getCreatedAt(); // "2024-01-15 10:30:45" (unchanged)
echo $product->getUpdatedAt(); // "2024-01-15 10:30:47" (updated!)
echo $product->getDeletedAt(); // null

// SOFT DELETE
$product->setDeletedAt(date('Y-m-d H:i:s'));
$repository->save($product);

echo $product->getCreatedAt(); // "2024-01-15 10:30:45"
echo $product->getUpdatedAt(); // "2024-01-15 10:30:49" (updated by save)
echo $product->getDeletedAt(); // "2024-01-15 10:30:49" (marked deleted)
```

## Database Setup

### Migration Example

Create database columns for timestamp fields:

```sql
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2),
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
);
```

### Using Code Generator

The code generator automatically adds timestamp fields:

```bash
composer codegen -- --env=dev --table=products model --save
```

Generates:

```php
class Product
{
    use OaCreatedAt;
    use OaUpdatedAt;
    // Other fields...
}
```

### Database Triggers (Alternative)

You can also use database triggers instead of application-level timestamps:

```sql
CREATE TRIGGER products_before_insert
BEFORE INSERT ON products
FOR EACH ROW
SET NEW.created_at = CURRENT_TIMESTAMP,
    NEW.updated_at = CURRENT_TIMESTAMP;

CREATE TRIGGER products_before_update
BEFORE UPDATE ON products
FOR EACH ROW
SET NEW.updated_at = CURRENT_TIMESTAMP;
```

**Note**: When using database triggers, set `syncWithDb: true` in the FieldAttribute:

```php
#[FieldAttribute(fieldName: "created_at", syncWithDb: true)]
protected string|null $createdAt = null;
```

## Custom Timestamp Fields

### Custom Field Names

If your database uses different column names:

```php
use ByJG\MicroOrm\Trait\CreatedAt;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use OpenApi\Attributes as OA;

trait CustomCreatedAt
{
    use CreatedAt;

    #[OA\Property(type: "string", format: "date-time", nullable: true)]
    #[FieldAttribute(fieldName: "date_created", syncWithDb: false)]
    protected string|null $createdAt = null;
}
```

### Custom Timestamp Format

Override getters/setters for custom formats:

```php
trait CustomCreatedAt
{
    use OaCreatedAt;

    public function getCreatedAt(): ?string
    {
        $timestamp = parent::getCreatedAt();
        return $timestamp ? date('c', strtotime($timestamp)) : null; // ISO 8601
    }

    public function setCreatedAt(?string $value): static
    {
        if ($value) {
            $value = date('Y-m-d H:i:s', strtotime($value));
        }
        return parent::setCreatedAt($value);
    }
}
```

### Additional Timestamp Fields

Create custom timestamp traits:

```php
<?php

namespace RestReferenceArchitecture\Trait;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use OpenApi\Attributes as OA;

trait OaPublishedAt
{
    #[OA\Property(type: "string", format: "date-time", nullable: true)]
    #[FieldAttribute(fieldName: "published_at", syncWithDb: false)]
    protected string|null $publishedAt = null;

    public function getPublishedAt(): ?string
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?string $value): static
    {
        $this->publishedAt = $value;
        return $this;
    }

    public function isPublished(): bool
    {
        return !empty($this->publishedAt) &&
               strtotime($this->publishedAt) <= time();
    }

    public function publish(): static
    {
        $this->publishedAt = date('Y-m-d H:i:s');
        return $this;
    }

    public function unpublish(): static
    {
        $this->publishedAt = null;
        return $this;
    }
}
```

## Best Practices

### 1. Always Use Timestamp Traits

```php
// Good - Automatic tracking
class Product
{
    use OaCreatedAt;
    use OaUpdatedAt;
}

// Bad - Manual tracking (error-prone)
class Product
{
    protected string $createdAt;

    public function setCreatedAt(string $value) {
        $this->createdAt = $value;
    }
}
```

### 2. Use OaDeletedAt for Audit Trail

```php
// Good - Keep deleted records for audit
class Order
{
    use OaDeletedAt;
}

// Bad - Permanent deletion loses data
$repository->delete($id);
```

### 3. Filter Soft-Deleted Records

```php
// Good - Exclude deleted records by default
public function list($page, $size): array
{
    return $this->repository->list(
        page: $page,
        size: $size,
        filter: [['deleted_at IS NULL', []]]
    );
}

// Bad - Returns deleted records
public function list($page, $size): array
{
    return $this->repository->list($page, $size);
}
```

### 4. Set Nullable Constraints

```sql
-- Good - Allow NULL for optional timestamps
created_at DATETIME NULL,
updated_at DATETIME NULL,
deleted_at DATETIME NULL

-- Bad - NOT NULL requires manual management
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
```

### 5. Combine with ActiveRecord

```php
class Product
{
    use ActiveRecord;
    use OaCreatedAt;
    use OaUpdatedAt;
    use OaDeletedAt;

    // Custom query for non-deleted records
    public static function findActive(): ?array
    {
        $query = Query::getInstance()
            ->table(self::$repository->getMapper()->getTable())
            ->where('deleted_at IS NULL');
        return self::query($query);
    }
}
```

### 6. Document Timestamp Behavior

```php
/**
 * Product Model
 *
 * Timestamps:
 * - created_at: Set automatically on first save
 * - updated_at: Updated automatically on every save
 * - deleted_at: Set manually for soft deletes
 */
class Product
{
    use OaCreatedAt;
    use OaUpdatedAt;
    use OaDeletedAt;
}
```

## Troubleshooting

### Timestamps Not Updating

**Problem**: `updated_at` not changing on save

**Solution**: Ensure `syncWithDb: false` in FieldAttribute:

```php
#[FieldAttribute(fieldName: "updated_at", syncWithDb: false)]
```

### OpenAPI Generation Error

**Problem**: Error when running `composer run openapi` on traits

**Solution**: This is expected if traits are not used in any model. The error only occurs when traits exist but aren't used. Once you use a trait in a model, OpenAPI generation works correctly.

### Timezone Issues

**Problem**: Timestamps in wrong timezone

**Solution**: Set timezone in `config/<env>/02-security.php`:

```php
return [
    'timezone' => fn() => date_default_timezone_set('UTC')
];
```

### NULL Timestamps

**Problem**: All timestamps are NULL

**Solution**: Check database column allows NULL and ORM mapping is correct:

```sql
SHOW COLUMNS FROM products LIKE '%_at';
```

## Related Documentation

- [ORM Usage Guide](../guides/orm.md)
- [Code Generator](code-generator.md)
- [Advanced Repository Patterns](../guides/repository-advanced.md)
- [Service Patterns](../guides/services.md)
