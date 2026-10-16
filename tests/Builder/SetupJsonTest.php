<?php

namespace Tests\Builder;

use Builder\PostCreateScript;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Where the unattended setup reads its answers from, and what happens when it cannot.
 */
class SetupJsonTest extends TestCase
{
    protected string $dir;
    protected string|false $setupJson;

    protected function setUp(): void
    {
        $this->setupJson = getenv('SETUP_JSON');
        $this->dir = sys_get_temp_dir() . '/setup-json-' . uniqid();
        mkdir($this->dir . '/parent/project', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->setupJson === false ? putenv('SETUP_JSON') : putenv('SETUP_JSON=' . $this->setupJson);
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
    }

    protected function load(): ?array
    {
        $script = new class extends PostCreateScript {
            public static function load(string $workdir): ?array
            {
                return self::loadConfigFromJson($workdir);
            }
        };

        return $script::load($this->dir . '/parent/project');
    }

    public function testReadsTheFileNamedBySetupJson(): void
    {
        file_put_contents($this->dir . '/answers.json', '{"namespace": "AcmeShop"}');
        putenv('SETUP_JSON=' . $this->dir . '/answers.json');

        $this->assertSame(['namespace' => 'AcmeShop'], $this->load());
    }

    public function testASetupJsonFileThatIsNotThereIsAnError(): void
    {
        // A Composer running in a container does not see a file outside its mounts: it used to
        // fall back to the questions, and create the project with the defaults
        putenv('SETUP_JSON=' . $this->dir . '/missing.json');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("SETUP_JSON is '{$this->dir}/missing.json', but that file cannot be read here");
        $this->load();
    }

    public function testASetupJsonThatIsNotJsonIsAnError(): void
    {
        // It used to be skipped without a word
        file_put_contents($this->dir . '/parent/setup.json', '{"namespace": "AcmeShop",}');
        putenv('SETUP_JSON');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot read the setup from '{$this->dir}/parent/setup.json': Syntax error");
        $this->load();
    }

    public function testFallsBackToTheParentDirectory(): void
    {
        file_put_contents($this->dir . '/parent/setup.json', '{"db_schema": "sqlite"}');
        putenv('SETUP_JSON');

        $this->assertSame(['db_schema' => 'sqlite'], $this->load());
    }
}
