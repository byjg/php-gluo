<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ByJG\Config\Config;
use ByJG\Gluo\Http\Application;

// The Application, the Server and the routes are in the configuration (config/<env>/03-api.php).
// A persistent worker gets the Application once and calls handle($psr7Request) for each request.
Config::get(Application::class)->handle();
