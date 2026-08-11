<?php

use ByJG\Cache\Psr16\BaseCacheEngine;
use ByJG\Cache\Psr16\FileSystemCacheEngine;
use ByJG\Config\DependencyInjection as DI;

// use ByJG\Cache\Psr16\RedisCacheEngine;
// use ByJG\Config\Param;

return [

    // Override: Enable FileSystemCache for production
    // TODO: Change to Redis for production when available
    BaseCacheEngine::class => DI::bind(FileSystemCacheEngine::class)
        ->toSingleton(),

    // Example: Redis Cache (uncomment when ready)
    // BaseCacheEngine::class => DI::bind(RedisCacheEngine::class)
    //     ->withConstructorArgs([Param::get('REDIS_CONNECTION')])
    //     ->toSingleton(),

];
