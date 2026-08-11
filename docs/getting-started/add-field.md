---
sidebar_position: 30
title: Add a New Field
---

# Add a New Field

Now we have the table `example_crud` created in the [previous tutorial](first-table.md),
let's modify it to add a new field `status`.

## Changing the table

We need to add the proper field in the `up` script and remove it in the `down` script.

`db/migrations/up/00003-add-field-status.sql`:

```sql
alter table example_crud
    add status varchar(10) null;
```

`db/migrations/down/00002-rollback-field-status.sql`:

```sql
alter table example_crud
    drop column status;
```

## Run the migration

```shell
APP_ENV=dev composer run migrate -- update
# OR
composer run migrate -- --env=dev update
```


## Adding the field status to the `Model`

Open the file: `src/Model/ExampleCrud.php` and add the field `status`:

```php
...
    /**
     * @var string|null
     */
    #[OA\Property(type: "string", format: "string", nullable: true)]
    protected ?string $status = null;

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string|null $status
     * @return ExampleCrud
     */
    public function setStatus(?string $status): ExampleCrud
    {
        $this->status = $status;
        return $this;
    }
...
```

## Updating the Repository

As we're just adding a new field and already updated the Model, we don't need to change the `Repository` class. The ORM will automatically handle the new field.

## Updating the Service

Similarly, no changes needed in the `Service` class. The `BaseService` methods automatically work with the updated Model.

## Updating the REST Controller

We just need to allow the rest to receive the new field. If we don't do it, the API will throw an error.

Open the file: `src/Controller/ExampleCrudController.php` and add the attribute `status` to method `postExampleCrud()`:

```php
#[OA\RequestBody(
        description: "The object ExampleCrud to be created",
        required: true,
        content: new OA\JsonContent(
            required: [ "name" ],
            properties: [
                
                new OA\Property(property: "name", type: "string", format: "string"),
                new OA\Property(property: "birthdate", type: "string", format: "date-time", nullable: true),
                new OA\Property(property: "code", type: "integer", format: "int32", nullable: true),
                new OA\Property(property: "status", type: "string", format: "string", nullable: true)    # <-- Add this line
            ]
        )
    )]
    public function postExampleCrud(HttpResponse $response, HttpRequest $request)
```

## Updating the Tests

We only need to update the `getSampleData()` method to include the new field.
Open the file: `tests/Controller/ExampleCrudTest.php`

```php
protected function getSampleData($array = false)
    {
        $sample = [
            'name' => 'name',
            'birthdate' => '2023-01-01 00:00:00',
            'code' => 1,
            'status' => 'status',                     # <-- Add this line
        ];
...
```

## Update the OpenAPI

```shell
composer run openapi
```

## Run the tests

If everything is ok, the tests should pass:

```shell
APP_ENV=test composer run test
```

## Next Steps

[Next: Creating a REST method](first-endpoint.md)
