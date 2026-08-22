---
sidebar_position: 170
title: Advanced Repository Patterns
---

# Advanced Repository Patterns

This guide covers advanced features of the Repository layer, including complex queries, UUID handling, transactions, and custom query methods.

## Table of Contents

- [Overview](#overview)
- [BaseRepository Features](#baserepository-features)
- [Advanced Querying](#advanced-querying)
- [UUID Handling](#uuid-handling)
- [Custom Query Methods](#custom-query-methods)
- [Transaction Management](#transaction-management)
- [Performance Optimization](#performance-optimization)
- [ActiveRecord Pattern](#activerecord-pattern)

## Overview

The `BaseRepository` class provides a foundation for data access with support for:

- CRUD operations
- Pagination and filtering
- UUID generation and conversion
- Query builder integration
- Transaction support
- Custom query methods

**Location**: `ByJG\Gluo\Repository\BaseRepository` (byjg/gluo-core)

## BaseRepository Features

### Basic Methods

All repositories extending `BaseRepository` have these methods:

```php
// Get single entity by ID
$project = $repository->get($id);

// Get the underlying ByJG Repository
$ormRepository = $repository->getRepository();

// Get the Mapper
$mapper = $repository->getMapper();

// Get read executor
$executor = $repository->getExecutor();

// Get write executor (for master/slave setups)
$executorWrite = $repository->getExecutorWrite();

// Create empty model instance
$model = $repository->model();
```

### Example Repository

```php
<?php

namespace RestReferenceArchitecture\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Repository;
use RestReferenceArchitecture\Model\Project;

class ProjectRepository extends BaseRepository
{
    public function __construct(DatabaseExecutor $executor)
    {
        $this->repository = new Repository($executor, Project::class);
    }
}
```

## Filtering and Ordering

`BaseRepository::list()` and `listQuery()` both accept `orderBy` and `filter` parameters. In a REST controller these are typically forwarded straight from HTTP query string parameters.

### `orderBy`

A string or array of `ORDER BY` clauses:

```php
// Single column
$repository->list(page: 0, size: 20, orderBy: 'name ASC');

// Multiple columns
$repository->list(page: 0, size: 20, orderBy: ['created_at DESC', 'name ASC']);
```

### `filter`

An array of `[$condition, $params]` tuples that are appended as `WHERE` clauses:

```php
$filter = [
    ['status = :status', ['status' => 'active']],
    ['created_at >= :since', ['since' => '2024-01-01']],
];

$repository->list(page: 0, size: 20, filter: $filter);
```

### Passing query params from a REST controller

`ProjectController` exposes `orderBy` and `filter` as optional query string parameters and passes them directly to the service (which forwards them to the repository):

```php
#[OA\Parameter(name: "orderBy", in: "query", required: false, schema: new OA\Schema(type: "string"))]
#[OA\Parameter(name: "filter",  in: "query", required: false, schema: new OA\Schema(type: "string"))]
public function listProject(HttpResponse $response, HttpRequest $request): void
{
    $service = Config::get(ProjectService::class);
    $result = $service->list(
        page:    (int) ($request->queryString('page')    ?? 0),
        size:    (int) ($request->queryString('size')    ?? 20),
        orderBy: $request->queryString('orderBy'),
        filter:  $request->queryString('filter'),
    );
    $response->write($result);
}
```

:::info
The `filter` query param expects JSON-encoded tuples when sent over HTTP. Validate and sanitize the value before passing it to the repository in production code.
:::

## Advanced Querying

### The listQuery() Method

The `listQuery()` method provides powerful filtering, pagination, and ordering capabilities:

**Location**: `ByJG\Gluo\Repository\BaseRepository` (byjg/gluo-core)

```php
public function listQuery(
    $tableName = null,
    $fields = [],
    $page = 0,
    $size = 20,
    $orderBy = null,
    $filter = null
): Query
```

#### Parameters

- **`tableName`**: Override the default table (useful for joins)
- **`fields`**: Array of fields to select (default: all fields)
- **`page`**: Page number (0-indexed)
- **`size`**: Results per page
- **`orderBy`**: String or array of ORDER BY clauses
- **`filter`**: Array of WHERE conditions

#### Example: Basic Pagination

```php
$query = $repository->listQuery(
    page: 0,      // First page
    size: 50,     // 50 results
    orderBy: 'name ASC'
);

$results = $repository->getRepository()->getByQuery($query);
```

#### Example: Multiple Order By

```php
$query = $repository->listQuery(
    page: 2,
    size: 25,
    orderBy: ['created_at DESC', 'name ASC']
);
```

#### Example: Filtering

```php
// Filter format: [field_expression, value]
$filter = [
    ['name = [[name]]', ['name' => 'John']],
    ['age > [[min_age]]', ['min_age' => 18]],
    ['status = [[status]]', ['status' => 'active']]
];

$query = $repository->listQuery(
    page: 0,
    size: 20,
    orderBy: 'created_at DESC',
    filter: $filter
);
```

#### Example: Custom Fields

```php
// Only select specific fields
$query = $repository->listQuery(
    fields: ['id', 'name', 'email'],
    page: 0,
    size: 100
);
```

### The listGeneric() Method

Query arbitrary tables dynamically without a mapper:

**Location**: `ByJG\Gluo\Repository\BaseRepository` (byjg/gluo-core)

```php
public function listGeneric(
    $tableName,
    $fields = [],
    $page = 0,
    $size = 20,
    $orderBy = null,
    $filter = null
)
```

#### Example: Query Related Table

```php
// Query a join table or related table
$userRoles = $repository->listGeneric(
    tableName: 'user_roles',
    fields: ['user_id', 'role_id', 'granted_at'],
    filter: [
        ['user_id = [[uid]]', ['uid' => $userId]]
    ],
    orderBy: 'granted_at DESC'
);

// Returns array of associative arrays (not model objects)
foreach ($userRoles as $row) {
    echo $row['role_id'];
}
```

#### Example: Aggregation Query

```php
// Count records by status
$statusCounts = $repository->listGeneric(
    tableName: 'orders',
    fields: ['status', 'COUNT(*) as count'],
    filter: [
        ['created_at > [[date]]', ['date' => '2024-01-01']]
    ],
    orderBy: 'count DESC'
);
```

### Custom Query with getByQuery()

Build completely custom queries using the Query builder:

```php
use ByJG\MicroOrm\Query;

// Complex query with joins
$query = Query::getInstance()
    ->table('project', 'd')
    ->join('user', 'u', 'd.user_id = u.id')
    ->fields(['d.*', 'u.name as user_name'])
    ->where('d.status = :status', ['status' => 'active'])
    ->where('u.role = :role', ['role' => 'admin'])
    ->orderBy(['d.created_at DESC'])
    ->limit(0, 50);

$results = $repository->getByQuery($query);
```

### Advanced Filter Examples

#### IN Clause

```php
$filter = [
    ['status IN (:statuses)', ['statuses' => ['active', 'pending']]]
];
```

#### LIKE Search

```php
$filter = [
    ['name LIKE :search', ['search' => '%john%']]
];
```

#### Multiple Conditions with OR

```php
use ByJG\MicroOrm\Query;

$query = Query::getInstance()
    ->table($repository->getMapper()->getTable())
    ->where('(status = :status1 OR status = :status2)', [
        'status1' => 'active',
        'status2' => 'pending'
    ])
    ->where('created_at > :date', ['date' => '2024-01-01']);

$results = $repository->getByQuery($query);
```

#### Date Range

```php
$filter = [
    ['created_at >= :start', ['start' => '2024-01-01']],
    ['created_at <= :end', ['end' => '2024-12-31']]
];
```

## UUID Handling

Binary UUIDs offer a 16-byte storage footprint without losing usability. The reference architecture wires everything for you.

### Generate a UUID on Demand

```php
$uuid = BaseRepository::getUuid(); // "550E8400-E29B-41D4-A716-446655440000"
```

Use this helper when you need a UUID string outside of ORM inserts (e.g., building fixtures).

### Attribute-Based Binary Columns

Use `TableMySqlUuidPKAttribute` and `FieldUuidAttribute` to keep repositories tiny while persisting binary UUIDs:

```php
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\FieldUuidAttribute;
use ByJG\MicroOrm\Attributes\TableMySqlUuidPKAttribute;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Literal\Literal;

#[TableMySqlUuidPKAttribute("task")]
class Task
{
    #[FieldUuidAttribute(primaryKey: true)]
    protected string|HexUuidLiteral|null $id = null;

    #[FieldAttribute(fieldName: "uuid", syncWithDb: false)]
    protected ?string $uuid = null;

    public function setId(string|Literal|null $id): static
    {
        if ($id instanceof Literal) {
            $id = new HexUuidLiteral($id);
        }
        $this->id = $id;
        return $this;
    }
}
```

`TableMySqlUuidPKAttribute` generates a UUID literal automatically when inserting rows, while `FieldUuidAttribute` handles binary ⇄ string conversion on selects/updates.

The repository stays simple:

```php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Repository;

class TaskRepository extends BaseRepository
{
    public function __construct(DatabaseExecutor $executor)
    {
        $this->repository = new Repository($executor, Task::class);
    }
}
```

### Querying by UUID

`BaseRepository::get()` uses `HexUuidLiteral::create($itemId)` internally, so passing a UUID string “just works”:

```php
$entity = $repository->get('550e8400-e29b-41d4-a716-446655440000');
```

When you need to build queries manually, wrap UUIDs with `HexUuidLiteral::create()` to generate the correct binary literal for the database driver.

## Custom Query Methods

Add domain-specific query methods to your repositories or models.

### In Repository Pattern

```php
class ProjectRepository extends BaseRepository
{
    public function findByStatus(string $status): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where('status = :status', ['status' => $status])
            ->orderBy(['created_at DESC']);

        return $this->repository->getByQuery($query);
    }

    public function findByDateRange(string $start, string $end): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where('created_at >= :start', ['start' => $start])
            ->where('created_at <= :end', ['end' => $end]);

        return $this->repository->getByQuery($query);
    }

    public function countByStatus(string $status): int
    {
        $result = $this->listGeneric(
            tableName: $this->repository->getMapper()->getTable(),
            fields: ['COUNT(*) as count'],
            filter: [
                ['status = :status', ['status' => $status]]
            ]
        );

        return (int) $result[0]['count'];
    }

    public function findRecent(int $limit = 10): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->orderBy(['created_at DESC'])
            ->limit(0, $limit);

        return $this->repository->getByQuery($query);
    }
}
```

### In ActiveRecord Pattern

Add static query methods directly to your model:

**Location**: `src/Model/Note.php`

```php
class Note
{
    use ActiveRecord;

    /**
     * Find records by task id (the binary(16) foreign key to task)
     *
     * @param mixed $taskId
     * @return null|Note[]
     */
    public static function getByTaskId($taskId): ?array
    {
        $query = Query::getInstance()
            ->table(self::$repository->getMapper()->getTable(), 'alias')
            ->where('alias.task_id = :value', ['value' => $taskId]);

        return self::query($query);
    }

    /**
     * Find recent notes
     *
     * @return null|Note[]
     */
    public static function findRecent(): ?array
    {
        $query = Query::getInstance()
            ->table(self::$repository->getMapper()->getTable())
            ->orderBy(['created_at DESC']);

        return self::query($query);
    }

    /**
     * Get single record by body
     *
     * @param string $body
     * @return Note|null
     */
    public static function getByBody(string $body): ?Note
    {
        $query = Query::getInstance()
            ->table(self::$repository->getMapper()->getTable())
            ->where('body = :body', ['body' => $body]);

        $results = self::query($query);
        return $results[0] ?? null;
    }
}
```

#### Usage

```php
// Call static methods directly on the model
$notes = Note::getByTaskId('550e8400-e29b-41d4-a716-446655440000');
$recent = Note::findRecent();
$note = Note::getByBody('Remember to follow up');
```

## Transaction Management

### Using the Executor

```php
$executor = $repository->getExecutorWrite();

try {
    $executor->beginTransaction();

    // Perform multiple operations
    $repository->save($model1);
    $repository->save($model2);
    $repository->save($model3);

    $executor->commitTransaction();
} catch (\Exception $e) {
    $executor->rollbackTransaction();
    throw $e;
}
```

### Multi-Repository Transactions

```php
use ByJG\Config\Config;

$projectRepo = Config::get(ProjectRepository::class);
$userRepo = Config::get(UserRepository::class);

// Ensure both use the same executor
$executor = $projectRepo->getExecutorWrite();

try {
    $executor->beginTransaction();

    $user = $userRepo->save($userData);
    $project = $projectRepo->save($projectData);

    // Link them
    $project->setUserId($user->getId());
    $projectRepo->save($project);

    $executor->commitTransaction();
} catch (\Exception $e) {
    $executor->rollbackTransaction();
    throw $e;
}
```

### Transaction Wrapper

Create a helper for cleaner transaction handling:

```php
trait TransactionHelper
{
    protected function transaction(callable $callback)
    {
        $executor = $this->getExecutorWrite();

        try {
            $executor->beginTransaction();
            $result = $callback($executor);
            $executor->commitTransaction();
            return $result;
        } catch (\Exception $e) {
            $executor->rollbackTransaction();
            throw $e;
        }
    }
}

// Usage in repository
class ProjectRepository extends BaseRepository
{
    use TransactionHelper;

    public function createWithRelated($projectData, $relatedData)
    {
        return $this->transaction(function($executor) use ($projectData, $relatedData) {
            $project = $this->save($projectData);
            // Save related data
            return $project;
        });
    }
}
```

## Performance Optimization

### Eager Loading with Joins

```php
public function listWithUser(): array
{
    $query = Query::getInstance()
        ->table($this->repository->getMapper()->getTable(), 'd')
        ->join('user', 'u', 'd.user_id = u.id')
        ->fields([
            'd.*',
            'u.name as user_name',
            'u.email as user_email'
        ]);

    return $this->repository->getByQuery($query);
}
```

### Selective Field Loading

```php
// Only load fields you need
$query = Query::getInstance()
    ->table($this->repository->getMapper()->getTable())
    ->fields(['id', 'name', 'status'])  // Don't load large text fields
    ->where('status = :status', ['status' => 'active']);
```

### Batch Operations

```php
public function bulkInsert(array $models): void
{
    $executor = $this->getExecutorWrite();

    try {
        $executor->beginTransaction();

        foreach ($models as $model) {
            $this->repository->save($model);
        }

        $executor->commitTransaction();
    } catch (\Exception $e) {
        $executor->rollbackTransaction();
        throw $e;
    }
}
```

### Caching Results

```php
use Psr\SimpleCache\CacheInterface;

class ProjectRepository extends BaseRepository
{
    protected CacheInterface $cache;

    public function findByIdCached($id)
    {
        $key = "project:{$id}";

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $project = $this->get($id);
        $this->cache->set($key, $project, 3600); // 1 hour

        return $project;
    }

    public function save($model, $updateConstraint = null): mixed
    {
        $result = parent::save($model, $updateConstraint);

        // Invalidate cache
        $key = "project:{$result->getId()}";
        $this->cache->delete($key);

        return $result;
    }
}
```

## ActiveRecord Pattern

The ActiveRecord pattern provides an alternative to the Repository pattern where models handle their own persistence.

### Basic ActiveRecord Usage

```php
use RestReferenceArchitecture\Model\Note;

// Create
$note = new Note();
$note->setTaskUuid('550e8400-e29b-41d4-a716-446655440000');
$note->setBody('Test');
$note->save();

// Read
$note = Note::get(1);

// Update
$note->setBody('Updated');
$note->save();

// Delete
$note->delete();

// Query
$results = Note::getByTaskId('550e8400-e29b-41d4-a716-446655440000');
```

### Adding Custom Methods

See [Custom Query Methods](#custom-query-methods) section above.

### When to Use ActiveRecord vs Repository

**Use ActiveRecord when:**
- Simple CRUD operations dominate
- Models are independent
- Rapid development is priority
- Small to medium projects

**Use Repository when:**
- Complex business logic
- Multiple data sources
- Need for testing isolation
- Large enterprise applications

See [Architecture Decision Guide](../concepts/architecture.md) for detailed comparison.

## Advanced Examples

### Full-Text Search

```php
public function search(string $term): array
{
    $query = Query::getInstance()
        ->table($this->repository->getMapper()->getTable())
        ->where(
            'MATCH(name, description) AGAINST (:term IN BOOLEAN MODE)',
            ['term' => $term . '*']
        );

    return $this->repository->getByQuery($query);
}
```

### Hierarchical Queries

```php
public function getWithChildren(int $parentId): array
{
    $query = Query::getInstance()
        ->table($this->repository->getMapper()->getTable(), 'parent')
        ->join(
            $this->repository->getMapper()->getTable(),
            'child',
            'parent.id = child.parent_id'
        )
        ->where('parent.id = :id', ['id' => $parentId])
        ->fields(['parent.*', 'child.id as child_id', 'child.name as child_name']);

    return $this->repository->getByQuery($query);
}
```

### Soft Deletes

```php
public function softDelete($model): void
{
    $model->setDeletedAt(date('Y-m-d H:i:s'));
    $this->repository->save($model);
}

public function listActive($page = 0, $size = 20): array
{
    $query = $this->listQuery(
        page: $page,
        size: $size,
        filter: [
            ['deleted_at IS NULL', []]
        ]
    );

    return $this->repository->getByQuery($query);
}
```

## Best Practices

1. **Use Type Hints**: Always type-hint return values and parameters
2. **Document Complex Queries**: Add PHPDoc comments explaining query logic
3. **Cache Expensive Queries**: Use PSR-16 cache for slow queries
4. **Index Database Fields**: Ensure WHERE/ORDER BY fields are indexed
5. **Validate Before Save**: Check constraints before database operations
6. **Use Transactions**: Group related operations in transactions
7. **Pagination**: Always paginate list queries
8. **Avoid N+1 Queries**: Use joins for related data

## Related Documentation

- [ORM Usage Guide](orm.md)
- [Service Patterns](services.md)
- [Architecture Decisions](../concepts/architecture.md)
- [Testing Guide](testing.md)
