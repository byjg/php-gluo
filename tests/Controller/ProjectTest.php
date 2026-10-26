<?php

namespace Tests\Controller;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Config\Config;
use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error400Exception;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error403Exception;
use ByJG\RestServer\Exception\Error404Exception;
use ByJG\Serializer\ObjectCopy;
use RestReferenceArchitecture\Model\Project;
use RestReferenceArchitecture\Service\ProjectService;

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
            ->withPath("/project/1")
            ->withRequestBody(json_encode($this->getSampleData(true)))
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
            ->withPath("/project/1")
            ->withRequestBody(json_encode($this->getSampleData(true)))
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
        $this->sendRequest($request);

        // The key is in the path; the body has the same fields as the POST
        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('PUT')
            ->withPath("/project/" . $bodyAr['id'])
            ->withRequestBody(json_encode($this->getSampleData(true)))
            ->expectStatus(200)
            ->withRequestHeader([
                "Authorization" => "Bearer " . $result['token']
            ])
        ;
        $this->sendRequest($request);
    }

    /**
     * The server refuses an invalid body with 400, not only the test client: these requests
     * are sent without the OpenAPI schema, so the client does not stop them and they reach
     * #[ValidateRequest] in the controller.
     */
    public function testInvalidPayloadIsRejectedByTheServer()
    {
        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))->getBody()->getContents(), true);
        $sample = $this->getSampleData(true);
        $collection = "/project";
        $item = "/project/1";

        $invalid = [
            // The key is generated (POST) or taken from the path (PUT): the body cannot set it
            'POST with the key in the body' => ['POST', $collection, $sample + ['id' => 1]],
            'PUT with the key in the body' => ['PUT', $item, $sample + ['id' => 1]],
            'POST without the required fields' => ['POST', $collection, (object)[]],
            'PUT without the required fields' => ['PUT', $item, (object)[]],
            'POST with an empty name' => ['POST', $collection, array_merge($sample, ['name' => ''])],
            'PUT with an empty name' => ['PUT', $item, array_merge($sample, ['name' => ''])],
        ];

        foreach ($invalid as $case => [$method, $path, $payload]) {
            $request = new FakeApiRequester();
            $request
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod($method)
                ->withPath($path)
                ->withRequestBody(json_encode($payload))
                ->withRequestHeader([
                    "Authorization" => "Bearer " . $result['token']
                ])
            ;

            try {
                $request->send();
                $this->fail("$case was accepted");
            } catch (Error400Exception) {
                $this->addToAssertionCount(1);
            }
        }
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

    public function testListOrderBy()
    {
        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true);

        $list = function (string $orderBy) use ($result) {
            $request = new FakeApiRequester();
            $request
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('GET')
                ->withPath("/project")
                ->withQuery(['orderBy' => $orderBy])
                ->expectStatus(200)
                ->withRequestHeader([
                    "Authorization" => "Bearer " . $result['token']
                ])
            ;
            return json_decode($this->sendRequest($request)->getBody()->getContents(), true);
        };

        $descending = array_column($list('id desc'), 'id');
        $expected = $descending;
        rsort($expected);
        $this->assertSame($expected, $descending);

        // A field the model does not have is refused, not passed to the database.
        $this->expectException(Error400Exception::class);
        $this->expectExceptionMessage("unknown field 'notAField'");
        $list('notAField');
    }

    /**
     * The model is validated against its own OpenAPI schema on every save, not only through the
     * API: a service called from a command or a queue consumer is held to the same rules.
     */
    public function testAnInvalidProjectIsRejectedWhenSavedOutsideTheApi()
    {
        $executor = Config::get(DatabaseExecutor::class);
        $count = $executor->getScalar('select count(*) from project');

        try {
            Config::get(ProjectService::class)->create(['name' => '']);
            $this->fail('The empty name was saved');
        } catch (Error400Exception $ex) {
            $this->assertStringContainsString('is shorter than minLength 1', $ex->getMessage());
        }

        $this->assertEquals($count, $executor->getScalar('select count(*) from project'));
    }

    /**
     * A missing record answers 404 named after the model: BaseService::getOrFail() says
     * "Project not found" without the controller passing a message.
     */
    public function testAMissingProjectIs404()
    {
        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true);

        $this->expectException(Error404Exception::class);
        $this->expectExceptionMessage('Project not found');
        $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('GET')
                ->withPath('/project/999999')
                ->withRequestHeader(['Authorization' => "Bearer " . $result['token']])
                ->expectStatus(404)
        );
    }
}
