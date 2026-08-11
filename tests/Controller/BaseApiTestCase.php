<?php

namespace Test\Controller;

use ByJG\Gluo\Testing\BaseApiTestCase as GluoBaseApiTestCase;

class BaseApiTestCase extends GluoBaseApiTestCase
{
    protected function getOpenApiPath(): string
    {
        return __DIR__ . '/../../public/docs/openapi.json';
    }
}
