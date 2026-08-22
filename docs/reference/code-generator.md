---
sidebar_position: 400
title: Code Generator
---

# Code Generator

The code generator creates PHP classes based on your database tables, dramatically speeding up development.

## What It Generates

The code generator supports two architectural patterns:

### Repository Pattern (Default)
- **Model** - PHP class with properties matching table columns
- **Repository** - Data access layer with ORM integration
- **Service** - Business logic layer extending BaseService
- **REST API** - Complete CRUD endpoints (GET, POST, PUT)
- **Functional Tests** - Test suite for the CRUD API
- **Config** - Automatic DI bindings (added automatically with `--save`)

### ActiveRecord Pattern (with `--activerecord`)
- **Model** - PHP class with properties and ActiveRecord trait for direct database operations
- **REST API** - Complete CRUD endpoints (GET, POST, PUT)
- **Functional Tests** - Test suite for the CRUD API

## Usage

```bash
APP_ENV=<environment> composer codegen -- --table=<table_name> <arguments> [options]
composer codegen -- --env=<environment> --table=<table_name> <arguments> [options]
```

### Required

- `--table=<name>` - Database table name

### Environment

You can specify the environment in two ways:
- Set the `APP_ENV` environment variable
- Use the `--env=<environment>` parameter (overrides `APP_ENV`)

**Note:** At least one method must be used to specify the environment (dev, test, prod).

### Arguments (at least one required)

| Argument               | Description              | Repository Pattern | ActiveRecord Pattern        |
|------------------------|--------------------------|--------------------|-----------------------------|
| `all`                  | Generate all components  | ✓ All components   | ✓ Model, Controller, Test         |
| `model`                | Generate Model           | ✓                  | ✓ (with ActiveRecord trait) |
| `repo` or `repository` | Generate Repository      | ✓                  | ✗ Not applicable            |
| `service`              | Generate Service         | ✓                  | ✗ Not applicable            |
| `controller`           | Generate REST controller | ✓                  | ✓                           |
| `test`                 | Generate Test            | ✓                  | ✓                           |

### Options

- `--activerecord` - Use ActiveRecord pattern instead of a Repository pattern
- `--save` - Save generated files to disk (otherwise prints to console)
- `--debug` - Show debug information

## Examples

The commands below run from the repository root, which is also the PHP application root. Generated files land under `src/` (model, repository, service, controller), `tests/` (the functional test) and `config/` (the DI bindings, with `--save`).

### Repository Pattern (Default)

Generate all components for the `project` table using the Repository pattern:

```bash
# Using APP_ENV
APP_ENV=dev composer codegen -- --table=project all --save

# Using --env parameter
composer codegen -- --env=dev --table=project all --save
```

This creates:
- `src/Model/Project.php`
- `src/Repository/ProjectRepository.php`
- `src/Service/ProjectService.php`
- `src/Controller/ProjectController.php`
- `tests/Controller/ProjectTest.php`
- Automatically adds DI bindings to `config/dev/04-repositories.php` and `config/dev/05-services.php`

Generate only specific components:

```bash
APP_ENV=dev composer codegen -- --table=project model controller --save
```

### ActiveRecord Pattern

Generate all components for the `note` table using the ActiveRecord pattern:

```bash
# Using APP_ENV
APP_ENV=test composer codegen -- --table=note all --activerecord --save

# Using --env parameter
composer codegen -- --env=test --table=note all --activerecord --save
```

This creates:
- `src/Model/Note.php` (with ActiveRecord trait)
- `src/Controller/NoteController.php`
- `tests/Controller/NoteTest.php`

Generate only the model:

```bash
APP_ENV=test composer codegen -- --table=note model --activerecord --save
```

### Preview Without Saving

Preview the generated REST controller without saving to disk:

```bash
APP_ENV=dev composer codegen -- --table=project controller
composer codegen -- --env=dev --table=note all --activerecord
```

## Automatic Configuration

:::tip Automatic DI Bindings (Repository Pattern Only)
When using `--save` with the **Repository pattern**, repository and service bindings are automatically added to the configuration files:
- Repositories → `config/dev/04-repositories.php`
- Services → `config/dev/05-services.php`

No manual configuration needed!

**Note:** ActiveRecord pattern does not require DI bindings since models use the ActiveRecord trait for direct database access.
:::

Example output:
```
Processing Repository for table project...
File saved in src/Repository/ProjectRepository.php
Added use statement for ProjectRepository to 04-repositories.php
Added DI binding for ProjectRepository to 04-repositories.php
```

## Important Notes

:::warning Overwriting Files
Using `--save` will **overwrite existing files** without warning. Be careful when regenerating files you've customized.
:::

:::info After Generation
After generating REST controllers, remember to:
1. Run `composer run openapi` to update the OpenAPI specification
2. Run `composer run test` to verify the generated tests pass
:::

## Customizing Templates

You can modify existing templates or create your own. The default templates ship inside **byjg/gluo-core** (`vendor/byjg/gluo-core/templates/codegen/`) and use the [Jinja template engine for PHP](https://github.com/byjg/jinja_php). To customize, copy them into your project — a local `templates/codegen/` directory takes precedence over the package templates:

```bash
mkdir -p templates/codegen
cp -r vendor/byjg/gluo-core/templates/codegen/* templates/codegen/
```

**Available templates:**

Repository Pattern:
- `model.php.jinja` - Model class template
- `repository.php.jinja` - Repository class template
- `service.php.jinja` - Service class template
- `controller.php.jinja` - REST controller template
- `test.php.jinja` - Test class template

ActiveRecord Pattern:
- `model.php.jinja` - Same model template; the `activerecord` variable switches it to the ActiveRecord trait
- `controlleractiverecord.php.jinja` - REST controller for ActiveRecord template
- Uses the same `test.php.jinja` template as a Repository pattern

**Template variables available** (see [Template Customization](../guides/templates.md) for the full field-level reference):
- `className` - PascalCase class name (e.g., `UserProfile`)
- `tableName` - Original table name (e.g., `user_profile`)
- `varTableName` - camelCase variable name (e.g., `userProfile`)
- `restPath` / `restTag` - REST route path (e.g., `user/profile`) and OpenAPI tag
- `namespace` - Project namespace
- `fields` - Array of table columns (keys: `field`, `property`, `type`, `php_type`, `openapi_type`, `openapi_format`, `null`, `key`, `default`, `extra`)
- `primaryKeys` - Array of primary key column names
- `nullableFields` / `nonNullableFields` - camelCase property lists
- `indexes` - Table indexes (with `camelColumnName`)
- `autoIncrement` - `"yes"` when the PK auto-increments
- `activerecord` - True in ActiveRecord mode
- `hasCreatedAt` / `hasUpdatedAt` / `hasDeletedAt` - Timestamp-trait switches
