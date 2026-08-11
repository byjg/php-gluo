<?php

use ByJG\Cache\Psr16\BaseCacheEngine;
use ByJG\Cache\Psr16\FileSystemCacheEngine;
use ByJG\Config\DependencyInjection as DI;

return [

    // Override: Enable FileSystemCache for staging
    BaseCacheEngine::class => DI::bind(FileSystemCacheEngine::class)
        ->toSingleton(),

];
