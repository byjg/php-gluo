<?php

use ByJG\Config\DependencyInjection as DI;
use RestReferenceArchitecture\Repository\ProjectRepository;
use RestReferenceArchitecture\Repository\TaskRepository;

return [

    // Repository Bindings
    ProjectRepository::class => DI::bind(ProjectRepository::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    TaskRepository::class => DI::bind(TaskRepository::class)
        ->withInjectedConstructor()
        ->toSingleton(),

];
