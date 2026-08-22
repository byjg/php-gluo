---
sidebar_position: 330
title: Dependency Injection
---

# Dependency Injection & Configuration Container

The project uses the [PSR-11](https://www.php-fig.org/psr/psr-11/) container to manage dependencies. This document covers both the configuration structure and the DI binding patterns that wire everything together.

## Configuration Structure

The configuration is organized by environment in the `config/{environment}/` folders. Each environment can have:

- **credentials.env** - Environment variables (database connections, API keys, JWT secrets, etc.)
- **Numbered PHP files** - Dependency injection bindings organized by layer:
  - `01-infrastructure.php` - Database, Cache, Logging
  - `02-security.php` - JWT, Authentication, CORS
  - `03-api.php` - OpenAPI routes, Middleware
  - `04-repositories.php` - Repository bindings
  - `05-services.php` - Service bindings
  - `06-external.php` - External services (Email, etc.)
  - `07-controllers.php` - Controller bindings

You must set the `APP_ENV` environment variable to specify which environment to use.

### Example: credentials.env

```env title="config/dev/credentials.env"
WEB_SERVER=localhost
DASH_SERVER=localhost
WEB_SCHEMA=http
API_SERVER=localhost
API_SCHEMA=http
DBDRIVER_CONNECTION=mysql://root:mysqlp455w0rd@mysql-container/mydb
EMAIL_CONNECTION=smtp://username:password@mail.example.com
JWT_SECRET=OFbOmC2VxlgQHNrBLa/wyj7/fFkgPnLpckbXMVuIU7Sqb3RTztNx3xzEYaoeA31JUpvBjkD7FRKBFGQ0+fnTig==
CORS_SERVERS=.*
```

### Example: 01-infrastructure.php

```php title="config/dev/01-infrastructure.php"
<?php

use ByJG\Cache\Psr16\BaseCacheEngine;
use ByJG\Cache\Psr16\NoCacheEngine;
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;

return [
    BaseCacheEngine::class => DI::bind(NoCacheEngine::class)->toSingleton(),

    DbDriverInterface::class => DI::bind(Factory::class)
        ->withFactoryMethod("getDbRelationalInstance", [Param::get('DBDRIVER_CONNECTION')])
        ->toSingleton(),
];
```

The configuration is loaded by the [byjg/config](https://github.com/byjg/config) library.

## Getting Configuration Values

Use the `Config::get()` method:

```php
Config::get('WEB_SERVER');
```

## Environment Hierarchy

The environments and their inheritance are defined in `ByJG\Gluo\Config\BaseConfigBootstrap` (byjg/gluo-core); the project's `config/ConfigBootstrap.php` just extends it.

The project has four environments with the following inheritance hierarchy:

```mermaid
graph TD
    dev[dev - Development]
    test[test - Testing]
    staging[staging - Staging]
    prod[prod - Production]

    dev --> test
    dev --> staging
    staging --> prod

    style dev fill:#90EE90
    style test fill:#87CEEB
    style staging fill:#FFD700
    style prod fill:#FF6347
```

**Inheritance Rules:**
- **test** inherits from **dev**
- **staging** inherits from **dev** (with caching enabled)
- **prod** inherits from **staging** and **dev** (with caching enabled)

Child environments override parent configurations. For example:
- `config/dev/credentials.env` defines base database connection
- `config/prod/credentials.env` overrides with production database connection
- `config/prod/01-infrastructure.php` overrides to use FileSystemCache instead of NoCache

The project bootstrap in `config/ConfigBootstrap.php` is intentionally tiny — the
environment set, inheritance and caching live in gluo-core, so improvements arrive with
`composer update`:

```php
<?php

use ByJG\Gluo\Config\BaseConfigBootstrap;

return new class extends BaseConfigBootstrap {
};
```

To register more OS environment variables, add config directories, or define extra
environments, override `configureDefinition()`:

```php
return new class extends BaseConfigBootstrap {
    #[\Override]
    protected function configureDefinition(Definition $definition): void
    {
        parent::configureDefinition($definition); // keeps TAG_VERSION / TAG_COMMIT
        $definition->withOSEnvironment(['DATABASE_URL', 'REDIS_HOST']);
    }
};
```

The Config is automatically initialized when first accessed, thanks to byjg/config's auto-initialization feature.

---

## Dependency Injection Patterns

Dependency Injection (DI) decouples your code from specific implementations, making it easier to swap dependencies based on environment or requirements.

### Example: Environment-Specific Cache

You might want caching enabled in production but disabled in development for easier debugging.

**Development** - `config/dev/01-infrastructure.php`:

```php
<?php

use ByJG\Cache\Psr16\BaseCacheEngine;
use ByJG\Cache\Psr16\NoCacheEngine;
use ByJG\Config\DependencyInjection as DI;

return [
    BaseCacheEngine::class => DI::bind(NoCacheEngine::class)
        ->toSingleton(),
];
```

**Production** - `config/prod/01-infrastructure.php`:

```php
<?php

use ByJG\Cache\Psr16\BaseCacheEngine;
use ByJG\Cache\Psr16\FileSystemCacheEngine;
use ByJG\Config\DependencyInjection as DI;

return [
    BaseCacheEngine::class => DI::bind(FileSystemCacheEngine::class)
        ->toSingleton(),
];
```

**Usage in Code:**

```php
<?php

use ByJG\Config\Config;
use ByJG\Cache\Psr16\BaseCacheEngine;

// Get the cache instance (implementation depends on APP_ENV)
$cache = Config::get(BaseCacheEngine::class);

// Use it the same way regardless of environment
$cache->set('key', 'value', 3600);
$value = $cache->get('key');
```

The application automatically returns the correct implementation based on the `APP_ENV` environment variable.

### Constructor Injection (`withInjectedConstructor`)

```php
ProjectService::class => DI::bind(ProjectService::class)
    ->withInjectedConstructor()
    ->toSingleton(),
```

The container automatically injects dependencies defined in the constructor based on their type hints.

### Constructor with Parameters (`withConstructorArgs`)

```php
JwtWrapper::class => DI::bind(JwtWrapper::class)
    ->withConstructorArgs([
        Param::get('API_SERVER'),
        Param::get(JwtKeyInterface::class)
    ])
    ->toSingleton(),
```

Mix environment parameters and other dependencies.

### Factory Method (`withFactoryMethod`)

```php
DbDriverInterface::class => DI::bind(Factory::class)
    ->withFactoryMethod("getDbRelationalInstance", [
        Param::get('DBDRIVER_CONNECTION')
    ])
    ->toSingleton(),
```

Use a factory method instead of a constructor.

### Singleton vs Transient (`toSingleton`)

```php
// Singleton - Same instance every time
MyService::class => DI::bind(MyService::class)->toSingleton(),

// Transient - New instance every time
MyService::class => DI::bind(MyService::class),
```

### Controllers

The `Server` is bound with `withContainer(Param::container())` in `03-api.php`, so it
resolves route controllers from the container rather than instantiating them directly.
That is what lets a controller declare its dependencies in the constructor.

Controllers are not listed one by one. They are *terminal* classes — nothing depends on
them — so a per-class binding would encode no decision: one implementation named directly
by the router, always per-request, and every constructor argument a type-hinted service
that is itself explicitly bound. A single `Autowire` rule covers the namespace:

```php
// config/dev/07-controllers.php
'RestReferenceArchitecture\Controller\*' => Autowire::rule()
    ->withInjectedConstructor()
    ->toInstance(),
```

Three things to know:

- **A controller that declares no constructor** (an ActiveRecord one, say) degrades to
  `withConstructorNoArgs()` automatically — no special case needed.
- **An explicit binding wins**, so a controller needing a scalar argument can still be
  declared by hand in the same file.
- **A controller outside the namespace is not covered** and fails with **501** naming the
  class, rather than being built without its dependencies.

Services and repositories stay explicit: their bindings *do* carry decisions, so a pattern
there would be convention standing in for a real choice.

`Param::container()` resolves to the container itself. Do not reach for
`Config::getContainer()` inside a config file — the facade is not populated until the
container finishes building.

## Configuration Organization

Dependencies are organized by layer in numbered files:

- `01-infrastructure.php` - Database, Cache, Logging
- `02-security.php` - JWT, Authentication, User Management
- `03-api.php` - OpenAPI, Routes, Middleware
- `04-repositories.php` - Data access layer
- `05-services.php` - Business logic layer
- `06-external.php` - Email, SMS, external APIs
- `07-controllers.php` - REST controllers

This organization makes it easy to find and modify related configurations.

## Related Documentation

- [Configuration Guide](../guides/configuration.md) - Advanced configuration patterns
- [Service Layer](../guides/services.md) - Registering services in the container
- [Architecture](architecture.md) - How DI fits the overall architecture
