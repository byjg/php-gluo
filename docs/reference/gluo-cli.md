---
sidebar_position: 395
title: The gluo Command
---

# The `gluo` Command

`./gluo` is the command-line entry point of a Gluo project. It runs two kinds of commands:

- **Built-in commands**, provided by `byjg/gluo-core`: migrations, code generation, the
  OpenAPI spec and the interactive terminal.
- **Project commands**, defined by your application: any public method, by a short name.

```bash
./gluo                                 # same as ./gluo list
./gluo list [--check]                  # list built-in and project commands
./gluo [--env=<env>] <command> [args...]
./gluo <command> --help                # help for the command
```

Run it from the project root. It exits with 0 on success and 1 on any failure (unknown
command, exception from the command, `list --check` with a status other than `OK`).
Add `-v` to print the stack trace of a failure.

## Selecting the environment

`--env=<env>`, `--env <env>` or `-e <env>`, before or after the command name:

```bash
./gluo --env=dev migrate:update
./gluo migrate:update --env dev
APP_ENV=dev ./gluo migrate:update      # the existing APP_ENV applies when --env is absent
```

The launcher consumes the option and exports it as `APP_ENV` before the configuration is
loaded. It is not passed to the command.

## Built-in commands

| Command | What it does |
|---|---|
| `migrate:update` | Migrate up or down to a version, or to the latest |
| `migrate:up` / `migrate:down` | Migrate in one direction |
| `migrate:reset` | Recreate the database from `db/base.sql`, then migrate |
| `migrate:version` | Show the current database version (alias: `migrate:status`) |
| `migrate:create` | Create the migration version table (alias: `migrate:install`) |
| `codegen:all` | Generate every component for a table |
| `codegen:model`, `codegen:repository`, `codegen:service`, `codegen:controller`, `codegen:test` | Generate one component (`codegen:repo` is an alias) |
| `openapi` | Regenerate `public/docs/openapi.json` from the controller attributes |
| `openapi:check` | Warn when the spec is older than its sources |
| `terminal` | Open the [interactive PHP terminal](scriptify.md) |

Everything after the command name is passed to the command unchanged:

```bash
./gluo --env=dev migrate:reset --version=5
./gluo --env=dev migrate:up -vv
./gluo --env=dev codegen:all --table=product --save
./gluo --env=dev codegen:model --table=note --activerecord
```

See [Database Migrations](../guides/migrations.md) and the
[Code Generator](code-generator.md) for the options of each.

The built-ins run through the project's `builder/Scripts.php`, so anything you override
there (`getAppNamespace()`, the OpenAPI scan paths, extra migration drivers) applies.

### Composer scripts

The Composer scripts are aliases, so existing pipelines keep working:

| Composer script | Runs |
|---|---|
| `composer migrate -- --env=dev update` | `./gluo migrate --env=dev update` |
| `composer codegen -- --env=dev --table=product all --save` | `./gluo codegen --env=dev --table=product all --save` |
| `composer openapi` | `./gluo openapi` |
| `composer openapi:check` | `./gluo openapi:check` |
| `composer terminal` | `./gluo terminal` |

`./gluo migrate <command>` and `./gluo codegen <components>`, without the colon, exist for
these aliases. The `codegen` form is also the way to generate several components in one
run: `./gluo --env=dev codegen --table=product model controller --save`.

`composer test`, `composer psalm` and `composer compile` are development tooling and stay
as Composer scripts.

## Project commands

Declare them in `config/commands.php`, as a command name mapped to `Namespace/Class::method`:

```php
<?php
return [
    'user:cleanup' => 'MyApp/Service/MaintenanceService::cleanOldRecords',
    'mail:welcome' => 'MyApp/Service/MailService::sendWelcomeEmail',
    'migrate:seed' => 'MyApp/Service/SeedService::run',
];
```

```php
/**
 * Delete records older than the given number of days.
 * @param int $days
 * @param string|null $table
 */
public function cleanOldRecords(int $days, ?string $table = null): void
```

```bash
./gluo user:cleanup 30
./gluo user:cleanup 30 audit_log
./gluo --env=prod user:cleanup 30
./gluo user:cleanup --help
```

- **Arguments are positional.** Each value after the command name maps, in order, to the
  method parameters. They arrive as strings, and PHP converts them to the scalar parameter
  types (`"30"` becomes `int $days`).
- **`--help` prints the method's PHPDoc** and a usage line built from its signature.
- **Names** are lowercase, `group:action`, using `a-z`, `0-9`, `:` and `-`. The exact name
  of a built-in command is reserved, but the groups are open: `migrate:seed` is a valid
  project command.
- **The class must be registered in the container** (`config/dev/05-services.php`, for
  example). It is built by the container, never with `new`, so its constructor
  dependencies are injected as everywhere else in the application.
- **The method must be public.**

Commands are run by [Scriptify](scriptify.md), with the container returned by
`config/scriptify-bootstrap.php`. The same target and bootstrap work with
`scriptify install`, to run a command as a system service:

```bash
sudo vendor/bin/scriptify install --template=systemd queue-worker \
    --class "MyApp/Service/QueueWorker::processLoop" \
    --bootstrap "config/scriptify-bootstrap.php" \
    --rootdir "/var/www/myapi"
```

## Checking the commands

`./gluo list` only lists. `./gluo list --check` adds a status to each command, without
running any of them — useful during development and in CI:

```text
Environment: dev

Built-in
  codegen:all            OK
  migrate:update         DB_UNREACHABLE    SQLSTATE[HY000] [2002] Connection refused
  openapi                OK
  terminal               OK

Project
  mail:welcome           OK                MyApp/Service/MailService::sendWelcomeEmail
  queue:work             NOT_IN_CONTAINER  MyApp/Service/QueueWorker::processLoop
  user:cleanup           MISSING_METHOD    MyApp/Service/MaintenanceService::cleanOldRecord
```

It honours `--env`, so `./gluo --env=prod list --check` validates the production
configuration, and it exits with 1 unless every command is `OK`.

| Built-in status | Meaning | Applies to |
|---|---|---|
| `NO_ENV` | Neither `--env` nor `APP_ENV` is set | `migrate:*`, `codegen:*` |
| `CONFIG_ERROR` | The configuration fails to load for the environment | `migrate:*`, `codegen:*` |
| `NO_DB_CONFIG` | `DBDRIVER_CONNECTION` is not defined | `migrate:*`, `codegen:*` |
| `DB_UNREACHABLE` | The database connection fails (the password is masked in the message) | `migrate:*`, `codegen:*` |
| `MISSING_EXTENSION` | A required PHP extension is not loaded (`readline`) | `terminal` |

| Project status | Meaning |
|---|---|
| `INVALID_NAME` | The name has characters outside `a-z`, `0-9`, `:` and `-` |
| `RESERVED` | The name is the exact name of a built-in command |
| `INVALID_TARGET` | The target is not in `Class::method` form |
| `MISSING_CLASS` | The class does not exist |
| `MISSING_METHOD` | The method does not exist |
| `NOT_PUBLIC` | The method is not public |
| `NOT_IN_CONTAINER` | The class is not registered in the container |
| `ERROR` | The check itself failed, for example because the container could not be loaded |

The check never builds your classes: it uses reflection and asks the container whether it
*has* the class, so it does not open the connections or clients a constructor would. The
one exception is the database, for the built-ins that need it: one connection is opened
and closed, with no query and without touching the migration version table.
