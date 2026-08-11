<?php

namespace Test\Controller;

use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error403Exception;
use ByJG\Serializer\ObjectCopy;
use RestReferenceArchitecture\Model\Project;

class ProjectTest extends BaseApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return Project|array
     */
    protected function getSampleData($array = false)
    {
        $sample = [

            'name' => 'name',
            'description' => 'description',
        ];

        if ($array) {
            return $sample;
        }

        $model = new Project();
        ObjectCopy::copy($sample, $model);
        return $model;
    }



    public function testGetUnauthorized()
    {
        $this->expectException(Error401Exception::class);
        $this->expectExceptionMessage('Absent authorization token');

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/project/1")
            ->expectStatus(401)
        ;
        $this->sendRequest($request);
    }

    public function testListUnauthorized()
    {
        $this->expectException(Error401Exception::class);
        $this->expectExceptionMessage('Absent authorization token');

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/project/1")
            ->expectStatus(401)
        ;
        $this->sendRequest($request);
    }

    public function testPostUnauthorized()
    {
        $this->expectException(Error401Exception::class);
        $this->expectExceptionMessage('Absent authorization token');

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath("/project")
            ->withRequestBody(json_encode($this->getSampleData(true)))
            ->expectStatus(401)
        ;
        $this->sendRequest($request);
    }

    public function testPutUnauthorized()
    {
        $this->expectException(Error401Exception::class);
        $this->expectExceptionMessage('Absent authorization token');

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('PUT')
            ->withPath("/project")
            ->withRequestBody(json_encode($this->getSampleData(true) + ['id' => 1]))
            ->expectStatus(401)
        ;
        $this->sendRequest($request);
    }

    public function testPostInsufficientPrivileges()
    {
        $this->expectException(Error403Exception::class);
        $this->expectExceptionMessage('Insufficient privileges');

        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath("/project")
            ->withRequestBody(json_encode($this->getSampleData(true)))
            ->expectStatus(403)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $this->sendRequest($request);
    }

    public function testPutInsufficientPrivileges()
    {
        $this->expectException(Error403Exception::class);
        $this->expectExceptionMessage('Insufficient privileges');

        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('PUT')
            ->withPath("/project")
            ->withRequestBody(json_encode($this->getSampleData(true) + ['id' => 1]))
            ->expectStatus(403)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $this->sendRequest($request);
    }

    public function testFullCrud()
    {
        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath("/project")
            ->withRequestBody(json_encode($this->getSampleData(true)))
            ->expectStatus(200)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $body = $this->sendRequest($request);
        $bodyAr = json_decode($body->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/project/" . $bodyAr['id'])
            ->expectStatus(200)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $body = $this->sendRequest($request);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('PUT')
            ->withPath("/project")
            ->withRequestBody($body->getBody()->getContents())
            ->expectStatus(200)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $this->sendRequest($request);
    }

    public function testList()
    {
        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/project")
            ->expectStatus(200)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $this->sendRequest($request);
    }
}
