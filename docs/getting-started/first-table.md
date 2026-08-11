---
sidebar_position: 20
title: Create Your First Table
---

# Add a New Table

After [creating the project](installation.md), you're ready to create your own tables.

## Create the Table

Create a new migration file in the `migrations` folder using the format `0000X-message.sql`, where `X` represents a sequential number that determines execution order.

1. Create an "up" migration file `db/migrations/up/00002-create-table-example.sql`:

```sql
create table example_crud
(
    id int auto_increment not null primary key,
    name varchar(50) not null,
    birthdate datetime null,
    code int null
);
```

2. Create a corresponding "down" migration file `db/migrations/down/00001-rollback-table-example.sql` for rollbacks:

```sql
drop table example_crud;
```

## Run the Migration

Apply your migrations with:

```shell
APP_ENV=dev composer run migrate -- update
# OR
composer run migrate -- --env=dev update
```

Expected output:
```text
> Builder\Scripts::migrate
> Environment: dev
> Database: mysql://root:****@mysql-container/localdev

Updating to version latest...
Migration completed successfully.
```

To rollback changes:

```shell
APP_ENV=dev composer run migrate -- update --version=1
# OR
composer run migrate -- --env=dev update --version=1
```

The result should be:

```text
> Builder\Scripts::migrate
> Environment: dev
> Database: mysql://root:****@mysql-container/localdev

Updating to version 1...
Migration completed successfully.
```

Remember to run the migrating update again to apply the changes.


## Generate CRUD Components with the Code Generator

Generate all necessary files for your new table:

```shell
# Ensure DB is updated first
APP_ENV=dev composer run migrate -- update
# OR: composer run migrate -- --env=dev update

# Generate files (options: rest, model, test, repo, config, or all)
APP_ENV=dev composer run codegen -- --table example_crud --save all
# OR: composer run codegen -- --env=dev --table example_crud --save all
```

This creates:
- `src/Model/ExampleCrud.php` - Model class
- `src/Repository/ExampleCrudRepository.php` - Repository class
- `src/Service/ExampleCrudService.php` - Service class
- `src/Controller/ExampleCrudController.php` - REST controller
- `tests/Controller/ExampleCrudTest.php` - Functional tests

:::tip Automatic Configuration
The repository and service are automatically registered in:
- `config/dev/04-repositories.php`
- `config/dev/05-services.php`

No manual configuration needed!
:::

:::tip ActiveRecord Pattern
To generate ActiveRecord pattern instead of Repository pattern:

```shell
APP_ENV=dev composer run codegen -- --table example_crud all --activerecord --save
# OR: composer run codegen -- --env=dev --table example_crud all --activerecord --save
```

This generates a simpler architecture with the model containing data access methods.
See [Code Generator Documentation](../reference/code-generator.md) for details.
:::

## Run the Tests

The automatically generated test is located at `tests/Controller/ExampleCrudTest.php`.

Run it:

```shell
composer run test
```

Initial tests **_will fail_** because we need to:

1. Generate OpenAPI documentation to create the endpoints:

```shell
composer run openapi
```

2. Fix the test data by updating `tests/Controller/ExampleCrudTest.php`:

 
Locate:

```php
    protected function getSampleData($array = false)
    {
        $sample = [

            'name' => 'name',
            'birthdate' => 'birthdate',
            'code' => 1,
        ];
...
```

And Change:
```php
'birthdate' => 'birthdate',
```

To:

```php
'birthdate' => '2023-01-01 00:00:00',
```

3. Run the tests again:
```shell
composer run test
```

Your tests should now pass successfully!

## Next Steps

Continue with [Adding a New Field](add-field.md) to enhance your implementation.
