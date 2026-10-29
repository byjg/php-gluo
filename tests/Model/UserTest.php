<?php

namespace Tests\Model;

use ByJG\Authenticate\Enum\UserField;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Config\Config;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RestReferenceArchitecture\Model\User;

class UserTest extends TestCase
{
    /**
     * The password rules of config/dev/02-security.php reach the users repository: a weak password
     * is rejected on save, also for a user created with new and saved without UsersService
     */
    public function testTheRepositoryChecksThePasswordRules(): void
    {
        $repository = Config::get(UsersRepository::class);
        $user = new User('Weak Password', 'weak-password@example.com', 'weak-password', 'weak');

        try {
            $repository->save($user);
            $this->fail('A password outside the rules was saved');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Password does not match the password definition', $e->getMessage());
        }
        $this->assertNull($repository->getByField(UserField::Email->value, 'weak-password@example.com'));
    }
}
