# Serialization Reference: Serialize and ObjectCopy

Two classes cover the full round-trip: `Serialize` converts objects/arrays **out** to arrays
or JSON; `ObjectCopy` copies data **into** an existing object.

```
HTTP body (array)  ──ObjectCopy──►  Entity (object)
Entity (object)    ──Serialize───►  array / JSON
```

---

## Writing to the response (no Serialize needed)

`$response->write()` uses the same getter-based serializer internally. Pass the model directly
whenever you don't need to filter fields first:

```php
$response->write($model);          // object → JSON (serializer calls getters automatically)
$response->write($arrayOfModels);  // array of objects works too
```

Only reach for `Serialize` explicitly when you need to filter, reshape, or get an array value.

---

## Serialize — object/array → array

`Serialize::from()` reads an object's `getXxx()` methods (stripping the `get` prefix and
lower-casing the first character) and returns a plain array. Works on arrays and `stdClass`
too — in that case it just passes through the keys.

### Basic usage

```php
use ByJG\Serializer\Serialize;

$array = Serialize::from($model)->toArray();
// getName() → 'name', getPrice() → 'price', etc.
```

### Exclude specific fields

Remove sensitive or internal-only fields before writing the response:

```php
$publicData = Serialize::from($model)
    ->withIgnoreProperties(['password', 'deletedAt'])
    ->toArray();
$response->write($publicData);
```

Property names match the getter key (i.e. `getName()` → `'name'`, `getDeletedAt()` → `'deletedAt'`).

### Include only specific fields

When an endpoint should return a narrow subset (e.g., public profile):

```php
$publicProfile = Serialize::from($model)
    ->withOnlyProperties(['id', 'name', 'email'])
    ->toArray();
$response->write($publicProfile);
```

`withIgnoreProperties` and `withOnlyProperties` are mutually exclusive — use one or the other.

### Strip null fields

By default Serialize includes `null` values. Omit them for cleaner API output:

```php
$data = Serialize::from($model)
    ->withDoNotParseNullValues()
    ->toArray();
```

The framework itself uses this internally in `ValidateRequest::getPayload()` to strip client-sent
nulls before validation — so `getPayload()` already returns a null-free array.

### Convert to JSON string

Useful for logging or passing to an external service:

```php
$json = Serialize::from($model)->toJson();
```

### Role-based field selection pattern

A common pattern for endpoints that return different data per role:

```php
$role = JwtContext::getRole();
$data = Serialize::from($model)
    ->withOnlyProperties(
        $role === User::ROLE_ADMIN
            ? ['id', 'name', 'price', 'internalNotes', 'createdAt']
            : ['id', 'name', 'price']
    )
    ->toArray();
$response->write($data);
```

---

## ObjectCopy — copy data into an object

`ObjectCopy::copy($source, $target)` reads the source (array or object) and writes each
value to the target using `setXxx()` methods, falling back to public properties. Matching
is by property name, case-insensitive.

### Populate a new entity from request data

```php
use ByJG\Serializer\ObjectCopy;

$model = new Product();
ObjectCopy::copy(ValidateRequest::getPayload(), $model);
// ['name' => 'Widget', 'price' => 9.99] → $model->setName('Widget'); $model->setPrice(9.99)
$this->repository->save($model);
```

You rarely need this explicitly because `BaseService::create($payload)` does it for you:

```php
// Inside BaseService::create():
$model = new $modelClass();
ObjectCopy::copy($payload, $model);   // ← this is what happens under the hood
$this->baseRepository->save($model);
```

### Update an entity (merge changed fields)

`BaseService::update($payload)` also uses `ObjectCopy` internally — it fetches the entity
by PK, then copies the payload over it, so only changed fields are overwritten:

```php
// Inside BaseService::update():
$model = $this->getOrFail($pkValue);
ObjectCopy::copy($payload, $model);   // merges; unchanged fields stay as-is
$this->baseRepository->save($model);
```

### Copy between two objects

```php
ObjectCopy::copy($sourceModel, $targetModel);
// $sourceModel->getName() → $targetModel->setName(...)
```

### Case transformation with PropertyHandler

If your JSON body uses `snake_case` keys but your model has `camelCase` setters, pass a
handler:

```php
use ByJG\Serializer\PropertyHandler\SnakeToCamelCase;

// {'first_name': 'John'} → setFirstName('John')
ObjectCopy::copy($snakeCaseBody, $model, new SnakeToCamelCase());
```

```php
use ByJG\Serializer\PropertyHandler\CamelToSnakeCase;

// getName() → 'first_name' key in target array
ObjectCopy::copy($model, $snakeCaseArray, new CamelToSnakeCase());
```

> This architecture uses camelCase throughout (PHP getters, JSON keys from the serializer),
> so handlers are rarely needed in practice. They're mainly useful when integrating with
> external systems that send snake_case.

---

## Quick reference

| Goal | Code |
|------|------|
| Write model to response | `$response->write($model)` |
| Model to array | `Serialize::from($model)->toArray()` |
| Exclude fields | `->withIgnoreProperties(['fieldName'])` |
| Only specific fields | `->withOnlyProperties(['id', 'name'])` |
| Strip nulls | `->withDoNotParseNullValues()` |
| To JSON string | `->toJson()` |
| Array/body into entity | `ObjectCopy::copy($array, $entity)` |
| Object into object | `ObjectCopy::copy($source, $target)` |
| snake_case body → entity | `ObjectCopy::copy($body, $entity, new SnakeToCamelCase())` |

---

## What each does NOT do

- **`Serialize`** does not populate objects — it only reads/converts. There is no `->into()`.
- **`ObjectCopy`** does not recurse into nested objects — it copies only top-level properties.
- **`$response->write()`** already serializes; wrapping it in `Serialize::from()->toArray()` first
  is redundant unless you need to filter fields.