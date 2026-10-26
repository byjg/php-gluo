# Cache: byjg/cache-engine

All cache engines implement **PSR-16 `CacheInterface`** (SimpleCache). The container binds
the engine to `BaseCacheEngine::class`, so services only depend on the base type and the
real engine is swapped per environment.

---

## Available engines

| Engine | Storage | Use case | Constructor |
|---|---|---|---|
| `NoCacheEngine` | None (pass-through) | Dev / testing — default in dev | `new NoCacheEngine()` |
| `ArrayCacheEngine` | PHP array (per-request) | Single request / unit tests | `new ArrayCacheEngine()` |
| `FileSystemCacheEngine` | Disk files | Prod when Redis isn't available | `new FileSystemCacheEngine($prefix, $path)` |
| `TmpfsCacheEngine` | `/dev/shm` (RAM disk) | Fast single-server | `new TmpfsCacheEngine($prefix)` |
| `RedisCacheEngine` | Redis | Distributed, high-throughput | `new RedisCacheEngine($server, $password)` |
| `MemcachedEngine` | Memcached cluster | Distributed | `new MemcachedEngine($servers[])` |
| `SessionCacheEngine` | `$_SESSION` | Per-user session data | `new SessionCacheEngine($prefix)` |

```php
use ByJG\Cache\Psr16\FileSystemCacheEngine;
use ByJG\Cache\Psr16\RedisCacheEngine;
use ByJG\Cache\Psr16\NoCacheEngine;

// Most common constructors:
new FileSystemCacheEngine('cache', '/tmp/myapp-cache');
new RedisCacheEngine('127.0.0.1:6379');
new RedisCacheEngine('127.0.0.1:6379', 'my-redis-password');
new MemcachedEngine(['127.0.0.1:11211']);
```

---

## PSR-16 interface

```php
$cache->get('key');                    // mixed — null if missing
$cache->get('key', 'default');         // returns 'default' if missing
$cache->set('key', $value, 3600);      // TTL in seconds
$cache->set('key', $value, new DateInterval('PT1H'));  // or DateInterval
$cache->set('key', $value, null);      // no expiration
$cache->has('key');                    // bool
$cache->delete('key');
$cache->clear();                       // wipe everything

// Batch
$cache->getMultiple(['a', 'b']);
$cache->setMultiple(['a' => 1, 'b' => 2], 3600);
$cache->deleteMultiple(['a', 'b']);

// Atomic (FileSystem, Redis, Memcached)
$cache->increment('hits');             // +1, returns new value
$cache->increment('hits', 5, 3600);    // +5 with TTL
$cache->decrement('quota', 1, 3600);
$cache->add('queue', 'item');          // append to list
```

---

## DI registration — environment-based swap

Wire the engine once per environment. Services only type-hint `BaseCacheEngine`:

```php
// config/dev/01-infrastructure.php
use ByJG\Cache\Psr16\BaseCacheEngine;
use ByJG\Cache\Psr16\NoCacheEngine;

BaseCacheEngine::class => DI::bind(NoCacheEngine::class)
    ->toSingleton(),
```

```php
// config/prod/01-infrastructure.php
use ByJG\Cache\Psr16\FileSystemCacheEngine;

BaseCacheEngine::class => DI::bind(FileSystemCacheEngine::class)
    ->withConstructorArgs(['myapp', '/var/cache/myapp'])
    ->toSingleton(),
```

```php
// config/test/01-infrastructure.php — ArrayCacheEngine resets between requests
use ByJG\Cache\Psr16\ArrayCacheEngine;

BaseCacheEngine::class => DI::bind(ArrayCacheEngine::class)
    ->toSingleton(),
```

### Injecting into a service

```php
use ByJG\Cache\Psr16\BaseCacheEngine;

class ProductService extends BaseService
{
    public function __construct(
        ProductRepository $repository,
        private readonly BaseCacheEngine $cache,   // injected from container
    ) {
        parent::__construct($repository);
    }

    public function getOrFailCached(int $id): Product
    {
        $key = "product:{$id}";
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            return $hit;
        }
        $product = $this->getOrFail($id);
        $this->cache->set($key, $product, 300);    // 5-minute TTL
        return $product;
    }
}
```

Register in DI with partial injection:
```php
// config/dev/05-services.php
ProductService::class => DI::bind(ProductService::class)
    ->withInjectedConstructor()    // BaseCacheEngine resolved automatically
    ->toSingleton(),
```

---

## Caching ORM query results

Pass a `CacheQueryResult` to `getByQuery()` or `getIterator()` to cache the result set:

```php
use ByJG\MicroOrm\CacheQueryResult;

$query = Query::getInstance()
    ->table('product')
    ->where('deleted_at IS NULL');

$products = $this->repository->getByQuery(
    $query,
    cache: new CacheQueryResult(
        cache:    $this->cache,      // any PSR-16 engine
        cacheKey: 'products:all',
        ttl:      300                // seconds, or DateInterval
    )
);
```

Cache is **opt-in and per-query** — the ORM never caches automatically. You own the cache key;
choose a key that encodes the query's parameters so different queries don't collide:

```php
new CacheQueryResult($this->cache, "product:name:{$name}", 60)
```

---

## Caching the OpenAPI route list

Parsing `openapi.json` on every request is wasteful in production. Cache it via
`withCache()` on `OpenApiRouteList`:

```php
// config/prod/03-api.php
OpenApiRouteList::class => DI::bind(OpenApiRouteList::class)
    ->withConstructorArgs([__DIR__ . '/../../public/docs/openapi.json'])
    ->withMethodCall('withCache', [Param::get(BaseCacheEngine::class)])
    ->toSingleton(),
```

In dev, skip `withCache()` so route changes are picked up immediately.

---

## Caching the DI config itself

For staging and prod, the resolved DI config can be cached to avoid re-processing config
files on every cold start. This is configured in `config/ConfigBootstrap.php`:

```php
->addEnvironment(
    (new Environment('prod'))
        ->inheritFrom(new Environment('staging'))
        ->withCache(new FileSystemCacheEngine('/tmp/config-cache'), 'config')
)
```

This is already set up in the project — don't cache in dev or test (it would hide config
changes from taking effect).

---

## Quick reference

| Goal | Code |
|---|---|
| Default dev (no-op) | `new NoCacheEngine()` |
| File-based | `new FileSystemCacheEngine('prefix', '/path')` |
| Redis | `new RedisCacheEngine('host:port', $password)` |
| Get with fallback | `$cache->get('key') ?? compute()` |
| Set with TTL | `$cache->set('key', $value, 300)` |
| Invalidate | `$cache->delete('key')` |
| Cache a query | `getByQuery($q, cache: new CacheQueryResult($cache, $key, $ttl))` |
| Register in DI | bind to `BaseCacheEngine::class` per environment |