<?php

namespace Tests\Controller;

use ByJG\Gluo\Repository\BaseRepository;
use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error400Exception;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error403Exception;
use ByJG\Serializer\ObjectCopy;
use RestReferenceArchitecture\Model\Task;

class TaskTest extends BaseApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return Task|array
     */
    protected function getSampleData($array = false)
    {
        $sample = [

            'projectId' => 1,
            'title' => 'title',
            'status' => 'status',
        ];

        if ($array) {
            return $sample;
        }

        $model = new Task();
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
            ->withPath("/task/" . BaseRepository::getUuid())
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
            ->withPath("/task/" . BaseRepository::getUuid())
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
            ->withPath("/task")
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
            ->withPath("/task/" . BaseRepository::getUuid())
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
            ->withPath("/task")
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
            ->withPath("/task/" . BaseRepository::getUuid())
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
            ->withPath("/task")
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
            ->withPath("/task/" . $bodyAr['id'])
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
            ->withPath("/task/" . $bodyAr['id'])
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
        $collection = "/task";
        $item = "/task/" . BaseRepository::getUuid();

        $invalid = [
            // The key is generated (POST) or taken from the path (PUT): the body cannot set it
            'POST with the key in the body' => ['POST', $collection, $sample + ['id' => BaseRepository::getUuid()]],
            'PUT with the key in the body' => ['PUT', $item, $sample + ['id' => BaseRepository::getUuid()]],
            'POST without the required fields' => ['POST', $collection, (object)[]],
            'PUT without the required fields' => ['PUT', $item, (object)[]],
            'POST with an empty title' => ['POST', $collection, array_merge($sample, ['title' => ''])],
            'PUT with an empty title' => ['PUT', $item, array_merge($sample, ['title' => ''])],
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

    public function testListTasksByProject()
    {
        $token = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true)['token'];

        $list = function (int $projectId) use ($token): array {
            $body = $this->sendRequest(
                (new FakeApiRequester())
                    ->withPsr7Request($this->getPsr7Request())
                    ->withMethod('GET')
                    ->withPath("/project/$projectId/task")
                    ->withRequestHeader(['Authorization' => "Bearer $token"])
                    ->expectStatus(200)
            );
            return json_decode($body->getBody()->getContents(), true);
        };

        // The two seed tasks belong to project 1, and the database does the filtering.
        $tasks = $list(1);
        $this->assertContains('Set up repository', array_column($tasks, 'title'));
        $this->assertContains('Write first endpoint', array_column($tasks, 'title'));
        $this->assertSame([1], array_values(array_unique(array_column($tasks, 'projectId'))));

        // Another project never sees them.
        $this->assertNotContains('Set up repository', array_column($list(2), 'title'));
    }

    public function testList()
    {
        $result = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath("/task")
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
                ->withPath("/task")
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
}
