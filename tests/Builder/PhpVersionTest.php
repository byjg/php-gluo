<?php

namespace Tests\Builder;

use Builder\PostCreateScript;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The installer only accepts the PHP versions docker/Dockerfile has a byjg/php image for.
 */
class PhpVersionTest extends TestCase
{
    protected function validate(string $version): string
    {
        $script = new class extends PostCreateScript {
            public static function check(string $version): string
            {
                return self::validatePHPVersion($version);
            }
        };

        return $script::check($version);
    }

    public static function supportedProvider(): array
    {
        return [['8.3'], ['8.4'], ['8.5'], ['8.6']];
    }

    #[DataProvider('supportedProvider')]
    public function testSupportedVersionIsAccepted(string $version): void
    {
        $this->assertSame($version, $this->validate($version));
    }

    public static function unsupportedProvider(): array
    {
        return [['8.2'], ['8.7'], ['8'], ['8.6.0'], ['']];
    }

    #[DataProvider('unsupportedProvider')]
    public function testUnsupportedVersionIsRejected(string $version): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Only the PHP versions 8.3, 8.4, 8.5, 8.6 are supported');
        $this->validate($version);
    }
}
