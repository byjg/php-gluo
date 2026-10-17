---
sidebar_position: 10
title: Getting Started
---

# Getting Started

## Requirements

- **Docker Engine**: For containerizing your application
- **PHP 8.3+**: For local development
- **IDE**: Any development environment of your choice

> **Windows Users**: If you don't have PHP installed in WSL2, please follow the [Windows](windows.md) guide.

### Required PHP Extensions

- ctype
- curl
- dom
- filter
- hash
- json
- libxml
- mbstring
- openssl
- pcre
- pdo
- pdo_mysql (pdo_pgsql or pdo_sqlite for a PostgreSQL or SQLite project)
- phar
- simplexml
- tokenizer
- xml
- xmlwriter

## Installation

Choose one of the following installation methods:

```shell script
# Standard installation
mkdir ~/tutorial
composer create-project byjg/gluo ~/tutorial ^7.0

# OR Latest development version
mkdir ~/tutorial
composer create-project byjg/gluo ~/tutorial dev-master
```

### Alternative: `shellscript.download`

If you prefer an unattended installer (especially on fresh Linux boxes), use the `shellscript.download` loader:

```bash
/bin/bash -c "$(curl -fsSL https://shellscript.download/install/loader)"
```

After the loader is installed, run the dedicated script:

```bash
# Minimal install
load.sh byjg-gluo -- my-api --namespace=MyApp --name=mycompany/my-api

# Fully customised
load.sh byjg-gluo -- my-api \
  --namespace=MyApp \
  --name=mycompany/my-api \
  --mysql-uri=mysql://root:secret@gluo-database/mydb \
  --install-examples=n \
  --version="^7.0" \
  --php-version=8.4 \
  --timezone=America/New_York
```

The script:
- Generates a temporary `setup.json` (one directory above the target folder) with all answers.
- Runs `composer create-project byjg/gluo ...` using those values.
- Cleans up `setup.json` after success. If the target folder already exists, the script refuses to run (safety measure) — remove the folder first to re-run.

Required flags: the target folder, `--namespace`, and `--name`. Everything else is optional (defaults match the interactive installer). 
Ensure Composer exists locally or combine it with `load.sh php-docker` first.

### Setup Configuration

The installation will prompt you for configuration details:

```text
> Builder\PostCreateScript::run
========================================================
 Setup Project
 Answer the questions below
========================================================

Project Directory: ~/tutorial
PHP Version [8.4]: 8.4
Project namespace [MyRest]: Tutorial
Composer name [me/myrest]:
Database schema (mysql, postgres, sqlite) [mysql]:
Database host [gluo-database]:
Database user [root]:
Database password [mysqlp455w0rd]:
Dev database name [localdev]:
Test database name [localtest]:
Timezone [UTC]:
Install Frontend (Vite app) [Yes]: Yes
Install Examples [Yes]: Yes
Press <ENTER> to continue
```

#### Project layout

A Gluo project is a small monorepo:

```
my-api/
├── composer.json     # the single manifest — the repo root is the PHP app root
├── src/              # Model, Repository, Service, Controller
├── config/           # dev / test / staging / prod
├── db/               # migrations + base.sql
├── public/           # the docroot (app.php, OpenAPI docs)
├── templates/
├── tests/
├── frontend/         # the Vite frontend (only when Install Frontend = Yes)
├── docker/           # Dockerfiles
├── docker-compose.yml
└── README.md         # quick start + link to the online documentation
```

Run PHP tooling from the repo root (`composer test`, `./gluo migrate:update`,
`./gluo openapi`) — there is one manifest and one `vendor/`.

#### Configuration Options

- **PHP Version**: The PHP version for your Docker container (8.3, 8.4, 8.5)
- **Project namespace**: Your application's root namespace (must be CamelCase, e.g., `MyApp`, `Tutorial`)
- **Composer name**: Package name in `vendor/package` format (e.g., `me/myrest`)
- **Database schema**: `mysql`, `postgres` or `sqlite`. The project gets the SQL of that database in `db/`
  (`db/base.sql` and `db/migrations/`) and its server in `docker-compose.database.yml`, which
  `docker-compose.yml` includes (no server for SQLite).
- **Database host**: Hostname of the DB server for the app in the `rest` container: `gluo-database` by default, the
  name of the database service in `docker-compose.database.yml` (not asked for SQLite). The commands you run on
  your machine reach the same server at `127.0.0.1`, through its published port: see [the connections](#database-connections) below
- **Database user**: Username that will be injected in your `.env` files: `root` or `postgres` by default (not asked
  for SQLite)
- **Database password**: Password that will be injected in your `.env` files (not asked for SQLite)
- **Dev/Test database name**: Logical databases used for the dev and test environments. For SQLite, the name of the
  file under `db/`: `localdev` is `db/localdev.sqlite`, connected as `sqlite://{__ROOT__}/db/localdev.sqlite`
- **Timezone**: Server timezone (e.g., `UTC`, `America/New_York`, `Europe/London`)
- **Install Frontend**: Whether to include the `frontend/` Vite app (login, reset, dashboard, profile). See the [Frontend guide](../guides/frontend.md).
- **Install Examples**: Whether to include example code (Project/Task/Note demonstrating the three patterns, plus Sample endpoints — and, if the frontend is installed, its example screens)

The two options combine into three shapes:

| Frontend | Examples | You get |
|---|---|---|
| Yes | Yes | **Full-stack demo** — API + Project/Task/Note + the Vite app with example screens |
| Yes | No | **Auth app shell** — API + the Vite app (login/reset/dashboard/profile only) |
| No | No | **Pure API** — just the REST API and authentication |

### Database connections

The database runs in Docker, and it is reached from two places:

| From | Connection | Set in |
|---|---|---|
| The API, in the `rest` container | `…@gluo-database/localdev` (the service name) | `DBDRIVER_CONNECTION` of the `rest` service, `docker-compose.yml` |
| Your machine: `./gluo`, `composer test` | `…@127.0.0.1/localdev`, `…@127.0.0.1/localtest` (the published port) | `config/dev/credentials.env`, `config/test/credentials.env` |

Nothing has to be added to `/etc/hosts`. The tests always use the test database, wherever they run:
`tests/bootstrap.php` keeps the server of a `DBDRIVER_CONNECTION` set in the environment (the `rest` container's)
and replaces its database with the test one (a SQLite connection is ignored: the test file of `config/test`
applies), so running them in the container cannot reach the dev database.

## Running the Project

```shell
cd ~/tutorial
docker compose -f docker-compose.yml up -d
```

## Database Setup

```shell
# Create a fresh database (warning: destroys existing data)
APP_ENV=dev ./gluo migrate:reset

# Alternative if local PHP isn't configured: run it in the rest container
# docker compose exec rest ./gluo migrate:reset
```

Expected output:
```text
> Environment: dev
> Database: mysql://root:****@127.0.0.1/localdev

Resetting database...
Database reset successfully.
```

## Verify Installation

### If You Installed Examples

```shell script
curl http://localhost:8080/sample/ping
```

Expected response:
```json
{"result":"pong"}
```

### If You Skipped Examples

The project is ready! You can start by:
- Creating your first table following the [getting started tutorial](first-table.md)
- Accessing the API documentation at http://localhost:8080/docs
- The `users` table and authentication endpoints are already available

## Run Tests

```shell script
composer test
# OR, without PHP on your machine: docker compose exec rest composer test
```

Either way they run on the test database (see [the connections](#database-connections)).

**Note**: If you chose not to install examples, the project will only include authentication tests. Example tests (Project/Task/Note, Sample) will not be present.

## Documentation

Access the Swagger documentation:
```shell script
open http://localhost:8080/docs
```

## Next Steps

Continue with [creating a new table and CRUD operations](first-table.md).
