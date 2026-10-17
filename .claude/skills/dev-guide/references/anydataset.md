# AnyDataset: Unified Row/Iterator Abstraction

`byjg/anydataset` provides a single unified way to work with tabular data regardless of
source — in-memory arrays, XML files, JSON, CSV/fixed-width text, or NoSQL stores. Every
source produces the same `GenericIterator` → `Row` interface, so processing code is
identical whether the data comes from a database, an uploaded CSV, or a third-party API
response.

The core package is already installed. The format-specific extensions are optional — install
only what you need.

---

## Installation

```bash
# Core (already in composer.json)
composer require "byjg/anydataset"

# Optional extensions — add as needed:
composer require "byjg/anydataset-xml"      # XML files / strings
composer require "byjg/anydataset-json"     # JSON files / strings / API responses
composer require "byjg/anydataset-text"     # CSV, delimited, and fixed-width text
composer require "byjg/anydataset-nosql"    # MongoDB, DynamoDB, S3, Cloudflare KV
```

---

## Core concepts

```
AnyDataset / XmlDataset / JsonDataset / TextFileDataset
    │
    └── getIterator()  →  GenericIterator (implements PHP Iterator)
                                │
                                └── current()  →  Row / RowArray / RowObject
                                                       │
                                                       ├── get('field')
                                                       ├── set('field', value)
                                                       └── toArray()
```

Every iterator is a standard PHP `foreach`-able sequence of `Row` objects.
`GenericIterator` also provides terminal methods: `toArray()`, `toEntities()`,
`first()`, `firstOrFail()`, `exists()`.

---

## In-memory AnyDataset (core)

### Create and populate

```php
use ByJG\AnyDataset\Core\AnyDataset;

// From an array of associative arrays
$ds = new AnyDataset([
    ['id' => 1, 'name' => 'Alice', 'role' => 'admin'],
    ['id' => 2, 'name' => 'Bob',   'role' => 'user'],
]);

// Start empty, add rows
$ds = new AnyDataset();
$ds->appendRow(['id' => 3, 'name' => 'Carol', 'role' => 'user']);
```

### Iterate

```php
foreach ($ds->getIterator() as $row) {
    echo $row->get('name');   // Alice, Bob, Carol
}

// Terminal helpers
$all   = $ds->getIterator()->toArray();    // [['id'=>1,'name'=>'Alice',...], ...]
$first = $ds->getIterator()->first();      // underlying entity|null (array here — see toEntities)
```

### Filter with IteratorFilter

```php
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Core\Enum\Relation;

$filter = new IteratorFilter();
$filter->and('role', Relation::Equal, 'admin');

foreach ($ds->getIterator($filter) as $row) {
    echo $row->get('name');   // Alice
}
```

**Available relations:**
`EQUAL`, `NotEqual`, `LessThan`, `GreaterThan`, `LessThanOrEqualTo`,
`GreaterThanOrEqualTo`, `StartsWith`, `CONTAINS`, `IN`, `NotIn`,
`IsNull`, `IsNotNull`

### Grouped conditions (AND/OR)

```php
$filter = new IteratorFilter();
$filter->startGroup('id', Relation::Equal, 1)
       ->or('id', Relation::Equal, 2)
       ->endGroup()
       ->and('role', Relation::Equal, 'user');
```

### Row methods

```php
$row->get('field');                      // read value
$row->set('field', $value);             // write value
$row->set('tags', 'php', append: true); // append to a multi-value field
$row->unset('field');                   // remove field
$row->toArray();                        // all fields as associative array
```

### `toEntities()` — what it really returns

`toEntities()` does NOT hydrate: it returns each row's *underlying entity* — whatever
the row was created from. For an array-backed dataset (like `AnyDataset` above) that is
just the associative array, same as `toArray()`. It pays off with object-backed
iterators: micro-orm's `Repository` builds rows around your model instances, so there
`->toEntities()` returns `Product[]`.

Rows are **object-backed** when you append objects — `appendRow()` accepts
`array|object`. Then the two terminals are symmetric: `toArray()` serializes each
entity to an associative array, `toEntities()` returns the original instances.

```php
$ds = new AnyDataset();
$ds->appendRow(new Product(1, 'Widget'));
$ds->appendRow(new Product(2, 'Gadget'));

$ds->getIterator()->toArray();     // [['id'=>1,'name'=>'Widget'], ...]  (serialized)
$ds->getIterator()->toEntities();  // [Product, Product]  (the same instances)
$ds->getIterator()->first();       // Product(1, 'Widget')

// Array-backed rows have no entity class — entities ARE the arrays:
$rows = (new AnyDataset([['id' => 1]]))->getIterator()->toEntities();  // [['id'=>1]]

// Hydrating with toEntities(): objects pass through as-is; only
// array entities need an ObjectCopy into the model:
$products = [];
foreach ($ds->getIterator()->toEntities() as $entity) {
    if (is_array($entity)) {
        $product = new Product();
        ObjectCopy::copy($entity, $product);
        $entity = $product;
    }
    $products[] = $entity;   // Product either way
}
```

---

## Closure-based field definitions (all adapters)

Every adapter that accepts a field map — XML, JSON, Text, and even in-memory transformations —
supports closures as field values. The closure receives the current `Row` object and returns
the computed value. This is the idiomatic way to derive fields, normalise data, or encode
conditional logic **inside the definition**, keeping the iteration loop clean and if-free.

```php
// General pattern: any field value can be a callable
$fieldMap = [
    'plain_field' => 'source_field_or_xpath',        // string
    'computed'    => function (RowInterface $row): mixed {
        // full access to already-mapped fields on this row
        return someTransformation($row->get('plain_field'));
    },
];
```

### What you can do in a closure

```php
$fieldMap = [
    // Normalise a value
    'status' => fn($row) => strtolower(trim($row->get('STATUS'))),

    // Conditional / default
    'label'  => fn($row) => $row->get('name') ?: $row->get('code'),

    // Derived from multiple fields
    'full_address' => fn($row) =>
        implode(', ', array_filter([
            $row->get('street'),
            $row->get('city'),
            $row->get('country'),
        ])),

    // Type cast
    'price'  => fn($row) => (float) $row->get('raw_price'),
    'active' => fn($row) => $row->get('flag') === 'Y',

    // Nested lookup / external data
    'category_name' => fn($row) => $categoryMap[$row->get('category_id')] ?? 'Unknown',
];
```

The closure is evaluated **after** all plain fields on the row are already populated, so
you can safely read sibling fields within the same definition.

### Example: clean import without any if in the loop

Without closures you'd scatter conditionals across the iteration. With closures, the mapping
declares all transformations once and the loop body stays trivial:

```php
$ds = new JsonDataset($apiResponse);
$iterator = $ds->getIterator("/products")
    ->withFields([
        JsonFieldDefinition::create("id",     "productId")->ofTypeInt(),
        JsonFieldDefinition::create("name",   "title"),
        JsonFieldDefinition::create("price",  "pricing/amount")->ofTypeFloat(),
        JsonFieldDefinition::create("status", "availability")
            ->withDefault("unknown"),
        // Derived — no if needed in the loop
        JsonFieldDefinition::create("label",  "title")
            ->withProcessor(fn($val, $row) =>
                strtoupper($val) . ' (' . $row->get('status') . ')'
            ),
    ]);

foreach ($iterator as $row) {
    // completely clean — no conditionals
    $model = new Product();
    ObjectCopy::copy($row->toArray(), $model);
    $repository->save($model);
}
```

---

## XML (byjg/anydataset-xml)

Install: `composer require "byjg/anydataset-xml"`

Map XPath selectors to row field names. Each matching `$rowNode` element becomes one row.

```php
use ByJG\AnyDataset\Xml\XmlDataset;

$xml = file_get_contents('catalog.xml');

$ds = new XmlDataset(
    $xml,
    "book",                          // XPath: repeating row element
    [
        "category" => "@category",   // attribute
        "title"    => "title",       // child element text
        "lang"     => "title/@lang", // attribute of child
        "price"    => "price",
        // closure — see "Closure-based field definitions" section above
        "discount" => fn($row) => round((float)$row->get('price') * 0.1, 2),
    ]
);

foreach ($ds->getIterator() as $row) {
    echo $row->get('title');     // Everyday Italian
    echo $row->get('category');  // COOKING
    echo $row->get('discount');  // 3.0
}
```

---

## JSON (byjg/anydataset-json)

Install: `composer require "byjg/anydataset-json"`

Navigate nested JSON with a `/`-separated path. Use `*` to expand arrays.

```php
use ByJG\AnyDataset\Json\JsonDataset;
use ByJG\AnyDataset\Json\JsonFieldDefinition;

$json = '{"data":{"users":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]}}';

$ds = new JsonDataset($json);

// Simple: iterate the array at a path
foreach ($ds->getIterator("/data/users") as $row) {
    echo $row->get('id');    // 1, 2
    echo $row->get('name');  // Alice, Bob
}

// With field definitions — rename fields, validate types, set required
$iterator = $ds->getIterator("/data/users")
    ->withFields([
        JsonFieldDefinition::create("userId", "id")->required()->ofTypeInt(),
        JsonFieldDefinition::create("displayName", "name")->ofTypeString(),
    ]);

foreach ($iterator as $row) {
    echo $row->get('userId');       // 1, 2
    echo $row->get('displayName');  // Alice, Bob
}
```

**`JsonFieldDefinition` options:** `required()`, `ofTypeString()`, `ofTypeInt()`,
`ofTypeFloat()`, `ofTypeBool()`, default value.

Pass an already-decoded PHP array instead of a JSON string — both are accepted.

---

## Text files (byjg/anydataset-text)

Install: `composer require "byjg/anydataset-text"`

### Delimited (CSV-style)

```php
use ByJG\AnyDataset\Text\TextFileDataset;

// From a file — first line is treated as header (field names)
$ds = TextFileDataset::getInstance('/path/to/data.csv')
    ->withFieldParser(TextFileDataset::CSVFILE_COMMA);

foreach ($ds->getIterator() as $row) {
    echo $row->get('email');
}

// No header — specify field names explicitly
$ds = TextFileDataset::getInstance('/path/to/data.csv')
    ->withFields(['firstname', 'lastname', 'email'])
    ->withFieldParser(TextFileDataset::CSVFILE_COMMA);
```

**Built-in parsers:**
- `TextFileDataset::CSVFILE` — `|`, `;`, or `,`
- `TextFileDataset::CSVFILE_COMMA` — `,` only
- `TextFileDataset::CSVFILE_SEMICOLON` — `;` only

Also accepts an HTTP/HTTPS URL as the source.

### Fixed-width text

```php
use ByJG\AnyDataset\Text\FixedTextFileDataset;
use ByJG\AnyDataset\Text\Definition\FixedTextDefinition;
use ByJG\AnyDataset\Text\Definition\TextTypeEnum;

// "001ALICE  S1500"
// pos 0-2: id (3 chars), pos 3-9: name (7), pos 10: enabled (1), pos 11-14: code (4)
$ds = (new FixedTextFileDataset($content))
    ->withFieldDefinition([
        new FixedTextDefinition('id',      0,  3, TextTypeEnum::Number),
        new FixedTextDefinition('name',    3,  7, TextTypeEnum::String),
        new FixedTextDefinition('enabled', 10, 1, TextTypeEnum::String, ['S', 'N']),
        new FixedTextDefinition('code',    11, 4, TextTypeEnum::Number),
    ]);

foreach ($ds->getIterator() as $row) {
    echo $row->get('id');    // 1
    echo $row->get('name');  // ALICE
}
```

---

## NoSQL (byjg/anydataset-nosql)

Install: `composer require "byjg/anydataset-nosql"`

Supports MongoDB (document), DynamoDB, S3, and Cloudflare KV (key-value).

```php
use ByJG\AnyDataset\NoSql\Factory;

// MongoDB
$driver = Factory::getNoSqlInstance('mongodb://user:pass@host:27017/mydb');

// AWS DynamoDB
$driver = Factory::getNoSqlInstance('dynamodb://ACCESS_KEY:SECRET@us-east-1/tablename');

// AWS S3
$driver = Factory::getNoSqlInstance('s3://ACCESS_KEY:SECRET@us-east-1/bucketname');
```

---

## Using AnyDataset as a DB mock in tests

`AnyDataset` is the simplest way to inject fake data into code that normally reads from a
database. Create the data, wrap it in a fake repository, and pass it in.

```php
use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Core\Enum\Relation;

// Fake "database"
$fakeData = new AnyDataset([
    ['id' => 1, 'name' => 'Widget', 'price' => 9.99],
    ['id' => 2, 'name' => 'Gadget', 'price' => 24.99],
]);

// Query it like a real dataset
$filter = new IteratorFilter();
$filter->and('price', Relation::GreaterThan, 10.0);
$expensive = $fakeData->getIterator($filter)->toArray();
// [['id'=>2,'name'=>'Gadget','price'=>24.99]]

// Hydrate rows into models
$products = [];
foreach ($fakeData->getIterator()->toArray() as $data) {
    $product = new Product();
    ObjectCopy::copy($data, $product);
    $products[] = $product;
}
```

---

## Common patterns

### Read a CSV upload and import to DB

```php
$ds = TextFileDataset::getInstance($uploadedFilePath)
    ->withFieldParser(TextFileDataset::CSVFILE_COMMA);

foreach ($ds->getIterator() as $row) {
    $model = new Product();
    ObjectCopy::copy($row->toArray(), $model);
    $productRepository->save($model);
}
```

### Parse an API JSON response and save to DB

```php
$response = $httpClient->sendRequest($request);
$json     = $response->getBody()->getContents();

$ds = new JsonDataset($json);
foreach ($ds->getIterator("/items") as $row) {
    $model = new Product();
    ObjectCopy::copy($row->toArray(), $model);
    $productRepository->save($model);
}
```

### Extract data from an XML feed

```php
$ds = new XmlDataset($xmlString, "product", [
    "sku"   => "@sku",
    "name"  => "description",
    "price" => "price",
]);

foreach ($ds->getIterator() as $row) {
    $model = new Product();
    ObjectCopy::copy($row->toArray(), $model);
    $productRepository->save($model);
}
```

---

## Quick reference

| Goal | Code |
|---|---|
| In-memory dataset | `new AnyDataset([...rows...])` |
| Add a row | `$ds->appendRow(['key' => 'val'])` |
| Iterate all rows | `foreach ($ds->getIterator() as $row)` |
| Filter rows | `$ds->getIterator(new IteratorFilter()->and(...))` |
| First match | `->first()` / `->firstOrFail()` — returns the underlying entity |
| Rows as arrays | `->toArray()` |
| Underlying entities | `->toEntities()` — source objects; plain arrays for array-backed rows |
| Read XML | `new XmlDataset($xml, 'rowTag', ['field' => 'xpath'])` |
| Read JSON | `new JsonDataset($json)->getIterator('/path/to/array')` |
| Read CSV | `TextFileDataset::getInstance($path)->withFieldParser(CSVFILE_COMMA)` |
| Read fixed-width | `(new FixedTextFileDataset($text))->withFieldDefinition([...])` |