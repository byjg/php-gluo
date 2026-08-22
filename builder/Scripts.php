<?php

namespace Builder;

use ByJG\Gluo\Builder\BaseScripts;
use Composer\Script\Event;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Migration, OpenAPI generation and code generation logic live in
 * BaseScripts (byjg/gluo). This class binds them to composer scripts
 * and anchors the working directory to this project.
 */
class Scripts extends BaseScripts
{
    /**
     * A fresh checkout writes every file at roughly the same moment, and those
     * timestamps can straddle a second boundary. Ignore differences this small so
     * CI does not report a stale spec on every run; a real edit is always older.
     */
    private const STALE_GRACE_SECONDS = 2;

    public function __construct()
    {
        parent::__construct();
        // Anchor to the project root. The base class guesses from the vendor
        // location, which fails when byjg/gluo is symlinked (path repository).
        $this->workdir = realpath(__DIR__ . '/..');
    }

    protected function getAppNamespace(): string
    {
        return 'RestReferenceArchitecture';
    }

    public static function migrate(Event $event): void
    {
        (new Scripts())->runMigrate($event->getArguments());
    }

    public static function genOpenApiDocs(Event $event): void
    {
        (new Scripts())->runGenOpenApiDocs($event->getArguments());
    }

    public static function codeGenerator(Event $event): void
    {
        (new Scripts())->runCodeGenerator($event->getArguments());
    }

    /**
     * Routing is derived from the generated spec: OpenApiRouteList reads
     * public/docs/openapi.json. A controller change that has not been regenerated
     * therefore produces a silent 404 rather than an error. Warn when the spec is
     * older than the sources it is built from.
     *
     * Never blocks — a stale spec is a nuisance, not a reason to stop a test run.
     */
    public static function checkOpenApi(Event $event): void
    {
        (new Scripts())->runCheckOpenApi();
    }

    public function runCheckOpenApi(): void
    {
        $spec = $this->workdir . '/public/docs/openapi.json';

        if (!file_exists($spec)) {
            $this->warnStaleOpenApi([
                'public/docs/openapi.json does not exist.',
                'Routes are read from it, so every endpoint will 404.',
            ]);
            return;
        }

        $stale = $this->sourcesNewerThan((int)filemtime($spec) + self::STALE_GRACE_SECONDS);
        if ($stale === []) {
            return;
        }

        sort($stale);
        $lines = ['These files changed after the spec was generated:', ''];
        foreach (array_slice($stale, 0, 5) as $file) {
            $lines[] = "  - $file";
        }
        if (count($stale) > 5) {
            $lines[] = '  ... and ' . (count($stale) - 5) . ' more';
        }
        $lines[] = '';
        $lines[] = 'New or changed endpoints will 404 until the spec is rebuilt.';
        $this->warnStaleOpenApi($lines);
    }

    /**
     * @return string[] project-relative paths of PHP sources newer than $timestamp
     */
    private function sourcesNewerThan(int $timestamp): array
    {
        $found = [];
        foreach (['/src/Controller', '/src/Model'] as $relative) {
            $dir = $this->workdir . $relative;
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php' && $file->getMTime() > $timestamp) {
                    $found[] = substr($file->getPathname(), strlen((string)$this->workdir) + 1);
                }
            }
        }
        return $found;
    }

    /**
     * @param string[] $lines
     */
    private function warnStaleOpenApi(array $lines): void
    {
        $rule = str_repeat('!', 72);
        fwrite(STDERR, "\n$rule\n  The OpenAPI spec is out of date\n\n");
        foreach ($lines as $line) {
            fwrite(STDERR, "  $line\n");
        }
        fwrite(STDERR, "\n  Run:  composer openapi\n$rule\n\n");
    }
}
