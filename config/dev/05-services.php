<?php

use ByJG\Config\DependencyInjection as DI;
// Start Example
use RestReferenceArchitecture\Service\ProjectService;
use RestReferenceArchitecture\Service\TaskService;
// End Example

return [

    // Service Bindings
    // Start Example
    ProjectService::class => DI::bind(ProjectService::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    TaskService::class => DI::bind(TaskService::class)
        ->withInjectedConstructor()
        ->toSingleton(),
    // End Example

];
