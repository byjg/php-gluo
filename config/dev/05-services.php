<?php

use ByJG\Config\DependencyInjection as DI;
use RestReferenceArchitecture\Service\ProjectService;
use RestReferenceArchitecture\Service\TaskService;

return [

    // Service Bindings
    ProjectService::class => DI::bind(ProjectService::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    TaskService::class => DI::bind(TaskService::class)
        ->withInjectedConstructor()
        ->toSingleton(),

];
