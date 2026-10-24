# byjg/gluo

REST API built with [Gluo](https://opensource.byjg.com/docs/php/gluo/). The application
code lives in `src/` under the `RestReferenceArchitecture` namespace; the framework itself
is the `byjg/gluo-core` package in `vendor/` and is updated with `composer update`.

## Quick start

```bash
docker compose up -d
php gluo --env=dev migrate:reset
php gluo test
```

## Common commands

| Command                                                   | What it does                           |
|-----------------------------------------------------------|----------------------------------------|
| `php gluo test`                                           | Run the test suite                     |
| `php gluo --env=dev migrate:<command>`                 | Run database migrations                |
| `php gluo list --check` | List every command and validate it |
| `php gluo openapi`                                        | Regenerate `public/docs/openapi.json`  |
| `php gluo --env=dev codegen:all --table=<table> --save` | Scaffold model, repository, service, controller and test from a table |

## Documentation

The documentation is online, so it always matches the current version of the framework:

- [Getting started](https://opensource.byjg.com/docs/php/gluo/getting-started/installation)
- [Full documentation](https://opensource.byjg.com/docs/php/gluo/)
