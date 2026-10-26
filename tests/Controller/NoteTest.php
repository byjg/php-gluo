<?php

namespace Tests\Controller;

use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error400Exception;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error403Exception;
use ByJG\RestServer\Exception\Error404Exception;
use ByJG\Serializer\ObjectCopy;
use RestReferenceArchitecture\Model\Note;

class NoteTest extends BaseApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return Note|array
     */
    /** The fixed-UUID task seeded by the example migration. */
    private const SEED_TASK_UUID = '11111111-2222-3333-4444-555555555555';

    protected function getSampleData($array = false)
    {
        $sample = [

            'taskId' => self::SEED_TASK_UUID,
            'body' => 'body',
        ];

        if ($array) {
            return $sample;
        }

        $model = new Note();
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
            ->withPath("/note/1")
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
            ->withPath("/note/1")
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
            ->withPath("/note")
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
            ->withPath("/note/1")
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
            ->withPath("/note")
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
            ->withPath("/note/1")
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
            ->withPath("/note")
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
            ->withPath("/note/" . $bodyAr['id'])
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
            ->withPath("/note/" . $bodyAr['id'])
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
        $collection = "/note";
        $item = "/note/1";

        $invalid = [
            // The key is generated (POST) or taken from the path (PUT): the body cannot set it
            'POST with the key in the body' => ['POST', $collection, $sample + ['id' => 1]],
            'PUT with the key in the body' => ['PUT', $item, $sample + ['id' => 1]],
            'POST without the required fields' => ['POST', $collection, (object)[]],
            'PUT without the required fields' => ['PUT', $item, (object)[]],
            'POST with an empty body' => ['POST', $collection, array_merge($sample, ['body' => ''])],
            'PUT with an empty body' => ['PUT', $item, array_merge($sample, ['body' => ''])],
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
            ->withPath("/note")
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
                ->withPath("/note")
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

    public function testListNotesByProject()
    {
        $token = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()))->getBody()->getContents(), true)['token'];

        // The seed note is attached to a task of project 1 (note -> task -> project).
        $body = $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('GET')
                ->withPath('/project/1/note')
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(200)
        );
        $notes = json_decode($body->getBody()->getContents(), true);
        $this->assertNotEmpty($notes);
        $this->assertSame(self::SEED_TASK_UUID, strtolower($notes[0]['taskId']));
    }

    public function testGetReturnsComputedDaysField()
    {
        $token = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))->getBody()->getContents(), true)['token'];

        // The seed note (id 1) was created "today", so days should be >= 0.
        $body = $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('GET')
                ->withPath('/note/1')
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(200)
        );
        $note = json_decode($body->getBody()->getContents(), true);
        $this->assertArrayHasKey('days', $note);
        $this->assertIsInt($note['days']);
        $this->assertGreaterThanOrEqual(0, $note['days']);

        // body_length is a real DB VIRTUAL GENERATED column (char_length(body)).
        $this->assertArrayHasKey('bodyLength', $note);
        $this->assertSame(mb_strlen($note['body']), $note['bodyLength']);
    }

    public function testSoftDelete()
    {
        $token = json_decode($this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))->getBody()->getContents(), true)['token'];

        // Create a note.
        $created = json_decode($this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('POST')
                ->withPath('/note')
                ->withRequestBody(json_encode($this->getSampleData(true)))
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(200)
        )->getBody()->getContents(), true);
        $id = $created['id'];

        // Delete it (soft delete via the OaDeletedAt trait).
        $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('DELETE')
                ->withPath("/note/$id")
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(200)
        );

        // It is now hidden from the API (get returns 404, named after the model by getOrFail())...
        $this->expectException(Error404Exception::class);
        $this->expectExceptionMessage('Note not found');
        $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('GET')
                ->withPath("/note/$id")
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(404)
        );
    }

    /**
     * The ActiveRecord save() is validated too, on insert and on update.
     */
    public function testAnInvalidNoteIsRejectedWhenSavedOutsideTheApi()
    {
        try {
            Note::new(['taskId' => self::SEED_TASK_UUID, 'body' => str_repeat('a', 501)])->save();
            $this->fail('The long body was saved');
        } catch (Error400Exception $ex) {
            $this->assertStringContainsString('is longer than maxLength 500', $ex->getMessage());
        }

        $note = Note::new(['taskId' => self::SEED_TASK_UUID, 'body' => 'First version']);
        $note->save();

        $note->setBody('');
        try {
            $note->save();
            $this->fail('The empty body was saved');
        } catch (Error400Exception $ex) {
            $this->assertStringContainsString('is shorter than minLength 1', $ex->getMessage());
        }

        $this->assertSame('First version', Note::get($note->getId())->getBody());
    }
}
