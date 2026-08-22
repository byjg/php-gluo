<?php

use ByJG\Config\DependencyInjection as DI;
// Start Example
use RestReferenceArchitecture\Repository\ProjectRepository;
use RestReferenceArchitecture\Repository\TaskRepository;
// End Example

return [

    // Repository Bindings
    // Start Example
    ProjectRepository::class => DI::bind(ProjectRepository::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    TaskRepository::class => DI::bind(TaskRepository::class)
        ->withInjectedConstructor()
        ->toSingleton(),
    // End Example

];
