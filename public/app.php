<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ByJG\Config\Config;
use ByJG\RestServer\Route\OpenApiRouteList;
use ByJG\RestServer\Server;

class App
{
    public static function run()
    {
        $server = Config::get(Server::class);
        $server->handle(Config::get(OpenApiRouteList::class));
    }
}

App::run();
