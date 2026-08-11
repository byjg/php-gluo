<?php

namespace Test\Controller;

use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error403Exception;

/**
 * Create a TestCase inherited from SwaggerTestCase
 */
class SampleProtectedTest extends BaseApiTestCase
{

    public function testGetUnauthorized()
    {
        $this->expectException(Error401Exception::class);
        $this->expectExceptionMessage('Absent authorization token');

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/sampleprotected/ping")
            ->expectStatus(401)
        ;
        $this->sendRequest($request);
    }

    public function testGetAuthorized()
    {
        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/sampleprotected/ping")
            ->expectStatus(200)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $this->sendRequest($request);
    }

    public function testGetAuthorizedRole1()
    {
        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/sampleprotected/pingadm")
            ->expectStatus(200)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $this->sendRequest($request);
    }

    public function testGetAuthorizedRole2()
    {
        $this->expectException(Error403Exception::class);
        $this->expectExceptionMessage('Insufficient privileges');

        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/sampleprotected/pingadm")
            ->expectStatus(401)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $this->sendRequest($request);
    }
}
