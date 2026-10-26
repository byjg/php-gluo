# Changelog - Version 7.0

> **Status: in development.** This document tracks changes landing on the `7.0` branch.
> Nothing here is released yet, and the contents may still change.

## Breaking Changes

- `config/dev/03-api.php` builds the OpenAPI `Schema` with `Schema::fromFile()`. It used
  `Schema::getInstance()`, which byjg/swagger-test 7.0 removes (deprecated since 6.0): a project
  created from an earlier template fails every API test with
  `class ByJG\ApiTools\Base\Schema does not have a method "getInstance"` once it updates. Make
  the same change in the project:

  ```php
  Schema::class => DI::bind(Schema::class)
      ->withFactoryMethod('fromFile', [__DIR__ . '/../../public/docs/openapi.json'])
      ->toSingleton(),
  ```

  `fromFile()` exists since swagger-test 6.0, so the change can be made before updating.

- byjg/swagger-test 7.0 also removes `assertRequest()` (deprecated since 6.0), the former name of
  `sendRequest()`. The template's tests already use `sendRequest()`; a project's own test that calls
  `$this->assertRequest($request)` must call `$this->sendRequest($request)`.

- **`PUT` takes the key from the path:** `PUT /project/{id}`, `PUT /task/{id}` and `PUT /note/{id}`,
  like `GET` and `DELETE`. Before, it was `PUT /project` with the key in the body. The body is the
  same as the `POST`'s, the writable fields only; a key in the body is refused with 400. A client
  of the API, and a project's own controllers generated before this change, have to move. See
  gluo-core's changelog for the controller change, and use
  `$this->service->update($request->attribute('id'), ValidateRequest::getPayload())`:
  `BaseService::update()` now takes the key as its first argument.

## Changes

- **The database server is the `gluo-database` service, in `docker-compose.database.yml`.** It was
  `mysql-container` (`postgres-container` for PostgreSQL) in `docker-compose.yml`, with fixed
  `container_name`s (`resttest`, `mysql-container`) that made every project created from the
  template claim the same names: a second project could not start while another one's containers
  existed, even stopped. Now `docker-compose.yml` holds the API and the frontend and includes
  `docker-compose.database.yml`, which holds the server of the database chosen (none for SQLite).
  Compose names the containers after the project (`<project>-rest-1`). Use
  `docker compose exec rest ...` instead of `docker exec resttest ...`.

- **No `/etc/hosts` entry for the database.** `config/dev` and `config/test` connected to
  `mysql-container`, which the commands run on the host (`php gluo`, `php gluo test`) only reached
  with `127.0.0.1  mysql-container` in `/etc/hosts`. They now connect to `127.0.0.1`, the published
  port, and the `rest` service sets `DBDRIVER_CONNECTION` to `gluo-database` for the app in the
  container. The tests always use the test database: `tests/bootstrap.php` keeps the server of a
  `DBDRIVER_CONNECTION` set in the environment and replaces its database with the test one (it
  ignores a SQLite one: the test file of `config/test` applies), so they
  run on the host, in the `rest` container (`docker compose exec rest php gluo test`) or in a CI job
  that sets the variable, and never on the dev database.

- **`composer create-project` no longer creates a git repository.** The installer ran `git init`
  and made an "Initial commit", so a project created inside an existing repository (a monorepo,
  a demo with several apps) got a nested `.git` to remove by hand. Run `git init` yourself when
  the project is a repository of its own. The installer no longer asks for the git user name and
  email, and no longer needs git; `git_user_name` and `git_user_email` in `setup.json` are
  ignored.

- **`composer create-project` no longer leaves the starter's changelogs in the new
  project.** `CHANGELOG-*.md` are the history of this starter, not of the project being
  created, so the installer removes them, as it already did with `docs/`.
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

- **`php gluo` is the command-line entry point.** It replaces the Composer scripts for the
  framework tasks and runs your own commands by a short name:

  | New | Was |
  |---|---|
  | `php gluo --env=dev migrate:update` | `composer migrate -- --env=dev update` |
  | `php gluo --env=dev codegen:all --table=product --save` | `composer codegen -- --env=dev --table=product all --save` |
  | `php gluo openapi` | `composer openapi` |
  | `php gluo terminal` | `composer terminal` |
  | `php gluo test` | `composer test` |
  | `php gluo user:cleanup 30` | `scriptify run "\\MyApp\\Service\\MaintenanceService::cleanOldRecords" --arg 30` |

  The Composer scripts remain as aliases that call `php gluo`, so existing pipelines keep
  working. Project commands are declared in the new `src/Command/Commands.php`, a
  `ByJG\Scriptify\CommandProvider` that the `gluo` script passes to the launcher, with
  class references (`PaymentCommands::class`) instead of strings. They run with the Gluo
  container, so a command's class gets its constructor dependencies.
  `php gluo list --check` validates every command without running it, and
  `php gluo --env=prod install <command>` installs one as a system service.
  See [The gluo Command](docs/reference/gluo-cli.md).

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

- **The example lists page and sort.** The projects screen pages (`page`, `size`) and sorts
  by name through the API, with a new `Pagination` component in
  `frontend/src/components/ui.jsx`. It used to show only the first 50 projects.
- **`GET /project/{projectId}/task`** returns a project's tasks, filtered by the database
  (`TaskService::getByProjectId()`). The project screen used to load the first 50 tasks of
  every project and filter them in the browser, so tasks disappeared once there were more.
- **The examples are what codegen writes now:** `maxLength` and, for required text,
  `minLength: 1` on `project.name`, `task.title` and `note.body`, and a test per entity that
  sends invalid bodies to the server.
- **`public/docs/openapi.json` is OpenAPI 3.1.**
- **Every save is validated against the model's OpenAPI schema, not only requests.** `Project`,
  `Task` and `Note` declare `validator: SchemaValidator::class` in their table attribute, so a
  `save()` from a command, a queue consumer or a service is held to the same rules as
  `#[ValidateRequest]` and fails with the same 400. Their keys, and Note's computed `bodyLength`
  and `days`, are marked `readOnly`: the server generates them. See
  [OpenAPI Integration](docs/concepts/openapi-integration.md#the-schema-validates-every-save).
- **`Note` uses Gluo's `ActiveRecord` trait**, and its controller calls `Note::getOrFail($id)` instead
  of `Note::get($id)` followed by a null check, in `GET`, `PUT` and `DELETE`. A missing record is a 404
  named after the model in both patterns: `"Note not found"`, `"Project not found"` (it was
  `"Id not found"` in the Repository pattern). See [Traits](docs/reference/traits.md#activerecord-trait).

## Bug Fixes

- The Docker image copied the `gluo` script and `composer.json` "for the migration script", but not
  `db/`, so `php gluo migrate:update` in the image had no migrations to run. It now copies
  `db/` (a local SQLite database file in it stays out, through `.dockerignore`).

- A project created with `db_schema` `sqlite` or `postgres` did not work: only the connection
  string changed. Now:
  - the project gets the SQL and the server of its database. The template keeps them in
    `.db-dialects/<mysql|postgres|sqlite>/` (`db/` and `docker-compose.yml`); the installer writes
    the set chosen to `db/` and `docker-compose.database.yml` and removes `.db-dialects/`, so a
    project keeps one `db/base.sql`, one `db/migrations/` and one database server (none for
    SQLite);
  - a SQLite database is a file under `db/`, `sqlite://{__ROOT__}/db/<name>.sqlite`, which works on
    the host and in Docker (it was `sqlite:///<name>`, a file at the filesystem root), and
    `*.sqlite` is ignored by git;
  - the staging and prod placeholder connections use the scheme of the database chosen;
  - the questions use the user of the database chosen (`root`, `postgres`),
    and SQLite is not asked for a host, user or password.

  The installer offers `mysql`, `postgres` and `sqlite`. `sqlsrv` is no longer offered: a project
  created with it could not run its migrations. The create-project CI job runs every database.
  This needs, from the libraries: byjg/anydataset-db (the `postgres://` alias), byjg/migration
  (`postgres://` migrations), byjg/micro-orm (an empty key and "now" on PostgreSQL), byjg/authuser
  (user properties on PostgreSQL) and byjg/gluo-core (`BaseApiTestCase` picking the database).

- An unattended setup that could not read its answers fell back to the questions, and so
  created the project with the default answers: a `SETUP_JSON` naming a file that is not there
  (a Composer running in a container sees only the directories mounted into it), or a
  `setup.json` that is not valid JSON. Both now stop the setup with an error naming the file.
- A Composer running in a container (a dockerised `composer`) warned that Docker is not
  installed and waited for ENTER, because the container has no `docker` command. Inside a
  container the setup now says Docker could not be checked, and goes on.
- A new project kept the starter's own `composer.json` description ("Run `composer
  create-project byjg/gluo` …", with the project's name in it). It is now
  "A PHP REST API built with Gluo."

- The installer rewrites `byjg/gluo` to the project's composer name wherever the template names
  itself, and some comments about the framework said `byjg/gluo` where they meant
  `byjg/gluo-core`: a new project got `BaseUser (acme/shop)` in `src/Model/User.php`, and the same
  in `LoginController.php`. Those comments now name `byjg/gluo-core`, which the rewrite leaves
  alone.

- `src/Generator/UuidSeedGenerator.php` is removed. Nothing in the template used it, and it ran
  the MySQL-only `SELECT hex(uuid_to_bin(uuid()))`. `TableUuidPKAttribute` generates UUID keys;
  for a seed function of your own, return `HexUuidLiteral::forDatabase($executor, HexUuidLiteral::v7())`.

- `composer create-project` wrote the package name twice when the chosen name started
  with `byjg/gluo` (`byjg/gluo-shop` became `byjg/gluo-shop-shop` in `composer.json`).
  The installer now replaces only the template's exact name. Names with any other vendor
  or prefix were never affected.

- `User` and `Task` used micro-orm's `TableMySqlUuidPKAttribute`, which asked MySQL for
  `uuid_to_bin(uuid())`, so every insert into `users` or `task` failed on any other database.
  They now use `TableUuidPKAttribute`, which generates a version 7 UUID in PHP. A project created
  from an earlier template keeps working on MySQL; replace the attribute to run it elsewhere.
- The installer accepted only PHP 8.3, 8.4 and 8.5, although the project supports 8.6. It now
  accepts `8.6` too (interactive prompt and `php_version` in `setup.json`).

## Requirements

- PHP 8.3, 8.4, 8.5 and 8.6 are now supported: `"php": ">=8.3 <8.7"`.
  The previous `<8.6` upper bound excluded PHP 8.6, since `<8.6` is exclusive.
- The Docker image is based on `byjg/php:8.5-fpm-nginx-2026.10` (was `2026.06`). The installer
  replaces the PHP version in that tag, and `2026.06` was never published for 8.6; `2026.10`
  exists for 8.3, 8.4, 8.5 and 8.6.

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

- In this repository, `docker-compose.yml`, `docker-compose.database.yml`, `db/base.sql` and
  `db/migrations/` are no longer versioned: `php gluo --env=dev db:dialect <mysql|postgres|sqlite>`
  writes them from `.db-dialects/`, the only place to change them. The command and
  `.db-dialects/` are removed from a created project. The CI runs the command before the tests and
  before building the image.
- `phpunit.xml.dist` renamed to `phpunit.xml`.
- `.vscode/launch.json` is removed, and `.vscode` is ignored by git.
