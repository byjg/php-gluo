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
- **Flat, numbered files**: `config/{env}/01-infrastructure.php` … `07-controllers.php`, loaded in filename order
- **`.env` params**: each environment has a `credentials.env`; `config/.env` provides local machine overrides
- **Caching**: staging and prod cache the resolved container, set in `config/ConfigBootstrap.php` ([see below](#5-environment-specific-caching))

**Location**: `config/`

## Environment Hierarchy

The environments and their inheritance are defined in
`ByJG\Gluo\Config\BaseConfigBootstrap` (byjg/gluo-core). Your project's
`config/ConfigBootstrap.php` just extends it:

```
Development (dev)
├── Test (test)            # Inherits from dev
└── Staging (staging)      # Inherits from dev, cached (config/ConfigBootstrap.php)
    └── Production (prod)  # Inherits from staging (then dev), cached (config/ConfigBootstrap.php)
```

### How Inheritance Works

This is the actual definition in gluo-core:

```php
// ByJG\Gluo\Config\BaseConfigBootstrap (vendor/byjg/gluo-core)
$dev = Environment::create('dev');
$test = Environment::create('test')
    ->inheritFrom($dev);
$staging = Environment::create('staging')
    ->inheritFrom($dev);
$prod = Environment::create('prod')
    ->inheritFrom($staging, $dev);
```

An environment only needs to define what differs from its parent: a key defined in
`config/prod/` overrides the same key inherited from `staging`/`dev`.

### Inheritance Example

```
config/
├── dev/
│   └── credentials.env      # DBDRIVER_CONNECTION=mysql://...@127.0.0.1/localdev
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
    ├── 06-external.php       # Mail and other external services
    └── 07-controllers.php    # The Autowire rule for the controllers
```

byjg/config loads, for the active environment (and its parents): every `*.php` file
(DI bindings and params), every `*.env` file (plain params), and finally `config/.env`
for local overrides.

## Environment-Specific Configs

Values that vary per environment live in each environment's `credentials.env` and are
consumed in the PHP config files via `Param::get()`:

```ini title="config/dev/credentials.env"
DBDRIVER_CONNECTION=mysql://root:mysqlp455w0rd@127.0.0.1/localdev
JWT_SECRET=ZGV2LS1qd3Qtc2VjcmV0...
CORS_SERVERS=.*
```

```php title="config/dev/01-infrastructure.php (excerpt)"
use ByJG\AnyDataset\Db\Factory;
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;

return [
    DbDriverInterface::class => DI::bind(Factory::class)
        ->withFactoryMethod("getDbInstance", [Param::get('DBDRIVER_CONNECTION')])
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

The project bootstrap is intentionally small — the environment set and inheritance live
in gluo-core, and improvements arrive with `composer update`. The project only decides
where to cache the resolved container:

```php
<?php

use ByJG\Cache\Psr16\FileSystemCacheEngine;
use ByJG\Config\Definition;
use ByJG\Gluo\Config\BaseConfigBootstrap;

return new class extends BaseConfigBootstrap {
    #[\Override]
    protected function configureDefinition(Definition $definition): void
    {
        parent::configureDefinition($definition);

        // Cache the resolved container in the system temp dir: each new container starts empty
        $definition->getConfigObject('staging')->withCache(new FileSystemCacheEngine());
        $definition->getConfigObject('prod')->withCache(new FileSystemCacheEngine());
    }
};
```

### Customizing the Definition

Override `configureDefinition()` to add OS environment variables, extra config
directories, custom environments, or a config cache
([Environment-Specific Caching](#5-environment-specific-caching)):

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
        ->withFactoryMethod("getDbInstance", [Param::get('DBDRIVER_CONNECTION')])
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

Caching the resolved container saves reading the config files on every request. gluo-core
doesn't set it, because where the cache lives depends on how you deploy: the project sets it
in `config/ConfigBootstrap.php` ([Configuration Bootstrap](#configuration-bootstrap)), and
the template caches `staging` and `prod` with `FileSystemCacheEngine` in the system temp dir.

`withCache()` accepts any PSR-16 cache, but it must be a ready-made instance: the cache is
read before any config file is loaded, so it can't come from the DI container
(`Param::get(BaseCacheEngine::class)` would need the container it is meant to cache). The cached container belongs to one release, so it
must not outlive the deploy:

- **Per container** (`FileSystemCacheEngine` in the system temp dir, a memcached sidecar in
  the same pod): each deploy starts with an empty cache. This is the safe choice.
- **Shared or persistent** (a memcached/Redis service, a persistent volume): the new release
  would load the previous release's container. Clear the cache on every deploy, or give each
  release its own keys (e.g. memcached's `OPT_PREFIX_KEY` set to the release version).

The same applies to the route cache (`OpenApiRouteList::withCache()` in `03-api.php`), which
uses the `BaseCacheEngine` binding.

After changing config in an environment with a cache, clear it (or redeploy).

## Related Documentation

- [Getting Started Guide](../getting-started/installation.md)
- [PSR-11 Dependency Injection](../concepts/dependency-injection.md)
- [JWT Configuration](jwt-advanced.md)
