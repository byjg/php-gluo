<?php

namespace Test\Controller;

use ByJG\ApiTools\Exception\NotMatchedException;
use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error401Exception;

class ProfileTest extends BaseApiTestCase
{
    public function testGetUnauthorized(): void
    {
        $this->expectException(Error401Exception::class);
        $this->expectExceptionMessage('Absent authorization token');

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath('/profile')
            ->expectStatus(401);
        $this->sendRequest($request);
    }

    public function testGetAndUpdateProfile(): void
    {
        $login = json_decode(
            $this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))->getBody()->getContents(),
            true
        );
        $token = $login['token'];

        // GET /profile
        $body = $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('GET')
                ->withPath('/profile')
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(200)
        );
        $profile = json_decode($body->getBody()->getContents(), true);
        $this->assertNotEmpty($profile['userid']);
        $this->assertNotEmpty($profile['email']);

        // PUT /profile with a language property (stored in users_property)
        $body = $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('PUT')
                ->withPath('/profile')
                ->withRequestBody(json_encode([
                    'name' => 'Admin Updated',
                    'email' => $profile['email'],
                    'language' => 'fr',
                ]))
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(200)
        );
        $updated = json_decode($body->getBody()->getContents(), true);
        $this->assertSame('Admin Updated', $updated['name']);
        $this->assertSame('fr', $updated['language']);

        // GET /profile again — the language property persisted to the properties table
        $body = $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('GET')
                ->withPath('/profile')
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(200)
        );
        $this->assertSame('fr', json_decode($body->getBody()->getContents(), true)['language']);
    }

    public function testInvalidLanguageIsRejected(): void
    {
        $login = json_decode(
            $this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()))->getBody()->getContents(),
            true
        );
        $token = $login['token'];

        // 'language' is constrained to the en/fr/pt enum in the OpenAPI contract,
        // so a value outside it is rejected before it can be persisted.
        $this->expectException(NotMatchedException::class);
        $this->sendRequest(
            (new FakeApiRequester())
                ->withPsr7Request($this->getPsr7Request())
                ->withMethod('PUT')
                ->withPath('/profile')
                ->withRequestBody(json_encode([
                    'name' => 'Admin',
                    'email' => 'admin@example.com',
                    'language' => 'de',
                ]))
                ->withRequestHeader(['Authorization' => "Bearer $token"])
                ->expectStatus(400)
        );
    }
}
