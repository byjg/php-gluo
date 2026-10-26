# OpenAPI Attribute Edge Cases and Patterns

This covers patterns beyond basic `GET /resource` with a JSON body — drawn from real
production usage. All examples use `zircote/swagger-php` attributes (OpenAPI 3.x).

---

## Enum values

### Enum in a query parameter

```php
#[OA\Parameter(
    name: "status",
    description: "Filter by status",
    in: "query",
    required: false,
    schema: new OA\Schema(
        type: "string",
        enum: ["created", "pending", "approved", "rejected", "canceled"],
        nullable: true
    )
)]
```

### Enum in a path parameter

```php
#[OA\Parameter(
    name: "game",
    in: "path",
    required: true,
    schema: new OA\Schema(
        type: "string",
        enum: ["roulette", "mines", "mystery_garden"]
    )
)]
```

### Enum in a request body property

```php
new OA\Property(
    property: "pixType",
    type: "string",
    enum: ["cpf", "email", "phone"]
)
```

### Enum using model constants (preferred — stays in sync with the model)

```php
new OA\Property(
    property: "type",
    type: "string",
    enum: [
        Product::PRODUCT_PHYSICAL,
        Product::PRODUCT_DIGITAL,
        Product::PRODUCT_CASH,
    ]
)
```

### Enum inside array items

```php
new OA\Property(
    property: "bets",
    type: "array",
    items: new OA\Items(
        required: ["match_id", "bet"],
        properties: [
            new OA\Property(property: "match_id", type: "integer"),
            new OA\Property(property: "bet", type: "string", enum: ["team1", "team2", "draw"]),
        ]
    )
)
```

---

## Property formats

| Format | Use for |
|---|---|
| `format: "int32"` | 32-bit integer |
| `format: "int64"` | 64-bit integer (IDs from legacy systems) |
| `format: "double"` | Monetary amounts, floating point |
| `format: "date"` | ISO date: `2024-01-31` |
| `format: "date-time"` | ISO datetime: `2024-01-31T10:00:00Z` |
| `format: "uuid"` | UUID path/query parameter |
| `format: "binary"` | File upload field in multipart schema |

```php
#[OA\Parameter(
    name: "user_id",
    in: "path",
    required: true,
    schema: new OA\Schema(type: "string", format: "uuid")
)]

#[OA\Parameter(
    name: "start_date",
    in: "query",
    required: false,
    schema: new OA\Schema(type: "string", format: "date")
)]

new OA\Property(property: "created_at", type: "string", format: "date-time")
new OA\Property(property: "balance", type: "number", format: "double")
new OA\Property(property: "account_id", type: "integer", format: "int64")
```

---

## Nullable fields

Mark optional fields `nullable: true` — the framework strips nulls from output by default,
but the OpenAPI contract should still document what's possible:

```php
new OA\Property(property: "description", type: "string", nullable: true)
new OA\Property(property: "amount", type: "number", format: "double", nullable: true)

// Nullable array
new OA\Property(
    property: "tags",
    type: "array",
    items: new OA\Items(type: "string"),
    nullable: true
)

// Nullable query parameter
#[OA\Parameter(
    name: "filter",
    in: "query",
    required: false,
    schema: new OA\Schema(type: "string", nullable: true)
)]
```

---

## Response schemas

### Single model reference (preferred)

```php
#[OA\Response(
    response: 200,
    description: "The product",
    content: new OA\JsonContent(ref: "#/components/schemas/Product")
)]
```

### Array of model references

```php
#[OA\Response(
    response: 200,
    description: "Product list",
    content: new OA\JsonContent(
        type: "array",
        items: new OA\Items(ref: "#/components/schemas/Product")
    )
)]
```

### No body (PUT/DELETE that returns nothing)

```php
#[OA\Response(response: 200, description: "Updated")]
#[OA\Response(response: 204, description: "Deleted")]
```

### Inline schema (use when the response doesn't match any stored model)

```php
#[OA\Response(
    response: 200,
    description: "Token response",
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: "token", type: "string"),
            new OA\Property(property: "expires_at", type: "string", format: "date-time"),
        ]
    )
)]
```

### oneOf — response can be one of several shapes

Use when a single endpoint legitimately returns different structures:

```php
#[OA\Response(
    response: 200,
    description: "Single item or list",
    content: new OA\JsonContent(
        oneOf: [
            new OA\Schema(
                properties: [
                    new OA\Property(property: "id", type: "integer"),
                    new OA\Property(property: "name", type: "string"),
                ],
                type: "object"
            ),
            new OA\Schema(
                type: "array",
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: "id", type: "integer"),
                        new OA\Property(property: "name", type: "string"),
                    ],
                    type: "object"
                )
            ),
        ]
    )
)]
```

### Explicit OA\MediaType (use when you need to set the mediaType string directly)

```php
#[OA\Response(
    response: 200,
    description: "Statement",
    content: new OA\MediaType(
        mediaType: "application/json",
        schema: new OA\Schema(
            type: "array",
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "date", type: "string", format: "date"),
                    new OA\Property(property: "amount", type: "number", format: "double"),
                ]
            )
        )
    )
)]
```

---

## Multiple response codes

Always document the error responses that callers must handle:

```php
#[OA\Response(
    response: 200,
    description: "Success",
    content: new OA\JsonContent(ref: "#/components/schemas/Withdraw")
)]
#[OA\Response(
    response: 401,
    description: "Unauthorized",
    content: new OA\JsonContent(ref: "#/components/schemas/error")
)]
#[OA\Response(
    response: 404,
    description: "Not Found",
    content: new OA\JsonContent(ref: "#/components/schemas/error")
)]
#[OA\Response(
    response: 422,
    description: "Not Eligible",
    content: new OA\JsonContent(ref: "#/components/schemas/error")
)]
```

`#/components/schemas/error` is the project's standard error envelope — always reference it
for 4xx responses.

---

## Request body patterns

### Inline required + properties (no stored schema)

```php
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ["username", "password"],
        properties: [
            new OA\Property(property: "username", description: "The username", type: "string"),
            new OA\Property(property: "password", description: "The password (encrypted)", type: "string"),
        ]
    )
)]
```

### Nested object with its own `required` list

```php
#[OA\RequestBody(
    content: new OA\JsonContent(
        required: ["withdraw_id", "user_id"],
        properties: [
            new OA\Property(property: "withdraw_id", type: "string"),
            new OA\Property(property: "user_id", type: "string"),
            new OA\Property(
                property: "email_data",
                required: ["title", "subject"],
                properties: [
                    new OA\Property(property: "title", type: "string"),
                    new OA\Property(property: "subject", type: "string"),
                ],
                type: "object",
                nullable: true
            ),
        ]
    )
)]
```

### requestBody inline inside the verb attribute (alternative syntax)

Instead of a separate `#[OA\RequestBody]`, embed it in the verb:

```php
#[OA\Post(
    path: "/product",
    requestBody: new OA\RequestBody(
        description: "The product to create",
        required: true,
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(ref: "#/components/schemas/Product")
        )
    ),
    tags: ["Product"]
)]
```

Both syntaxes produce identical OpenAPI output. Prefer the standalone `#[OA\RequestBody]`
attribute for readability when the body schema is complex.

### Multipart / file upload

```php
#[OA\RequestBody(
    required: true,
    content: [
        new OA\MediaType(
            mediaType: "multipart/form-data",
            schema: new OA\Schema(
                required: ["file", "file_name"],
                properties: [
                    new OA\Property(property: "file",      type: "string", format: "binary"),
                    new OA\Property(property: "file_name", type: "string"),
                    new OA\Property(property: "size",      type: "string", nullable: true),
                ]
            )
        ),
    ]
)]
```

See `references/request-response.md` → "Multipart / file uploads" for how to read the file
in the controller via `$request->uploadedFiles()`.

---

## Nested structures

### Required fields inside array items

```php
new OA\Property(
    property: "products",
    type: "array",
    items: new OA\Items(
        required: ["title", "product_id", "stock"],
        properties: [
            new OA\Property(property: "title", type: "string"),
            new OA\Property(property: "product_id", type: "integer"),
            new OA\Property(property: "stock", type: "integer"),
            new OA\Property(property: "description", type: "string", nullable: true),
        ]
    )
)
```

### Object property containing an array of scalars

```php
new OA\Property(
    property: "extra_info",
    properties: [
        new OA\Property(property: "type", type: "string", nullable: true),
        new OA\Property(
            property: "ids",
            type: "array",
            items: new OA\Items(type: "string"),
            nullable: true
        ),
    ],
    type: "object"
)
```

### Dynamic / unknown keys (additionalProperties)

Use when the object has keys that aren't known at design time:

```php
new OA\Property(
    property: "metadata",
    type: "object",
    additionalProperties: new OA\AdditionalProperties()
)
```

This tells OpenAPI "this object can have any additional string-keyed properties".

---

## Route metadata

### summary (shown in UIs alongside the path)

```php
#[OA\Get(
    path: "/game/url",
    summary: "Generates the iframe URL for the game provider",
    security: [["jwt-token" => []]],
    tags: ["Games"]
)]
```

### Multiple tags

```php
#[OA\Get(
    path: "/minigames/{game}/history",
    tags: ["MiniGames", "History"],
)]
```

### Public endpoint (no security)

Omit the `security` key entirely — do not pass an empty array:

```php
#[OA\Get(
    path: "/ping",
    tags: ["Health"]
)]
```

---

## Property documentation helpers

### example value

```php
new OA\Property(
    property: "callback_url",
    type: "string",
    example: "https://example.com/callback?token=abc123"
)
```

### description on a property

```php
new OA\Property(
    property: "password",
    description: "Must be SHA-256 hashed before sending",
    type: "string"
)
```

### HTML in requestBody description (supported by Swagger UI)

```php
#[OA\RequestBody(
    description: "Pix withdrawal. <br><b>Accepted pixType values:</b>
        <ul>
            <li>cpf</li>
            <li>email</li>
            <li>phone</li>
        </ul>",
    content: new OA\JsonContent(ref: "#/components/schemas/WithdrawRequest")
)]
```

Use sparingly — plain text is easier to maintain. Useful only when a UL/table genuinely
helps API consumers.

---

## Custom attribute classes

When several endpoints share the same complex response shape, extract it into a reusable
attribute class:

```php
// src/Attribute/WithdrawResponseAttributes.php
namespace App\Attribute;

use OpenApi\Attributes as OA;

#[\Attribute]
class WithdrawResponseAttributes extends OA\JsonContent
{
    public function __construct()
    {
        parent::__construct(
            properties: [
                new OA\Property(property: "id", type: "string"),
                new OA\Property(property: "status", type: "string",
                    enum: ["pending", "approved", "rejected"]),
                new OA\Property(property: "amount", type: "number", format: "double"),
            ]
        );
    }
}
```

Usage:

```php
#[OA\Response(
    response: 200,
    description: "Withdraw status",
    content: new WithdrawResponseAttributes()
)]
```

This keeps the spec DRY when the same shape appears across multiple endpoints (e.g., GET
and PATCH on the same resource returning the same object).

---

## Quick reference

| Goal | Pattern |
|---|---|
| Enum in param/property | `schema: new OA\Schema(type: "string", enum: ["a","b"])` |
| Model constant in enum | `enum: [MyModel::CONST_A, MyModel::CONST_B]` |
| UUID path param | `schema: new OA\Schema(type: "string", format: "uuid")` |
| Date query param | `schema: new OA\Schema(type: "string", format: "date")` |
| Nullable property | `new OA\Property(..., nullable: true)` |
| Dynamic object keys | `additionalProperties: new OA\AdditionalProperties()` |
| Array of enum | `items: new OA\Items(type: "string", enum: ["x","y"])` |
| Required inside items | `new OA\Items(required: ["a","b"], properties: [...])` |
| oneOf response | `content: new OA\JsonContent(oneOf: [new OA\Schema(...), ...])` |
| No-body response | `#[OA\Response(response: 200, description: "Updated")]` |
| File upload field | `new OA\Property(property: "file", type: "string", format: "binary")` |
| requestBody inline | `#[OA\Post(requestBody: new OA\RequestBody(...), ...)]` |
| Summary in route | `#[OA\Get(path: "...", summary: "Short description", ...)]` |
| Multiple tags | `tags: ["Foo", "Bar"]` |
| Public (no auth) | omit `security` key entirely |
| Custom response class | Extend `OA\JsonContent`; use in `content: new MyAttr()` |