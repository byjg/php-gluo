<?php

namespace Test\Controller;

use ByJG\Authenticate\Enum\UserField;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Config\Config;
use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\JwtWrapper\JwtWrapper;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error422Exception;
use RestReferenceArchitecture\Model\User;

/**
 * Create a TestCase inherited from SwaggerTestCase
 */
class LoginTest extends BaseApiTestCase
{

    public function testLoginOk()
    {
        $this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()));
    }

    public function testLoginOk2()
    {
        $this->sendRequest(Credentials::requestLogin(Credentials::getRegularUser()));
    }

    public function testLoginFail()
    {
        $this->expectException(Error401Exception::class);
        $this->expectExceptionMessage('User not found');

        $this->sendRequest(Credentials::requestLogin([
            'username' => 'invalid',
            'password' => 'invalid'
        ]));
    }

    public function testRefreshTokenUnauthenticated(): void
    {
        $this->expectException(Error401Exception::class);

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath('/refreshtoken')
            ->expectStatus(401)
        ;
        $this->sendRequest($request);
    }

    public function testRefreshTokenTooEarlyToRefresh(): void
    {
        $this->expectException(Error401Exception::class);
        $this->expectExceptionMessage('You only can refresh the token 5 minutes before expire');

        // Login to get a fresh 3600s token — way more than 5 minutes remaining
        $loginResponse = $this->sendRequest(Credentials::requestLogin(Credentials::getAdminUser()));
        $token = json_decode($loginResponse->getBody()->getContents(), true)['token'];

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath('/refreshtoken')
            ->withRequestHeader(['Authorization' => 'Bearer ' . $token])
            ->expectStatus(401)
        ;
        $this->sendRequest($request);
    }

    public function testRefreshTokenOk(): void
    {
        // Create a near-expiry token (4 minutes) using the same codepath as login,
        // so the 5-minute refresh window is open.
        /** @var UsersService $usersService */
        $usersService = Config::get(UsersService::class);
        /** @var JwtWrapper $jwt */
        $jwt = Config::get(JwtWrapper::class);

        $userToken = $usersService->createInsecureAuthToken(
            login: Credentials::getAdminUser()['username'],
            jwtWrapper: $jwt,
            expires: 240,
            tokenUserFields: [
                UserField::Userid,
                UserField::Name,
                UserField::Role->value => User::ROLE_USER,
            ]
        );
        $shortToken = $userToken->token;

        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath('/refreshtoken')
            ->withRequestHeader(['Authorization' => 'Bearer ' . $shortToken])
            ->expectStatus(200)
        ;
        $response = $this->sendRequest($request);
        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
        $this->assertArrayHasKey('data', $data);
    }

    public function testResetRequestOk()
    {
        $email = Credentials::getRegularUser()["username"];

        // Clear the reset token
        /** @var UsersService $usersService */
        $usersService = Config::get(UsersService::class);
        $user = $usersService->getByEmail($email);
        $user->set(User::PROP_RESETTOKEN, null);
        $user->set(User::PROP_RESETTOKENEXPIRE, null);
        $user->set(User::PROP_RESETCODE, null);
        $user->set(User::PROP_RESETALLOWED, null);
        $usersService->save($user);

        // Check if the reset token was cleared
        $user = $usersService->getByEmail($email);
        $this->assertNotNull($user);
        $this->assertEmpty($user->get(User::PROP_RESETTOKEN));
        $this->assertEmpty($user->get(User::PROP_RESETTOKENEXPIRE));
        $this->assertEmpty($user->get(User::PROP_RESETCODE));
        $this->assertEmpty($user->get(User::PROP_RESETALLOWED));
        
        // Execute the request
        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath("/login/resetrequest")
            ->withRequestBody(json_encode(["email" => $email]))
            ->expectStatus(200)
        ;
        $this->sendRequest($request);

        // Check if the reset token was created
        /** @var UsersService $usersService */
        $usersService = Config::get(UsersService::class);
        $user = $usersService->getByEmail($email);
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKEN));
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKENEXPIRE));
        $this->assertNotEmpty($user->get(User::PROP_RESETCODE));
        $this->assertEmpty($user->get(User::PROP_RESETALLOWED));
    }

    public function testConfirmCodeFail()
    {
        $email = Credentials::getRegularUser()["username"];

        // Clear the reset token
        $usersService = Config::get(UsersService::class);
        $user = $usersService->getByEmail($email);
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKEN));
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKENEXPIRE));
        $this->assertNotEmpty($user->get(User::PROP_RESETCODE));
        $this->assertEmpty($user->get(User::PROP_RESETALLOWED));
        
        $this->expectException(Error422Exception::class);

        // Execute the request, expecting an error
        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath("/login/confirmcode")
            ->withRequestBody(json_encode((["email" => $email, "code" => "123456", "token" => $user->get(User::PROP_RESETTOKEN)])))
            ->expectStatus(422)
        ;
        $this->sendRequest($request);
    }

    public function testConfirmCodeOk()
    {
        $email = Credentials::getRegularUser()["username"];

        // Clear the reset token
        $usersService = Config::get(UsersService::class);
        $user = $usersService->getByEmail($email);
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKEN));
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKENEXPIRE));
        $this->assertNotEmpty($user->get(User::PROP_RESETCODE));
        $this->assertEmpty($user->get(User::PROP_RESETALLOWED));
        
        // Execute the request, now with the correct code
        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath("/login/confirmcode")
            ->withRequestBody(json_encode((["email" => $email, "code" => $user->get(User::PROP_RESETCODE), "token" => $user->get(User::PROP_RESETTOKEN)])))
            ->expectStatus(200)
        ;
        $this->sendRequest($request);

        // Check if the reset token was created
        $usersService = Config::get(UsersService::class);
        $user = $usersService->getByEmail($email);
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKEN));
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKENEXPIRE));
        $this->assertNotEmpty($user->get(User::PROP_RESETCODE));
        $this->assertEquals(User::VALUE_YES, $user->get(User::PROP_RESETALLOWED));
    }

    public function testPasswordResetOk()
    {
        $email = Credentials::getRegularUser()["username"];
        $password = Credentials::getRegularUser()["password"];

        // Clear the reset token
        $usersService = Config::get(UsersService::class);
        $user = $usersService->getByEmail($email);
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKEN));
        $this->assertNotEmpty($user->get(User::PROP_RESETTOKENEXPIRE));
        $this->assertNotEmpty($user->get(User::PROP_RESETCODE));
        $this->assertEquals(User::VALUE_YES, $user->get(User::PROP_RESETALLOWED));
        
        // Execute the request, now with the correct code
        $request = new FakeApiRequester();
        $request
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath("/login/resetpassword")
            ->withRequestBody(json_encode([
                "email" => $email,
                "token" => $user->get(User::PROP_RESETTOKEN),
                "password" => "new$password"
            ]))
            ->expectStatus(200)
        ;
        $this->sendRequest($request);

        // Check if the reset token was created
        $usersService = Config::get(UsersService::class);
        $user = $usersService->getByEmail($email);
        $this->assertNotNull($user);
        $this->assertEquals("83bfd34a3ebc0973609f5f2ec0080080286e3879", $user->getPassword());
        $this->assertEmpty($user->get(User::PROP_RESETTOKEN));
        $this->assertEmpty($user->get(User::PROP_RESETTOKENEXPIRE));
        $this->assertEmpty($user->get(User::PROP_RESETCODE));
        $this->assertEmpty($user->get(User::PROP_RESETALLOWED));

        // Restore old password
        $user->setPassword($password);
        $usersService->save($user);
    }
}
