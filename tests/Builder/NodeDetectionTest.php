<?php

namespace Tests\Builder;

use Builder\PostCreateScript;
use PHPUnit\Framework\TestCase;

/**
 * A Composer running where there is no Node (a PHP-only container) cannot install the frontend
 * dependencies, which is expected, not a failure: the installer must tell it apart from npm/bun
 * being there and failing.
 */
class NodeDetectionTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/node-detection-' . uniqid();
        mkdir($this->dir . '/bin', 0755, true);
        mkdir($this->dir . '/other', 0755, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
    }

    protected function hasNodePackageManager(string $path): bool
    {
        $script = new class extends PostCreateScript {
            public static function check(string $path): bool
            {
                return self::hasNodePackageManager($path);
            }
        };

        return $script::check($path);
    }

    protected function command(string $name, int $mode = 0755): void
    {
        file_put_contents("$this->dir/bin/$name", "#!/bin/sh\n");
        chmod("$this->dir/bin/$name", $mode);
    }

    public function testNpmOnThePath(): void
    {
        $this->command('npm');

        $this->assertTrue($this->hasNodePackageManager("$this->dir/other" . PATH_SEPARATOR . "$this->dir/bin"));
    }

    public function testBunOnThePath(): void
    {
        $this->command('bun');

        $this->assertTrue($this->hasNodePackageManager("$this->dir/bin"));
    }

    public function testNeitherOnThePath(): void
    {
        $this->command('node');

        $this->assertFalse($this->hasNodePackageManager("$this->dir/other" . PATH_SEPARATOR . "$this->dir/bin"));
    }

    public function testANonExecutableFileIsNotTheCommand(): void
    {
        $this->command('npm', 0644);

        $this->assertFalse($this->hasNodePackageManager("$this->dir/bin"));
    }

    public function testADirectoryIsNotTheCommand(): void
    {
        mkdir("$this->dir/bin/npm");

        $this->assertFalse($this->hasNodePackageManager("$this->dir/bin"));
    }
}
