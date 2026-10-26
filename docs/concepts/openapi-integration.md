---
sidebar_position: 320
title: OpenAPI Integration
---

# OpenAPI Integration

This document explains what OpenAPI is, how the reference architecture uses it, and how to keep the spec in sync with your code.

## What Is OpenAPI?

[OpenAPI](https://www.openapis.org/) (formerly Swagger) is a language-agnostic specification for describing HTTP APIs. A single `openapi.json` file documents every endpoint, its parameters, request bodies, and response schemas. This single source of truth drives:

- **Runtime routing** – `OpenApiRouteList` reads `openapi.json` to map incoming requests to controller methods.
- **Validation** – `#[ValidateRequest]` checks each request against it, and `SchemaValidator` checks each model against it before it is saved (see [The schema validates every save](#the-schema-validates-every-save)).
- **API reference** – An interactive browser UI ([Scalar](https://github.com/scalar/scalar)) is served at `http://localhost:8080/docs/` so you can explore and test endpoints without a separate tool.
- **Contract testing** – `OpenApiValidation` (via `ByJG\ApiTools`) asserts that every test response conforms to the declared schema.

## How swagger-php Scans Your Code

The project uses [`zircote/swagger-php`](https://zircote.github.io/swagger-php/guide/) to scan PHP attribute annotations and produce `public/docs/openapi.json`. It scans your `src/` directory plus the gluo-core `Trait/` directory (so the `OaCreatedAt`/`OaUpdatedAt`/`OaDeletedAt` properties appear in your schemas).

Run the generator whenever you add or change annotations:

```bash
APP_ENV=dev php gluo openapi
```

`php gluo openapi` invokes `swagger-php` with those scan paths (see `getOpenApiScanPaths()` in `ByJG\Gluo\Builder\BaseScripts`). The output is written to `public/docs/openapi.json`.

## The Generated File

`public/docs/openapi.json` is **generated** and should be committed to version control so that:

- The application can boot without running the generator.
- Tests can validate responses even in CI environments.
- The API reference always has an up-to-date spec to render.

### Spec version

The spec is written as **OpenAPI 3.1**. The attributes do not change: swagger-php turns
`nullable: true` into the 3.1 form, a type union such as `"type": ["string", "null"]`. The
router, `#[ValidateRequest]` and the contract tests all read 3.1. To keep writing 3.0,
override `getOpenApiVersion()` in `builder/Scripts.php` and return `'3.0.0'`.

## The schema validates every save

`#[ValidateRequest]` checks what comes in through an endpoint. A model can also be saved
without a request: by a command line script, a queue consumer, a service called by another
service. To hold those saves to the same rules, the model declares a validator in its table
attribute (codegen writes it):

```php title="src/Model/Project.php"
#[OA\Schema(required: ["id", "name"], type: "object", xml: new OA\Xml(name: "Project"))]
#[TableAttribute("project", validator: SchemaValidator::class)]
class Project
{
    #[OA\Property(type: "integer", format: "int32", readOnly: true)]
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    protected int|null $id = null;

    #[OA\Property(type: "string", format: "string", maxLength: 100, minLength: 1)]
    #[FieldAttribute(fieldName: "name")]
    protected string|null $name = null;
    // ...
}
```

The code that saves does not change: `save()` calls the validator first. It serializes the
model as the API returns it and matches it against `#/components/schemas/Project`, the schema
its own `OA\Property` attributes produce. A valid model is saved; an invalid one throws the
same 400 as `#[ValidateRequest]`, and nothing is written:

```php
// Repository pattern
$projectService->create(['name' => '']);       // Error400Exception: ... is shorter than minLength 1

// ActiveRecord pattern
$note = Note::get($id);
$note->setBody('');
$note->save();                                  // Error400Exception; the stored body is unchanged
```

- A property marked `readOnly` is not required: the generated key does not exist yet when a
  record is inserted, and computed fields are never written. codegen marks the key `readOnly`
  when the server generates it (auto-increment, or a UUID seeded by `TableUuidPKAttribute`);
  a key the client supplies stays writable and required.
- A property that is null is treated as not set.
- Only `save()` is validated. `UpdateQuery`, `InsertBulkQuery` and raw SQL through the
  `DatabaseExecutor` write to the table directly and are not checked.
- The check uses the generated `public/docs/openapi.json`, like the router: regenerate it
  after changing a model's attributes.

## API Reference

When the dev server is running, open `http://localhost:8080/docs/` to browse the interactive API documentation. Every endpoint, parameter, and schema defined in `openapi.json` is rendered on a single page with a sidebar grouped by tag, a search (`Ctrl/⌘ K`) over operations and schemas, and a built-in request client. Each operation also has a PHP code sample using [byjg/webrequest](https://opensource.byjg.com/docs/php/webrequest/), generated in the browser by `public/docs/index.html`. To call a protected endpoint, log in with `POST /login` and paste the token into the `jwt-token` authentication field (Scalar adds the `Bearer ` prefix).

## Contract Testing Overview

`BaseApiTestCase` mixes in `OpenApiValidation`, which intercepts every `sendRequest()` call and validates the HTTP response against the schema declared in `openapi.json`. This means:

- A missing required field in the response body causes the test to fail.
- An undocumented status code causes the test to fail.
- Schema drift between code and spec is caught automatically in CI.

See [Testing](../guides/testing.md) for practical examples.

## Keeping the Spec in Sync

:::tip Keep docs in sync
Whenever you change PHP attribute annotations in `src/`, rerun `APP_ENV=dev php gluo openapi`. The regenerated `public/docs/openapi.json` is what `OpenApiRouteList` reads at runtime and what the tests validate against.
:::

A common workflow:

1. Add or update `#[OA\Get]`, `#[OA\Post]`, etc. attributes in a controller.
2. Run `APP_ENV=dev php gluo openapi` to regenerate the spec.
3. Run `php gluo test` to verify the new endpoint passes contract tests.
4. Commit both the updated controller and the regenerated `openapi.json`.

## Related Documentation

- [REST Controllers](../guides/rest-controllers.md) - Defining routes with PHP attributes
- [Testing](../guides/testing.md) - Contract testing with OpenApiValidation
- [Request Lifecycle](request-lifecycle.md) - How the spec drives runtime routing
- [Frontend (Vite)](../guides/frontend.md) - The optional `frontend/` SPA that consumes these documented endpoints
