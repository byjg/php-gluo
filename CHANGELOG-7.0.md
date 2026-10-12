# Changelog - Version 7.0

> **Status: in development.** This document tracks changes landing on the `7.0` branch.
> Nothing here is released yet, and the contents may still change.

## Breaking Changes

- None.

## Changes

- **`?orderBy=` works on the list endpoints, and `?filter=` is gone.** The example
  controllers (`Project`, `Task`, `Note`) documented both parameters and applied neither.
  `orderBy` is now applied through `ByJG\Gluo\Util\OrderBy::fromQueryString()`, which only
  accepts fields of the model (`?orderBy=name desc,id`; anything else is a 400). `filter`
  was removed from the OpenAPI attributes because it never had a defined format. See
  [Filtering and Ordering](docs/guides/repository-advanced.md#filtering-and-ordering).

- **The frontend is published over HTTPS too.** `docker-compose.yml` now maps
  `7443:8443` on the `frontend` service, so the SPA answers on https://localhost:7443
  with the self-signed certificate `byjg/static-httpserver` generates at start. HTTP on
  `:7080` is unchanged. See [Frontend](docs/guides/frontend.md#https).

- **`./gluo` is the command-line entry point.** It replaces the Composer scripts for the
  framework tasks and runs your own commands by a short name:

  | New | Was |
  |---|---|
  | `./gluo --env=dev migrate:update` | `composer migrate -- --env=dev update` |
  | `./gluo --env=dev codegen:all --table=product --save` | `composer codegen -- --env=dev --table=product all --save` |
  | `./gluo openapi` | `composer openapi` |
  | `./gluo terminal` | `composer terminal` |
  | `./gluo user:cleanup 30` | `scriptify run "\\MyApp\\Service\\MaintenanceService::cleanOldRecords" --arg 30` |

  The Composer scripts remain as aliases that call `./gluo`, so existing pipelines keep
  working. Project commands are declared in the new `config/commands.php`, and
  `config/scriptify-bootstrap.php` hands the Gluo container to Scriptify so a command's
  class gets its constructor dependencies. `./gluo list --check` validates every command
  without running it. See [The gluo Command](docs/reference/gluo-cli.md).

  `Builder\Scripts` lost its static Composer entry points (`migrate`, `codeGenerator`,
  `genOpenApiDocs`, `checkOpenApi`); the `run*` methods the launcher calls are unchanged.

- **The config cache is set in `config/ConfigBootstrap.php`.** `byjg/gluo-core` no longer
  caches the resolved container for staging and prod; the template now does it in
  `configureDefinition()`, with the same `FileSystemCacheEngine` as before, so the behaviour
  doesn't change. Change it there if your deploy needs another cache (see
  [Environment-Specific Caching](docs/guides/configuration.md#5-environment-specific-caching)).
- **`composer create-project` no longer copies `docs/` into the new project.** The copy
  was frozen at creation time while `byjg/gluo-core` keeps updating through
  `composer update`, so it drifted out of date. The project now gets a short
  `README.md` with a quick start, the common commands and links to the online
  documentation at https://opensource.byjg.com/docs/php/gluo/. The `.claude/` skills
  are still copied.
- **The test namespace is `Tests\`, not `Test\`.** `composer.json` maps `Tests\` to `tests/`,
  the classes under `tests/` and the documentation follow, and `byjg/gluo-core` generates new
  tests in `Tests\Controller`. A project created from an earlier template keeps working as it
  is; rename its namespace when you want the generated tests to match.

## Requirements

- PHP 8.3, 8.4, 8.5 and 8.6 are now supported: `"php": ">=8.3 <8.7"`.
  The previous `<8.6` upper bound excluded PHP 8.6, since `<8.6` is exclusive.

### ByJG dependencies

- `byjg/gluo-core` is now `^7.0`.
- `byjg/scriptify` is now `^7.0`.

While 7.0 is unreleased these resolve to `7.0.x-dev` from each component's
`7.0` branch, via `minimum-stability: dev` with `prefer-stable: true`.

## Toolchain

- PHPUnit updated to `^12.5`.
- Psalm is installed as `psalm/phar` instead of `vimeo/psalm`.

  `vimeo/psalm` lists the PHP versions it supports and no published release includes
  8.6, so as a dev dependency it made `composer install` fail on the 8.6 build job
  before any test ran. `psalm/phar` requires only `php ^8.2` and bundles its own
  dependencies, so it installs on every PHP version in the matrix and cannot conflict
  with the project's. Psalm itself still refuses to *run* on 8.6, which is why the
  Psalm job uses 8.5. `composer psalm` runs it.

- PHPUnit 13 is deliberately **not** used. It requires PHP `>=8.4.1`, breaking the
  8.3 floor.

## Continuous Integration

- The build matrix now includes PHP 8.6.
- The Psalm job runs on PHP 8.5.

## Housekeeping

- `phpunit.xml.dist` renamed to `phpunit.xml`.
