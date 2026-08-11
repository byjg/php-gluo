<?php

use ByJG\Gluo\Config\BaseConfigBootstrap;

// Check PHP version requirement
if (PHP_INT_SIZE < 8) {
    throw new Exception("This application requires 64-bit PHP");
}

// Environments (dev, test, staging, prod), inheritance and caching are
// defined in BaseConfigBootstrap (byjg/gluo). Override configureDefinition()
// here to add config directories, OS environment variables or environments.
return new class extends BaseConfigBootstrap {
};
