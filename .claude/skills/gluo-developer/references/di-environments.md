# Environments and DI Configuration

## Config folder structure

```
config/
├── dev/                    # dev environment config files
│   ├── 01-infrastructure.php
│   ├── 02-security.php
│   ├── 03-api.php
│   ├── 04-repositories.php
│   ├── 05-services.php
│   ├── 06-external.php
│   └── 07-controllers.php
├── test/                   # test environment (inherits from dev)
│   ├── 01-infrastructure.php
│   └── ...
├── staging/                # staging environment (inherits from dev)
│   └── ...
├── prod/                   # prod environment (inherits from staging)
│   └── ...
├── ConfigBootstrap.php     # defines all environments and their inheritance
├── config-dev.env          # optional flat file alternative to config/dev/ directory
└── .env                    # optional local overrides (not committed)
```

Each environment is a directory. You only need to include files that differ from the parent
environment — inherited keys fill in the rest.

---

## File types inside a config directory

Two formats coexist in the same directory:

### `*.php` — DI bindings and scalar params

Returns an associative array. Keys are class names (DI entries) or plain strings (params).

```php
<?php
// config/dev/05-services.php
use App\Service\ProductService;
use App\Repository\ProductRepository;
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;

return [
    // DI binding — resolved lazily on first Config::get()
    ProductService::class => DI::bind(ProductService::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    // Scalar param — used with Param::get() in other bindings
    'PAYMENT_GATEWAY_URL' => 'https://sandbox.payment.example.com',
];
```

Files are loaded in **alphabetical order** — hence the `01-`, `02-` prefix convention.
Each file's returned array is merged into the environment's config.

### `*.env` — flat key=value pairs

Plain text, one entry per line. Useful for non-secret config values you want in version control
in `.env` style.

```ini
# config/dev/credentials.env
APP_NAME=MyApp
LOG_LEVEL=debug
```

Within a directory, PHP files load alphabetically first, then env files alphabetically.
Later values override earlier ones. Keys from either format are available through `Config::get()`.

---

## Loading order and precedence (highest wins)

```
OS environment variables              ← highest priority — overrides everything
    ▲
config/.env (not committed)            ← overrides env-specific config files
    ▲
config/{env}/    ← directory files    ← PHP files first, then env files
config-{env}.php / config-{env}.env  ← single-file alternative (rarely used here)
    ▲
inherited parent environment(s)       ← lowest priority
```

Practical implications:
- Put secrets in `config/.env` — they override any env-specific value.
- Put environment-specific defaults in `config/{env}/` — they're in version control.
- OS env vars win over everything — useful in CI/CD where secrets are injected via the environment.

The `config/.env` is **not committed** (it's in `.gitignore`). It's the place for local
developer overrides and secrets: database passwords, API keys, anything that varies
per machine or must stay out of the repo.

---

## Selecting the active environment

Select `APP_ENV` through the process environment or the global `php gluo --env` option.
The launcher exports `--env` as `APP_ENV` before initializing the container; it applies to
project commands as well as migrations and codegen.

```bash
php gluo --env=dev migrate:update
php gluo --env=dev codegen:all --table=product --save
APP_ENV=dev php gluo list --check
```

`config/.env` supplies container values after an environment has been selected; setting
`APP_ENV` there does not select the environment. The project-root `.env` may be used by
Docker Compose, but it is not Gluo's configuration override file.

The starter's `phpunit.xml` forces `APP_ENV=test`. `BaseApiTestCase` also refuses to reset
the database unless the selected environment is test. Check the test connection before
running database tests; the first API test resets that database.

The config system auto-initializes the first time `Config::get()` is called by reading
`config/ConfigBootstrap.php`, which defines all environments. You never call bootstrap
manually in application code.

---

## ConfigBootstrap.php — defining environments

`ByJG\Gluo\Config\BaseConfigBootstrap` defines environment inheritance without caching:

```php
$dev = Environment::create('dev');
$test = Environment::create('test')->inheritFrom($dev);
$staging = Environment::create('staging')->inheritFrom($dev);
$prod = Environment::create('prod')->inheritFrom($staging, $dev);
```

The starter's `config/ConfigBootstrap.php` extends it and explicitly enables caching:

```php
use ByJG\Cache\Psr16\FileSystemCacheEngine;
use ByJG\Config\Definition;
use ByJG\Gluo\Config\BaseConfigBootstrap;

return new class extends BaseConfigBootstrap {
    protected function configureDefinition(Definition $definition): void
    {
        parent::configureDefinition($definition);
        $definition->getConfigObject('staging')->withCache(new FileSystemCacheEngine());
        $definition->getConfigObject('prod')->withCache(new FileSystemCacheEngine());
    }
};
```

Inspect the application's override before changing it. Calling the parent preserves the
core's OS imports (`TAG_VERSION` and `TAG_COMMIT`). Add custom environments or config
directories here as needed. See [Cache](cache.md) for cache options.

### Environment options

| Method | Purpose |
|---|---|
| `inheritFrom(Environment ...$envs)` | Merge parent config (child keys win) |
| `withOSEnvironment(['KEY'])` | Pull specific OS env vars into the container |
| `withCache($psr16, CacheModeEnum $mode)` | Cache the resolved config (use in staging/prod) |
| `setAsAbstract()` | Cannot be loaded directly — only inherited |
| `setAsFinal()` | Cannot be inherited from |

`withOSEnvironment()` belongs to `Definition`, not `Environment`. It imports additional
OS keys into the container. Non-empty process environment values also override keys
already present in the configuration when the container is built; those keys do not need
an explicit import. This is not a live lookup after the container has been built.

---

## Accessing config at runtime

```php
use ByJG\Config\Config;

// Resolve a DI binding — returns the singleton/instance
$service = Config::get(ProductService::class);

// Read a scalar param
$url = Config::get('PAYMENT_GATEWAY_URL');   // 'https://sandbox...'

// Read without resolving DI (returns the raw definition, e.g. the DI object itself)
$raw = Config::raw('PAYMENT_GATEWAY_URL');

// Check if a key is registered
if (Config::has('SOME_FEATURE_FLAG')) { ... }

// Reset container — useful in tests to force re-initialization
Config::reset();
```

Prefer constructor injection in controllers and services. Use `Config::get()` where a
container lookup is needed, such as existing framework hooks or bootstrap integration.

---

## DI binding methods

### Starting a binding

```php
use ByJG\Config\DependencyInjection as DI;

// Create a new instance of the class
DI::bind(ProductService::class)

// Re-export an already-registered entry under a new key (alias)
DI::use(ProductService::class)
```

### Constructor injection options

| Method | When to use |
|---|---|
| `withInjectedConstructor()` | All params are class types already in the container |
| `withInjectedConstructorOverrides(['param' => value])` | Mix: auto-inject classes, override scalars |
| `withConstructorArgs([...])` | Provide every argument explicitly (no auto-injection) |
| `withNoConstructor()` | Skip constructor (e.g. class with static factory or no constructor) |

`withInjectedConstructor()` resolves each parameter by its **type hint** from the container.
If a param isn't type-hinted or isn't in the container, the binding will fail at runtime.

`withInjectedConstructorOverrides` matches overrides by **parameter name** (not position).
Any parameter not listed is auto-resolved from the container by type hint:

```php
// ProductRepository is auto-injected; only scalar baseUrl is overridden explicitly
ProductService::class => DI::bind(ProductService::class)
    ->withInjectedConstructorOverrides([
        'baseUrl' => Param::get('PRODUCT_API_URL'),
    ])
    ->toSingleton(),
```

`withConstructorArgs` requires you to list every parameter in order:

```php
HttpClient::class => DI::bind(MockClient::class)
    ->withConstructorArgs([
        (new Response(200))->withBody(new MemoryStream('{"ok":true}'))
    ])
    ->toSingleton(),
```

### Post-construction options

```php
// Call a method after instantiation (chainable)
DI::bind(OpenApiRouteList::class)
    ->withInjectedConstructor()
    ->withMethodCall('withDefaultProcessor', [JsonCleanOutputProcessor::class])
    ->toSingleton()

// Use a static factory method instead of `new`
DI::bind(DatabaseExecutor::class)
    ->withFactoryMethod('buildFromUri', [Param::get('DB_DSN')])
    ->toSingleton()
```

### Lifecycle options

| Method | Behaviour |
|---|---|
| `toSingleton()` | One instance per container lifetime (standard for services, repos) |
| `toInstance()` | New instance on every `Config::get()` call |
| `toEagerSingleton()` | Singleton, but created immediately at container boot (not lazily) |

Use `toEagerSingleton()` for things that must run at startup — for example, the ORM
initialization that calls `ORM::defaultDbDriver()`:

```php
// 01-infrastructure.php
'ORMInitialization' => DI::bind(SomeOrmInit::class)
    ->withInjectedConstructor()
    ->toEagerSingleton(),
```

---

## Param::get() — lazy cross-references

`Param::get('KEY')` creates a placeholder that is resolved at the moment the binding is
evaluated, not when the config file is loaded. This lets you reference a scalar param
defined in the same or a parent config file:

```php
// Scalar defined elsewhere in the config
'DB_DSN' => 'mysql://root:secret@localhost/mydb',

// Reference it by key — resolved lazily
DatabaseExecutor::class => DI::bind(DatabaseExecutor::class)
    ->withConstructorArgs([Param::get('DB_DSN')])
    ->toSingleton(),
```

`Param::get()` works across environment inheritance — if `DB_DSN` is defined in `dev/` and
test doesn't override it, test still resolves the dev value.

---

## Environment swap pattern (test vs dev/prod)

The key pattern for environment-specific behaviour is to re-bind only the parts that differ.
Because a child environment inherits everything from its parent, you only need to add
overrides in `config/test/`.

Example: swap a real HTTP client for a mock in tests without touching service code.

```php
// config/dev/06-external.php  (and prod)
HttpClient::class => DI::bind(HttpClient::class)
    ->withNoConstructor()
    ->toSingleton(),

PaymentGatewayService::class => DI::bind(PaymentGatewayService::class)
    ->withInjectedConstructorOverrides([
        'baseUrl' => Param::get('PAYMENT_GATEWAY_URL'),
    ])
    ->toSingleton(),
```

```php
// config/test/06-external.php
// Only re-bind HttpClient — PaymentGatewayService binding is inherited unchanged
HttpClient::class => DI::bind(MockClient::class)
    ->withConstructorArgs([
        (new Response(200))->withBody(new MemoryStream('{"status":"ok"}'))
    ])
    ->toSingleton(),
```

`MockClient extends HttpClient`, so `PaymentGatewayService` — which only knows `HttpClient`
by type — receives the mock transparently in the test environment.

---

## Quick reference

| Goal | Pattern |
|---|---|
| Register a service | `DI::bind(Foo::class)->withInjectedConstructor()->toSingleton()` |
| Inject class + scalar | `->withInjectedConstructorOverrides(['param' => value])` |
| All manual args | `->withConstructorArgs([...])` |
| No constructor | `->withNoConstructor()` |
| Call a setter after build | `->withMethodCall('setFoo', [$arg])` |
| Alias an existing binding | `DI::use(Foo::class)` |
| Reference another param | `Param::get('KEY')` |
| Eager init at boot | `->toEagerSingleton()` |
| Resolve at runtime | `Config::get(Foo::class)` |
| Read scalar | `Config::get('MY_PARAM')` |
| Check key exists | `Config::has('KEY')` |
| Reset in tests | `Config::reset()` |
