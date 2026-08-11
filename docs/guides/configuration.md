---
sidebar_position: 220
title: Configuration
---

# Configuration Deep Dive

Advanced configuration topics including environment inheritance, config loading, credentials management, and multi-environment setup.

## Table of Contents

- [Overview](#overview)
- [Environment Hierarchy](#environment-hierarchy)
- [Configuration Files](#configuration-files)
- [Environment-Specific Configs](#environment-specific-configs)
- [Credentials Management](#credentials-management)
- [Configuration Bootstrap](#configuration-bootstrap)
- [Best Practices](#best-practices)

## Overview

Configuration is powered by [byjg/config](https://github.com/byjg/config):

- **Environment Inheritance**: test and staging inherit from dev; prod inherits from staging
- **Flat, numbered files**: `config/{env}/01-infrastructure.php` … `06-external.php`, loaded in filename order
- **`.env` params**: each environment has a `credentials.env`; `config/.env` provides local machine overrides
- **Caching**: staging and prod cache the resolved container between requests

**Location**: `config/`

## Environment Hierarchy

The environments and their inheritance are defined in
`ByJG\Gluo\Config\BaseConfigBootstrap` (byjg/gluo-core). Your project's
`config/ConfigBootstrap.php` just extends it:

```
Development (dev)
├── Test (test)            # Inherits from dev
└── Staging (staging)      # Inherits from dev, cached
    └── Production (prod)  # Inherits from staging (then dev), cached
```

### How Inheritance Works

This is the actual definition in gluo-core:

```php
// ByJG\Gluo\Config\BaseConfigBootstrap (vendor/byjg/gluo-core)
$dev = Environment::create('dev');
$test = Environment::create('test')
    ->inheritFrom($dev);
$staging = Environment::create('staging')
    ->inheritFrom($dev)
    ->withCache(new FileSystemCacheEngine());
$prod = Environment::create('prod')
    ->inheritFrom($staging, $dev)
    ->withCache(new FileSystemCacheEngine());
```

An environment only needs to define what differs from its parent: a key defined in
`config/prod/` overrides the same key inherited from `staging`/`dev`.

### Inheritance Example

```
config/
├── dev/
│   └── credentials.env      # DBDRIVER_CONNECTION=mysql://...@mysql-container/localdev
├── staging/
│   └── credentials.env      # Override: staging database and secrets
└── prod/
    └── credentials.env      # Override: production database and secrets
```

**Result**:
- `dev` uses its own connection
- `test` inherits everything from `dev` it does not override
- `staging` overrides the connection; everything else falls back to `dev`
- `prod` starts from `staging` and overrides what differs

## Configuration Files

Each environment directory contains flat, numbered PHP files — the numbering controls
load order, and later definitions win:

```
config/
├── ConfigBootstrap.php       # Bootstrap (extends gluo-core BaseConfigBootstrap)
├── .env                      # Local machine overrides (gitignored; see .env.sample)
└── {environment}/
    ├── credentials.env       # Connection strings, JWT secret, mail, CORS
    ├── 01-infrastructure.php # Database, cache, logging, ORM init
    ├── 02-security.php       # JWT, password policy, users service
    ├── 03-api.php            # Route list, middleware, HTTP handler
    ├── 04-repositories.php   # Repository DI bindings
    ├── 05-services.php       # Service DI bindings
    └── 06-external.php       # Mail and other external services
```

byjg/config loads, for the active environment (and its parents): every `*.php` file
(DI bindings and params), every `*.env` file (plain params), and finally `config/.env`
for local overrides.

## Environment-Specific Configs

Values that vary per environment live in each environment's `credentials.env` and are
consumed in the PHP config files via `Param::get()`:

```ini title="config/dev/credentials.env"
DBDRIVER_CONNECTION=mysql://root:mysqlp455w0rd@mysql-container/localdev
JWT_SECRET=ZGV2LS1qd3Qtc2VjcmV0...
CORS_SERVERS=.*
```

```php title="config/dev/01-infrastructure.php (excerpt)"
use ByJG\AnyDataset\Db\Factory;
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;

return [
    DbDriverInterface::class => DI::bind(Factory::class)
        ->withFactoryMethod("getDbRelationalInstance", [Param::get('DBDRIVER_CONNECTION')])
        ->toSingleton(),
];
```

To change the database in another environment, override only the key:

```ini title="config/prod/credentials.env"
DBDRIVER_CONNECTION=mysql://produser:secret@db.internal/myapp
```

### Reading OS Environment Variables

To pull values from the operating-system environment (Docker/Kubernetes secrets),
register them in your bootstrap (see below) with `withOSEnvironment()`. The default
bootstrap already exposes `TAG_VERSION` and `TAG_COMMIT`.

## Credentials Management

### Per-environment `credentials.env`

Each environment ships a `credentials.env` with its connection strings and `JWT_SECRET`.
`composer create-project` regenerates a **unique JWT secret for every environment**.

### Local overrides: `config/.env`

**File**: `config/.env` (not committed — it is in `.gitignore`; a documented example
lives in `config/.env.sample`)

Use it for developer-machine specifics you don't want in version control:

```ini title="config/.env"
# Override database connection for local development
DBDRIVER_CONNECTION=mysql://root:secret@127.0.0.1/localdev

# Override JWT secret for local testing
JWT_SECRET=local-dev-secret-key
```

:::caution Keep secrets out of version control
Real production secrets belong in OS environment variables (via `withOSEnvironment()`)
or in a secrets manager — not committed to `credentials.env`.
:::

## Configuration Bootstrap

**File**: `config/ConfigBootstrap.php`

The project bootstrap is intentionally tiny — the environment set, inheritance, and
caching live in gluo-core, and improvements arrive with `composer update`:

```php
<?php

use ByJG\Gluo\Config\BaseConfigBootstrap;

return new class extends BaseConfigBootstrap {
};
```

### Customizing the Definition

Override `configureDefinition()` to add OS environment variables, extra config
directories, or custom environments:

```php
return new class extends BaseConfigBootstrap {
    #[\Override]
    protected function configureDefinition(Definition $definition): void
    {
        parent::configureDefinition($definition); // keeps TAG_VERSION / TAG_COMMIT

        // Expose more OS environment variables as params
        $definition->withOSEnvironment(['DATABASE_URL', 'REDIS_HOST']);
    }
};
```

## Best Practices

### 1. Use OS Environment Variables for Production Secrets

```php
// Good - registered OS variable, injected by the platform
$definition->withOSEnvironment(['DATABASE_URL', 'JWT_SECRET']);

// Bad - production secret committed in credentials.env
```

### 2. Override Only What Differs

Rely on inheritance: keep the complete configuration in `dev`, and let `test`,
`staging`, and `prod` define only their deltas. Small environment dirs are a
feature, not an omission.

### 3. Use `Param::get()` for Values, `DI::bind()` for Services

```php
return [
    // A value resolved at injection time
    DbDriverInterface::class => DI::bind(Factory::class)
        ->withFactoryMethod("getDbRelationalInstance", [Param::get('DBDRIVER_CONNECTION')])
        ->toSingleton(),

    // A service with injected constructor
    DatabaseExecutor::class => DI::bind(DatabaseExecutor::class)
        ->withInjectedConstructor()
        ->toSingleton(),
];
```

### 4. Keep the Layer Numbering

```
# Good - layered by concern, loaded in order
config/dev/01-infrastructure.php
config/dev/02-security.php
config/dev/03-api.php

# Bad - everything in one file
config/dev/app.php
```

Later files can reference bindings from earlier ones — repositories (04) build on the
database (01), services (05) build on repositories.

### 5. Environment-Specific Caching

`staging` and `prod` already cache the resolved definition with
`FileSystemCacheEngine` (see `BaseConfigBootstrap`). After changing config in those
environments, clear the cached container (the cache lives in the system temp dir).

## Related Documentation

- [Getting Started Guide](../getting-started/installation.md)
- [PSR-11 Dependency Injection](../concepts/dependency-injection.md)
- [JWT Configuration](jwt-advanced.md)
