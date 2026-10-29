<?php

namespace Builder;

use ByJG\Gluo\Builder\BaseScripts;

/**
 * Migration, OpenAPI generation and code generation logic live in
 * BaseScripts (byjg/gluo-core). The gluo script runs its built-in commands through
 * this class, which anchors the working directory to this project and is
 * the place to override them.
 */
class Scripts extends BaseScripts
{
    public function __construct()
    {
        parent::__construct();
        // Anchor to the project root. The base class guesses from the vendor
        // location, which fails when byjg/gluo-core is symlinked (path repository).
        $this->workdir = realpath(__DIR__ . '/..');
    }

    protected function getAppNamespace(): string
    {
        return 'RestReferenceArchitecture';
    }
}
