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
- pdo_mysql
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
  --mysql-uri=mysql://root:secret@mysql-container/mydb \
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
Git user name [Your Name]:
Git user email [your.email@example.com]:
PHP Version [8.4]: 8.4
Project namespace [MyRest]: Tutorial
Composer name [me/myrest]:
Database schema [mysql]:
Database host [mysql-container]:
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
├── docs/
└── docker-compose.yml
```

Run PHP tooling from the repo root (`composer test`, `composer migrate`,
`composer openapi`) — there is one manifest and one `vendor/`.

#### Configuration Options

- **PHP Version**: The PHP version for your Docker container (8.3, 8.4, 8.5)
- **Project namespace**: Your application's root namespace (must be CamelCase, e.g., `MyApp`, `Tutorial`)
- **Composer name**: Package name in `vendor/package` format (e.g., `me/myrest`)
- **Database schema**: Supported drivers: `mysql`, `postgres`, `sqlsrv`, or `sqlite`
- **Database host**: Hostname or container name for the DB server (ignored for SQLite)
- **Database user**: Username that will be injected in your `.env` files
- **Database password**: Password that will be injected in your `.env` files
- **Dev/Test database name**: Logical databases used for the dev and test environments (for SQLite, this is the file path)
- **Timezone**: Server timezone (e.g., `UTC`, `America/New_York`, `Europe/London`)
- **Install Frontend**: Whether to include the `frontend/` Vite app (login, reset, dashboard, profile). See the [Frontend guide](../guides/frontend.md).
- **Install Examples**: Whether to include example code (Project/Task/Note demonstrating the three patterns, plus Sample endpoints — and, if the frontend is installed, its example screens)

The two options combine into three shapes:

| Frontend | Examples | You get |
|---|---|---|
| Yes | Yes | **Full-stack demo** — API + Project/Task/Note + the Vite app with example screens |
| Yes | No | **Auth app shell** — API + the Vite app (login/reset/dashboard/profile only) |
| No | No | **Pure API** — just the REST API and authentication |

**Tip**: To access the MySQL container locally, add this to your `/etc/hosts` file:
```
127.0.0.1  mysql-container
```

## Running the Project

```shell
cd ~/tutorial
docker compose -f docker-compose.yml up -d
```

## Database Setup

```shell
# Create a fresh database (warning: destroys existing data)
APP_ENV=dev composer run migrate -- reset --yes

# Alternative if local PHP isn't configured:
# export CONTAINER_NAME=myrest  # second part of your composer name
# docker exec -it $CONTAINER_NAME composer run migrate -- reset --yes
```

Expected output:
```text
> Builder\Scripts::migrate
> Environment: dev
> Database: mysql://root:****@mysql-container/localdev

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
APP_ENV=test composer run test
# OR: docker exec -it $CONTAINER_NAME composer run test
```

**Note**: If you chose not to install examples, the project will only include authentication tests. Example tests (Project/Task/Note, Sample) will not be present.

## Documentation

Access the Swagger documentation:
```shell script
open http://localhost:8080/docs
```

## Next Steps

Continue with [creating a new table and CRUD operations](first-table.md).
