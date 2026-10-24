---
sidebar_position: 140
title: Database Migrations
---

# Database Migration

## Usage

```bash
APP_ENV=<environment> php gluo migrate:<command> [options]
php gluo --env=<environment> migrate:<command> [options]
```

Run `php gluo migrate:update --help` to display usage information and the available commands. `composer migrate -- <command>` still works as an alias for `php gluo migrate:<command>`.

### Environment

You can specify the environment in two ways:
- Set the `APP_ENV` environment variable
- Use the `--env=<environment>` parameter (overrides `APP_ENV`)

**Note:** At least one method must be used to specify the environment (dev, test, prod).

### Available Commands

| Command   | Description                                                      |
|-----------|------------------------------------------------------------------|
| `version` | Show current database version (alias: `status`)                  |
| `create`  | Create migration version table (alias: `install`)                |
| `reset`   | Reset database to base.sql and optionally migrate to a version   |
| `up`      | Migrate up to a specific version or latest                       |
| `down`    | Migrate down to a specific version or 0                          |
| `update`  | Intelligently migrate up or down to a specific version           |

### Available Options

| Option              | Description                                           |
|---------------------|-------------------------------------------------------|
| `-u=N, --version=N` | Target version for migration                          |
| `--force`           | Force migration even if database is in partial state  |
| `--no-transaction`  | Disable transaction support                           |
| `-v, -vv, -vvv`     | Increase verbosity (shows more details)               |

## Create a New Database

You can create a fresh new database using the command:

```bash
# Using APP_ENV
APP_ENV=dev php gluo migrate:reset

# Using --env parameter
php gluo --env=dev migrate:reset

# Reset and migrate to specific version
php gluo --env=dev migrate:reset --version=5
```

:::warning
Use this command carefully. It will drop all tables and create a new database from base.sql.
:::

## Update the Database

You can update the database using the command:

```bash
# Migrate to latest version
APP_ENV=dev php gluo migrate:up

# Migrate to specific version
php gluo --env=dev migrate:update --version=5

# Migrate with verbose output
APP_ENV=dev php gluo migrate:up -vv
```

The `up` command applies all pending migrations. The `update` command intelligently migrates up or down to reach the specified version.

## Rollback the Database

You can rollback the database to a previous version:

```bash
# Rollback to version 3
APP_ENV=dev php gluo migrate:down --version=3

# Rollback completely (to version 0)
php gluo --env=dev migrate:down --version=0
```

## Create a New Migration Version

Create a new file in the `db/migrations/up` folder with the format `00XXX-description.sql`, where `XXX` is a sequential number:

**Example:** `db/migrations/up/00003-add-users-table.sql`

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL
);
```

### Rollback Support

To support rollbacks, create a corresponding file in `db/migrations/down`:

**Example:** `db/migrations/down/00002-rollback-users-table.sql`

```sql
DROP TABLE users;
```

:::tip
Migration numbers must be unique and sequential. The "down" migration number corresponds to the version you'll have after the rollback.
:::

For more information about the migration process, refer to [byjg/migration](https://github.com/byjg/migration).
