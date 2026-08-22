---
sidebar_position: 150
title: ORM & Repository
---

# Database ORM

The reference architecture uses [byjg/micro-orm](https://github.com/byjg/micro-orm) with PHP 8 attributes. Your models declare the mapping, repositories receive the shared `DatabaseExecutor`, and `BaseRepository` supplies common helpers.

## 1. Model Mapping with Attributes

Annotate your models with `TableAttribute` / `FieldAttribute` (or the UUID variants) so Micro ORM knows how to persist each property.

```php
<?php

namespace RestReferenceArchitecture\Model;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;

#[TableAttribute("project")]
class Project
{
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    protected ?int $id = null;

    #[FieldAttribute(fieldName: "name")]
    protected ?string $name = null;

    #[FieldAttribute(fieldName: "description")]
    protected ?string $description = null;

    // getters/setters omitted for brevity
}
```

Need UUID support? Use `#[TableMySqlUuidPKAttribute]` + `#[FieldUuidAttribute]` as shown in `src/Model/Task.php` and `src/Model/User.php`. They automatically read/write binary UUIDs via `HexUuidLiteral`.

## 2. Repository Definition

Repositories extend `ByJG\Gluo\Repository\BaseRepository`. Inject `ByJG\AnyDataset\Db\DatabaseExecutor` and hand it to `ByJG\MicroOrm\Repository`, pointing at your model class:

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

`BaseRepository` already exposes useful helpers:

```php
$project = $repository->get($id);
$list  = $repository->list(page: 0, size: 20);
$model = $repository->model();          // New instance of mapped entity
$repo  = $repository->getRepository();  // Direct access to ByJG\Repository
$mapper = $repository->getMapper();     // Underlying Mapper instance
```

Saving and deleting records delegates to Micro ORM while keeping literals in sync:

```php
$model = $repository->model()
    ->setName('hello world');

$saved = $repository->save($model);
$repository->delete($saved->getId());
```

## 3. Custom Queries

Use `ByJG\MicroOrm\Query` (or any `QueryBuilderInterface`) for filtered results:

```php
use ByJG\MicroOrm\Query;

class ProjectRepository extends BaseRepository
{
    public function findByName(string $value): array
    {
        $query = Query::getInstance()
            ->table($this->getMapper()->getTable())
            ->where('name = :value', ['value' => $value])
            ->orderBy(['id DESC']);

        return $this->getRepository()->getByQuery($query);
    }
}
```

`listQuery()` (in `BaseRepository`) already builds paginated queries—pass filters, order clauses, and selected fields as needed. For raw arrays (instead of model hydration), call `listGeneric()` to run ad-hoc queries while reusing the same pagination helpers.

## 4. Working with UUIDs

Binary UUID columns are transparent when you rely on the attribute helpers:

- `#[TableMySqlUuidPKAttribute("task")]` wires the table and default UUID generator.
- `#[FieldUuidAttribute(primaryKey: true)]` handles binary ⇄ string conversion automatically.
- `HexUuidLiteral::create($uuid)` lets you query by UUID strings without manual hex handling.

`BaseRepository::save()` normalizes any UUID `Literal` back to a formatted string so controllers/tests can keep using human-readable IDs.

## 5. Services and REST Controllers

Repositories are registered in `config/<env>/04-repositories.php` and injected into services (see `src/Service/ProjectService.php`). Services orchestrate repositories, and REST controllers resolve services via the PSR-11 container. For deeper patterns (DTOs, filters, transactions), read [Repository Patterns](repository-advanced.md) and [Service Layer](services.md).

## 6. ActiveRecord REST Endpoints

When using the ActiveRecord pattern, the controller calls model methods directly — no separate service class is required. The reference implementation is `src/Controller/NoteController.php`.

### GET — Fetch by ID

```php title="src/Controller/NoteController.php (GET by id)"
#[RequireAuthenticated]
public function getNote(HttpResponse $response, HttpRequest $request): void
{
    $model = Note::get($request->attribute('id'));

    if (is_null($model)) {
        throw new Error404Exception("Note not found");
    }

    $response->write($model);
}
```

### GET — List with Pagination

```php title="src/Controller/NoteController.php (list)"
#[RequireAuthenticated]
public function listNote(HttpResponse $response, HttpRequest $request): void
{
    $models = Note::all((int)($request->queryString('page') ?? 0), (int)($request->queryString('size') ?? 50));
    $response->write($models);
}
```

### POST — Create

Use `Note::new($payload)` to hydrate a new instance from an array, then call `save()`:

```php title="src/Controller/NoteController.php (create)"
#[RequireRole(User::ROLE_ADMIN)]
#[ValidateRequest]
public function postNote(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();

    $model = Note::new($payload);
    $model->save();

    $response->write(["id" => $model->getId()]);
}
```

### PUT — Update

Use `->fill($payload)` to apply changes to an existing instance, then call `save()`:

```php title="src/Controller/NoteController.php (update)"
#[RequireRole(User::ROLE_ADMIN)]
#[ValidateRequest]
public function putNote(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();

    $model = Note::get($payload['id'] ?? null);

    if (is_null($model)) {
        throw new Error404Exception("Note not found");
    }

    $model->fill($payload);
    $model->save();
}
```

**Key differences from service-based controllers:**

| Service pattern | ActiveRecord pattern |
|---|---|
| `$this->projectService->create($payload)` (injected) | `Note::new($payload)->save()` |
| `$this->projectService->getOrFail($id)` (injected) | `Note::get($id)` |
| `$service->update($payload)` | `$model->fill($payload); $model->save()` |

## 7. Read-only and computed fields (the Note example)

`src/Model/Note.php` demonstrates three field patterns beyond a plain column. All
three rely on `syncWithDb: false`, which keeps a property in the mapper (so it is selected
and hydrated) but excludes it from writes.

### Binary foreign key

`task_id` is a real `binary(16)` foreign key to `task(id)`, mapped with the same helper as
a UUID primary key:

```php title="src/Model/Note.php"
#[OA\Property(type: "string", format: "string")]
#[FieldUuidAttribute(fieldName: "task_id")]
protected string|LiteralInterface|null $taskId = null;
```

`FieldUuidAttribute` converts binary ⇄ formatted UUID string on select/update, so callers
pass and receive human-readable UUIDs (`Note::getByTaskId('…')`).

### Database virtual column (`body_length`)

The database computes this on every read; the app never writes it. The migration declares a
MySQL **generated** column, and the model maps it read-only:

```sql title="db/migrations/up/00001-create-table-examples.sql"
body_length int generated always as (char_length(body)) virtual
```

```php title="src/Model/Note.php"
#[OA\Property(type: "integer", format: "int32", nullable: true)]
#[FieldAttribute(fieldName: "body_length", syncWithDb: false)]
protected int|null $bodyLength = null;
```

It needs a **setter** (`setBodyLength`) even though it is read-only: hydration copies DB
rows onto the model through setters, so a getter alone would leave the property `null`.

### Computed field in PHP (`days`)

A value the database *cannot* generate — "days since creation" depends on `NOW()`, which
MySQL rejects in a generated-column expression. So it is derived in PHP from `created_at`.
The `FieldAttribute(syncWithDb: false)` only keeps it out of writes; the value comes from
the getter, not from a column:

```php title="src/Model/Note.php"
#[OA\Property(type: "integer", format: "int32", nullable: true)]
#[FieldAttribute(fieldName: "created_at", syncWithDb: false)]
protected int|null $days = null;

public function getDays(): int|null
{
    if (empty($this->createdAt)) {
        return null;
    }
    $timestamp = strtotime($this->createdAt);
    return $timestamp === false ? null : (int)floor((time() - $timestamp) / 86400);
}
```

### Soft delete

Note also uses `OaDeletedAt`, so `DELETE /note/{id}` calls `$model->delete()`, which sets
`deleted_at` instead of removing the row; `Note::get()`/`Note::all()` then skip it. See
[Traits](../reference/traits.md#oadeletedat-trait) for the trait details.

## 8. Joining across relationships (parentTable)

Each foreign key declares its parent table, so the ORM knows the relationship graph:

```php title="src/Model/Task.php"
#[FieldAttribute(fieldName: "project_id", parentTable: "project")]
protected int|null $projectId = null;
```
```php title="src/Model/Note.php"
#[FieldUuidAttribute(fieldName: "task_id", parentTable: "task")]
protected string|LiteralInterface|null $taskId = null;
```

With those declared, `Model::joinWith()` (and `Query::joinRelated()` for the Repository
pattern) builds the JOIN condition for you. `Note` has no `project_id`, yet "all notes in
a project" is a single query across `note → task → project`.

You pass the **model classes** in the path, not table-name strings. Passing a class
registers that entity's mapper on demand — reflection only, no database connection — so
its `parentTable` relationships are known even on a request that only touched `Note`.
`Task` is named as the through-entity (the same way Eloquent's `hasManyThrough` names its
through-model), so its `task → project` edge is registered and the middle join resolves:

```php title="src/Model/Note.php"
public static function getByProjectId($projectId): array
{
    $query = self::joinWith(Task::class, Project::class)  // FROM note JOIN task JOIN project
        ->field('note.*')                                 // keep only note columns to hydrate Note
        ->where('project.id = :id', ['id' => $projectId]);
    return self::query($query);
}
```

Exposed as `GET /project/{projectId}/note` (see `NoteController::listNotesByProject`).

> Because the mappers register on demand from the classes you name, the repositories stay
> ordinary lazy singletons — no bootstrap-time database connection is forced.
