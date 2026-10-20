<?php

use ByJG\Cache\Psr16\FileSystemCacheEngine;
use ByJG\Config\Definition;
use ByJG\Gluo\Config\BaseConfigBootstrap;

// Check PHP version requirement
if (PHP_INT_SIZE < 8) {
    throw new Exception("This application requires 64-bit PHP");
}

// Environments (dev, test, staging, prod) and their inheritance are defined in
// BaseConfigBootstrap (byjg/gluo). Override configureDefinition() here to add config
// directories, OS environment variables, environments or the config cache
// (see docs/guides/configuration.md, "Environment-Specific Caching").
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
