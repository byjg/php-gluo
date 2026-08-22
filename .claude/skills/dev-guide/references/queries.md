# Advanced ORM Query Patterns

## Insert, Update, Delete

### Standard entity-based writes (the common case)

`save()` and `delete()` work through the model — they are the right choice whenever you
have a model instance in hand.

```php
// INSERT — PK is null, ORM inserts and populates the PK on the returned model
$product = new Product();
$product->setName('Widget');
$product->setPrice(9.99);
$this->repository->save($product);
echo $product->getId();   // auto-incremented ID is now set

// UPDATE — PK is set, ORM issues an UPDATE
$product = $this->repository->get(42);
$product->setPrice(12.50);
$this->repository->save($product);

// DELETE by PK — hard delete (or soft-delete if model has OaDeletedAt trait)
$this->repository->delete(42);
// Composite PK:
$this->repository->delete(['order_id' => 5, 'product_id' => 12]);
```

`save()` determines insert vs update by checking whether the PK is null. Soft-delete
models (those with the `OaDeletedAt` trait / `deleted_at` column) have `delete()` set
`deleted_at` instead of removing the row.

### Conditional writes without loading an entity

For bulk updates, mass deletes, or any time loading the entity first would be wasteful,
use the write query objects directly. Execute them via the repository's write executor.

**UpdateQuery — update specific columns matching a WHERE clause:**

```php
use ByJG\MicroOrm\UpdateQuery;

$query = UpdateQuery::getInstance()
    ->table('product')
    ->set('price', 0.00)
    ->set('name', 'Discontinued')
    ->where('category_id = :cid', ['cid' => 7]);

// Execute through the executor (UpdateQuery has no public method on Repository)
$sqlStatement = $query->build($this->repository->getExecutorWrite()->getHelper());
$this->repository->getExecutorWrite()->execute($sqlStatement);
```

Use `setLiteral()` when the value should be injected as raw SQL (e.g. a DB function):
```php
$query->setLiteral('updated_at', 'NOW()');
```

**DeleteQuery — delete rows matching a WHERE clause:**

```php
use ByJG\MicroOrm\DeleteQuery;

$query = DeleteQuery::getInstance()
    ->table('product')
    ->where('deleted_at < :cutoff', ['cutoff' => '2024-01-01 00:00:00']);

// deleteByQuery() is a public method on Repository
$this->repository->deleteByQuery($query);
```

**InsertBulkQuery — insert many rows in a single SQL statement:**

```php
use ByJG\MicroOrm\InsertBulkQuery;

$bulk = InsertBulkQuery::getInstance('product', ['name', 'price', 'category_id'])
    ->values(['name' => 'Widget A', 'price' => 9.99, 'category_id' => 1])
    ->values(['name' => 'Widget B', 'price' => 14.99, 'category_id' => 1])
    ->values(['name' => 'Widget C', 'price' => 4.99, 'category_id' => 2]);

$sqlStatement = $bulk->build($this->repository->getExecutorWrite()->getHelper());
$this->repository->getExecutorWrite()->execute($sqlStatement);
```

### bulkExecute — multiple write operations in one transaction

Pass any mix of `UpdateQuery`, `DeleteQuery`, or `InsertBulkQuery` objects. If any
statement fails, the whole batch is rolled back automatically.

```php
$this->repository->bulkExecute([
    UpdateQuery::getInstance()->table('product')->set('active', 0)->where('id = :id', ['id' => 1]),
    DeleteQuery::getInstance()->table('audit_log')->where('created_at < :d', ['d' => '2023-01-01']),
]);
```

---

## The Query Object

`ByJG\MicroOrm\Query` is the main query builder. You build it and pass it to
`$this->repository->getByQuery($query)` (via `BaseRepository::getByQuery()`).

```php
use ByJG\MicroOrm\Query;

$query = Query::getInstance()
    ->table('product')                          // required unless using BaseRepository::list()
    ->fields(['id', 'name', 'price'])           // optional: restrict returned columns
    ->where('name = :name', ['name' => 'Widget'])
    ->orderBy(['name', 'price DESC'])
    ->limit($page * $size, $size);              // offset, count

$results = $this->repository->getByQuery($query);
```

## BaseRepository helpers already handle pagination

`BaseRepository::list()` wraps all of this:
```php
// In the service layer (or directly in a controller for ActiveRecord):
$results = $this->baseRepository->list(
    page: 0,
    size: 20,
    orderBy: 'name',           // or ['name', 'created_at DESC']
    filter: [
        ['name LIKE :name', ['name' => '%widget%']],
        ['price > :min',    ['min'  => 10.00]],
    ]
);
```

The `filter` parameter is an array of `[$whereClause, $bindings]` pairs — each pair becomes an
additional `AND` condition.

## Custom query with JOIN

```php
$query = Query::getInstance()
    ->table('product', 'p')
    ->join('category', 'c', 'c.id = p.category_id')
    ->fields(['p.id', 'p.name', 'c.name as category_name'])
    ->where('p.deleted_at IS NULL')
    ->orderBy(['p.name']);

return $this->repository->getByQuery($query);
```

## Multi-entity JOIN queries with ORM::getQueryInstance() (Repository pattern)

When you JOIN two tables and want both entities hydrated, use `ORM::getQueryInstance()` to
auto-build the JOIN, then pass the second repository's `Mapper` to `getByQuery()`.
Results come back as `[[entityA, entityB], ...]`.

**Step 1: declare the relationship via `parentTable` on the FK field** (in the child model):

```php
#[TableAttribute("post")]
class Post
{
    #[FieldAttribute(primaryKey: true)]
    protected int|null $id = null;

    // parentTable registers the post ↔ user relationship automatically
    // when a Repository for Post is first instantiated.
    #[FieldAttribute(fieldName: "user_id", parentTable: "user")]
    protected int|null $userId = null;
}
```

**Step 2: build the query and execute with multi-mapper**:

```php
use ByJG\MicroOrm\ORM;

// ORM::getQueryInstance() looks up the registered relationship and generates the JOIN.
// Equivalent to: Query::getInstance()->table('user')->join('post', 'post.user_id = user.id')
$query = ORM::getQueryInstance("user", "post")
    ->where('user.id = :id', ['id' => $userId]);

// Pass the joined table's mapper — each row is hydrated into [User, Post]
$results = $userRepo->getByQuery($query, [$postRepo->getMapper()]);

foreach ($results as [$user, $post]) {
    echo $user->getName() . " wrote: " . $post->getTitle();
}
```

Without the extra mapper argument, `getByQuery()` only hydrates the primary entity (User)
and ignores the joined columns. Add it whenever you need the joined entity as an object.

**Three-table example:**

```php
$query = ORM::getQueryInstance("user", "post", "comment");

$results = $userRepo->getByQuery($query, [
    $postRepo->getMapper(),
    $commentRepo->getMapper(),
]);

foreach ($results as [$user, $post, $comment]) {
    // each row is fully hydrated into three entities
}
```

## getIterator() vs getByQuery() — choosing the right return shape

`getByQuery()` always runs results through the Mapper and returns model instances. That's
the right choice when you need proper entity objects. But for complex queries with custom
SELECT fields, aggregates, or columns that don't map to any single entity, use `getIterator()`
instead — it returns raw `['field' => 'value']` arrays and imposes no entity constraints.

```php
use ByJG\MicroOrm\Query;

// getByQuery() — hydrated entities, constrained to the mapper's entity shape
$query = Query::getInstance()
    ->table('product')
    ->where('deleted_at IS NULL');

$products = $this->repository->getByQuery($query);  // Product[]

// getIterator() — raw field=>value arrays, full flexibility
$query = Query::getInstance()
    ->table('product', 'p')
    ->join('category', 'c', 'c.id = p.category_id')
    ->fields(['p.id', 'p.name', 'c.name as category_name', 'COUNT(*) as order_count'])
    ->where('p.deleted_at IS NULL')
    ->orderBy(['order_count DESC']);

$iterator = $this->repository->getIterator($query);
$rows = $iterator->toArray();   // [['id'=>1,'name'=>'Widget','category_name'=>'Tools','order_count'=>5], ...]
```

You can also iterate row by row — useful for large result sets where loading everything into
memory at once is undesirable:

```php
$iterator = $this->repository->getIterator($query);
foreach ($iterator as $row) {
    $data = $row->toArray();        // ['field' => 'value', ...]
    // process one row at a time
}
```

**Summary:**

| Aspect | getByQuery() | getIterator() |
| --- | --- | --- |
| Returns | Model instances | Raw field=>value arrays |
| Best for | Standard CRUD, need entity methods | Custom projections, aggregates, reporting |
| Constraint | Must match mapper entity structure | None - any columns, any aliases |




## Raw query (escape hatch)

For complex SQL that the query builder can't express, use `QueryRaw` or the executor directly:
```php
use ByJG\MicroOrm\QueryRaw;

$sql = "SELECT p.id, COUNT(o.id) as order_count
        FROM product p
        LEFT JOIN orders o ON o.product_id = p.id
        GROUP BY p.id";

$query = QueryRaw::getInstance($sql);
return $this->repository->getByQuery($query);
```

Or use the executor directly for a scalar:
```php
$count = $this->repository->getExecutor()
    ->getScalar("SELECT COUNT(*) FROM product WHERE deleted_at IS NULL");
```

## Getting a single record by field (not PK)

```php
// In your repository class:
public function getBySlug(string $slug): ?Product
{
    $query = Query::getInstance()
        ->table('product')
        ->where('slug = :slug', ['slug' => $slug]);
    $results = $this->repository->getByQuery($query);
    return $results[0] ?? null;
}
```

## Soft-delete awareness

If your model uses `OaDeletedAt`, it adds a `deleted_at` column. The ORM does NOT
automatically filter deleted records. Add the condition yourself:
```php
$query = Query::getInstance()
    ->table('product')
    ->where('deleted_at IS NULL');
```

To soft-delete a record, set `deleted_at` and save (don't call `delete()` which does a hard delete):
```php
$model->setDeletedAt(date('Y-m-d H:i:s'));
$this->repository->save($model);
```

## UUID primary keys

Models using UUID PK have type `string|LiteralInterface|null` for the id field.

```php
// Create (ORM generates the UUID automatically via TableMySqlUuidPKAttribute):
$model = new Task();
$model->setProjectId(1);
$model->setTitle('Draft the proposal');
$repo->save($model);
// $model->getId() now contains the hex UUID

// Retrieve by UUID:
$model = $repo->get(new HexUuidLiteral('550e8400-e29b-41d4-a716-446655440000'));
// or by the readable UUID string:
$model = $repo->get('550e8400e29b41d4a716446655440000'); // hex without dashes
```

## ActiveRecord queries

The ActiveRecord trait offers several querying styles. Choose the simplest one that fits.

### Basic retrieval
```php
$model = Note::get($id);                        // by PK → model or null
$all   = Note::all($page, $size);               // paginated, returns model[]
```

### where() — fluent, modern (preferred for simple conditions)

`where()` returns an `ActiveRecordQuery` (extends `Query`) pre-loaded with the model's table.
Chain conditions, then call a terminal method:

```php
// Single result
$model = Note::where('body = :b', ['b' => 'first note'])->first();        // or null
$model = Note::where('body = :b', ['b' => 'first note'])->firstOrFail();  // throws NotFoundException

// Multiple results
$models = Note::where('body IS NOT NULL')
    ->orderBy(['created_at DESC'])
    ->limit(0, 20)
    ->toArray();          // returns model[]

// Existence check
$exists = Note::where('body = :b', ['b' => 'first note'])->exists(); // bool
```

Chain multiple conditions with additional `->where()` calls (each is ANDed):
```php
$models = Note::where('task_uuid = :t', ['t' => $taskUuid])
    ->where('body LIKE :b', ['b' => '%urgent%'])
    ->toArray();
```

### newQuery() — build step by step
```php
$query = Note::newQuery()
    ->where('body LIKE :b', ['b' => '%foo%'])
    ->orderBy(['id DESC'])
    ->limit(0, 10);

$models = $query->toArray();
```

### filter() — legacy IteratorFilter (use where() instead for new code)
```php
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Core\Enum\Relation;

$filter = (new IteratorFilter())->and('body', Relation::EQUAL, 'foo');
$models = Note::filter($filter, page: 0, limit: 50);
```

`filter()` is still valid but `where()` is simpler for most cases.

### query() — full Query object (for complex cases)

When you need things `where()` can't express (custom fields, aliased tables), build a full
`Query` object and pass it to `query()`:

```php
use ByJG\MicroOrm\Query;

$query = Query::getInstance()
    ->table(Note::tableName(), 'n')
    ->where('n.body = :body', ['body' => 'foo']);

$models = Note::query($query);
```

### joinWith() — join across tables using registered relationships

`joinWith()` returns a `Query` pre-built with automatic JOINs. You then pass it to `query()`.

**How it works:** it calls `ORM::getQueryInstance(otherTable, ..., thisTable)` which looks up
registered relationships to determine the correct join conditions — you don't write the `ON`
clause manually.

**The "dependencies"** are relationships registered in the ORM registry. They are registered
automatically when a model has a FK field marked with `parentTable`:

```php
// In the child model (e.g. OrderItem references order):
#[FieldAttribute(fieldName: "order_id", parentTable: "order")]
protected int|null $orderId = null;
// → repository instantiation for this model registers the order ↔ order_item relationship
```

Or registered manually in a config file:
```php
// ORM::addRelationship(parentTable, childTable, foreignKeyInChild, primaryKeyInParent)
ORM::addRelationship('order', 'order_item', 'order_id', 'id');
```

Once relationships are registered, `joinWith()` finds the path automatically:
```php
// In OrderItem's model or a repository method:
$query = OrderItem::joinWith('order')   // adds the model's own table automatically
    ->where('order.status = :s', ['s' => 'pending'])
    ->orderBy(['order.created_at DESC']);

$items = OrderItem::query($query);
```

For three-table joins (ORM finds the path through intermediate tables):
```php
$query = LineItem::joinWith('order', 'customer');
$lineItems = LineItem::query($query);
```

> If no relationship is registered between the tables, `joinWith()` / `ORM::getQueryInstance()`
> throws `InvalidArgumentException`. Fall back to manual `Query` with explicit `->join()` in that case.

## Transactions

Use the executor for explicit transactions:
```php
$executor = $this->repository->getExecutorWrite();
$executor->beginTransaction();
try {
    $this->repository->save($modelA);
    $this->repository->save($modelB);
    $executor->commitTransaction();
} catch (\Throwable $e) {
    $executor->rollbackTransaction();
    throw $e;
}
```