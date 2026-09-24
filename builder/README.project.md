# byjg/gluo

REST API built with [Gluo](https://opensource.byjg.com/docs/php/gluo/). The application
code lives in `src/` under the `RestReferenceArchitecture` namespace; the framework itself
is the `byjg/gluo-core` package in `vendor/` and is updated with `composer update`.

## Quick start

```bash
docker compose up -d
composer migrate -- --env=dev reset
composer test
```

## Common commands

| Command                                                   | What it does                           |
|-----------------------------------------------------------|----------------------------------------|
| `composer test`                                           | Run the test suite                     |
| `composer migrate -- --env=dev <command>`                 | Run database migrations                |
| `composer openapi`                                        | Regenerate `public/docs/openapi.json`  |
| `composer codegen -- --env=dev --table=<table> all --save` | Scaffold model, repository, service, controller and test from a table |

## Documentation

The documentation is online, so it always matches the current version of the framework:

- [Getting started](https://opensource.byjg.com/docs/php/gluo/getting-started/installation)
- [Full documentation](https://opensource.byjg.com/docs/php/gluo/)
