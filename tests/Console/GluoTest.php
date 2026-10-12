<?php

namespace Tests\Console;

use Builder\Scripts;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Gluo\Console\Launcher;
use Tests\Controller\BaseApiTestCase;

/**
 * The ./gluo wiring this project owns: config/scriptify-bootstrap.php, config/commands.php
 * and Builder\Scripts, against the real container and the migrated test database.
 * Extends BaseApiTestCase to reuse the database reset and environment guard.
 */
class GluoTest extends BaseApiTestCase
{
    protected const ROOT = __DIR__ . '/../..';

    /**
     * @param array<string, string>|null $commands Replaces config/commands.php when given.
     * @return array{0: int, 1: string}
     */
    protected function gluo(array $arguments, ?array $commands = null): array
    {
        $launcher = new class (self::ROOT, $commands) extends Launcher {
            public function __construct(string $rootDir, private ?array $commands)
            {
                parent::__construct($rootDir);
            }

            #[\Override]
            public function getProjectCommands(): array
            {
                return $this->commands ?? parent::getProjectCommands();
            }
        };

        ob_start();
        try {
            $exitCode = $launcher->run(['./gluo', ...$arguments]);
        } finally {
            $output = (string) ob_get_clean();
        }

        return [$exitCode, $output];
    }

    public function testEveryShippedCommandPassesTheCheck(): void
    {
        [$exitCode, $output] = $this->gluo(['list', '--check']);

        $this->assertStringStartsWith("Environment: test\n", $output);
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('migrate:update         OK', $output);
        $this->assertStringContainsString('codegen:all            OK', $output);
    }

    public function testShippedRegistryIsAnArrayOfTargets(): void
    {
        $commands = require self::ROOT . '/config/commands.php';

        $this->assertIsArray($commands);
        foreach ($commands as $name => $target) {
            $this->assertTrue(Launcher::isValidName((string) $name), "Invalid command name: $name");
            $this->assertFalse(Launcher::isBuiltIn((string) $name), "Reserved command name: $name");
            $this->assertStringContainsString('::', $target);
        }
    }

    public function testBootstrapReturnsTheContainerThatBuildsRegisteredServices(): void
    {
        $commands = [
            'user:find' => 'ByJG/Authenticate/Service/UsersService::getByEmail',
            'not:registered' => 'Builder/Scripts::runCheckOpenApi',
        ];

        // A class registered in config/dev/02-security.php passes; one that is not is reported.
        [$exitCode, $output] = $this->gluo(['list', '--check'], $commands);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('user:find              OK', $output);
        $this->assertStringContainsString('not:registered         NOT_IN_CONTAINER', $output);

        // --help builds the service through the container: UsersService cannot be built with new.
        [$exitCode, $output] = $this->gluo(['user:find', '--help'], $commands);
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString("./gluo user:find <email>", $output);
    }

    public function testBuiltInsRunThroughTheProjectScripts(): void
    {
        $launcher = new Launcher(self::ROOT);

        $this->assertInstanceOf(Scripts::class, $launcher->getScripts());
        $this->assertTrue($launcher->getContainer()->has(UsersService::class));
    }

    public function testMigrateVersionAgainstTheTestDatabase(): void
    {
        [$exitCode, $output] = $this->gluo(['migrate:version']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('> Environment: test', $output);
        $this->assertMatchesRegularExpression('/Current version: \d+\nStatus: complete/', $output);
    }
}
