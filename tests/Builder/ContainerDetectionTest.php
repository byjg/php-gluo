<?php

namespace Tests\Builder;

use Builder\PostCreateScript;
use PHPUnit\Framework\TestCase;

/**
 * A Composer running in a container has no Docker command, which does not mean the host has no
 * Docker: the installer must tell the two apart before warning that Docker is not installed.
 */
class ContainerDetectionTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/container-detection-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
    }

    protected function isInsideContainer(array $markers): bool
    {
        $script = new class extends PostCreateScript {
            public static function check(array $markers): bool
            {
                return self::isInsideContainer($markers);
            }
        };

        return $script::check($markers);
    }

    public function testAMarkerFileMeansAContainer(): void
    {
        touch($this->dir . '/.containerenv');

        $this->assertTrue($this->isInsideContainer([$this->dir . '/.dockerenv', $this->dir . '/.containerenv']));
    }

    public function testNoMarkerFileMeansTheHost(): void
    {
        $this->assertFalse($this->isInsideContainer([$this->dir . '/.dockerenv', $this->dir . '/.containerenv']));
    }
}
