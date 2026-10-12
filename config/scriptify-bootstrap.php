<?php

// Bootstrap for Scriptify: loads the autoloader and returns the Gluo PSR-11 container.
// ./gluo and direct Scriptify calls (`scriptify run|install --bootstrap config/scriptify-bootstrap.php`)
// use this same file, so a command builds its class identically in every case.
//
// It returns the container the Config facade already uses. Do not build a second one:
// services that call Config::get() internally would resolve from a different container
// than the one that built them, duplicating singletons.

require_once __DIR__ . '/../vendor/autoload.php';

return \ByJG\Config\Config::getContainer();
