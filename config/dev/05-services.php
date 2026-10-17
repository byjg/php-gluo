<?php

use ByJG\Config\DependencyInjection as DI;
// Start Template
use Builder\DbDialect;
// End Template
// Start Example
use RestReferenceArchitecture\Service\ProjectService;
use RestReferenceArchitecture\Service\TaskService;
// End Example

return [

    // Service Bindings
    // Start Template
    DbDialect::class => DI::bind(DbDialect::class)
        ->withConstructorNoArgs()
        ->toSingleton(),
    // End Template

    // Start Example
    ProjectService::class => DI::bind(ProjectService::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    TaskService::class => DI::bind(TaskService::class)
        ->withInjectedConstructor()
        ->toSingleton(),
    // End Example

];
