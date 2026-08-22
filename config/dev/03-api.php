<?php

use ByJG\ApiTools\Base\Schema;
use ByJG\Cache\Psr16\BaseCacheEngine;
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\JwtWrapper\JwtWrapper;
use ByJG\RestServer\Middleware\CorsMiddleware;
use ByJG\RestServer\Middleware\JwtMiddleware;
use ByJG\RestServer\OutputProcessor\JsonCleanOutputProcessor;
use ByJG\RestServer\Route\OpenApiRouteList;
use ByJG\RestServer\Server;
use Psr\Log\LoggerInterface;

return [

    // OpenAPI Configuration
    OpenAPiRouteList::class => DI::bind(OpenAPiRouteList::class)
        ->withConstructorArgs([
            __DIR__ . '/../../public/docs/openapi.json'
        ])
        ->withMethodCall("withDefaultProcessor", [JsonCleanOutputProcessor::class])
        ->withMethodCall("withCache", [Param::get(BaseCacheEngine::class)])
        ->toSingleton(),

    Schema::class => DI::bind(Schema::class)
        ->withFactoryMethod('getInstance', [file_get_contents(__DIR__ . '/../../public/docs/openapi.json')])
        ->toSingleton(),

    // Middleware Configuration
    JwtMiddleware::class => DI::bind(JwtMiddleware::class)
        ->withConstructorArgs([
            Param::get(JwtWrapper::class)
        ])
        ->toSingleton(),

    CorsMiddleware::class => DI::bind(CorsMiddleware::class)
        ->withNoConstructor()
        ->withMethodCall("withCorsOrigins", [Param::get("CORS_SERVER_LIST")])  // Required to enable CORS
        // ->withMethodCall("withAcceptCorsMethods", [[/* list of methods */]])     // Optional. Default all methods. Don't need to pass 'OPTIONS'
        // ->withMethodCall("withAcceptCorsHeaders", [[/* list of headers */]])     // Optional. Default all headers
        ->toSingleton(),

    // HTTP Request Handler
    Server::class => DI::bind(Server::class)
        ->withConstructorArgs([
            Param::get(LoggerInterface::class)
        ])
        ->withMethodCall("withMiddleware", [Param::get(JwtMiddleware::class)])
        ->withMethodCall("withMiddleware", [Param::get(CorsMiddleware::class)])
        // Resolve route controllers from the container, so they can declare their
        // dependencies in the constructor. Controllers are registered in 07-controllers.php.
        // Param::container() resolves to the container itself; do not use
        // Config::getContainer() here, as it is not yet available while this file is read.
        ->withMethodCall("withContainer", [Param::container()])
//        ->withMethodCall("withDetailedErrorHandler", [])
        ->toSingleton(),

];
