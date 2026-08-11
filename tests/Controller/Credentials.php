<?php

namespace Test\Controller;

use ByJG\Config\Config;
use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\Util\Uri;
use ByJG\WebRequest\Psr7\Request;

class Credentials
{
    public static function getAdminUser(): array
    {
        return [
            'username' => (getenv('TEST_ADMIN_USER') ? getenv('TEST_ADMIN_USER') : 'admin@example.com'),
            'password' => (getenv('TEST_ADMIN_PASSWORD') ? getenv('TEST_ADMIN_PASSWORD') : '!P4ssw0rdstr!'),
        ];
    }

    public static function getRegularUser(): array
    {
        return [
            'username' => (getenv('TEST_REGULAR_USER') ? getenv('TEST_REGULAR_USER') : 'user@example.com'),
            'password' => (getenv('TEST_REGULAR_PASSWORD') ? getenv('TEST_REGULAR_PASSWORD') : '!P4ssw0rdstr!'),
        ];
    }

    public static function requestLogin($cred): FakeApiRequester
    {
        $uri = Uri::getInstance()
            ->withScheme(Config::get("API_SCHEMA"))
            ->withHost(Config::get("API_SERVER"));

        $psr7Request = Request::getInstance($uri);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($psr7Request)
            ->withMethod('POST')
            ->withPath("/login")
            ->expectStatus(200)
            ->withRequestBody($cred)
        ;
        return $request;
    }
}
