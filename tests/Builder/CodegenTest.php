<?php

namespace Test\Builder;

use Builder\Scripts;
use Test\Controller\BaseApiTestCase;

/**
 * End-to-end code generator test: introspects the real `project` table from the
 * migrated test database and renders through the byjg/gluo templates.
 * Extends BaseApiTestCase to reuse the database reset and environment guard.
 */
class CodegenTest extends BaseApiTestCase
{
    protected function runCodegen(array $arguments): string
    {
        $scripts = new Scripts();
        ob_start();
        try {
            $scripts->runCodeGenerator(array_merge(['--env=test', '--table=project'], $arguments));
        } finally {
            $output = ob_get_clean();
        }
        return $output;
    }

    public function testGenerateAllRepositoryPattern(): void
    {
        $output = $this->runCodegen(['all']);

        $this->assertStringContainsString('namespace RestReferenceArchitecture\Model;', $output);
        $this->assertStringContainsString('class Project', $output);

        $this->assertStringContainsString('use ByJG\Gluo\Repository\BaseRepository;', $output);
        $this->assertStringContainsString('class ProjectRepository extends BaseRepository', $output);

        $this->assertStringContainsString('use ByJG\Gluo\Service\BaseService;', $output);
        $this->assertStringContainsString('class ProjectService extends BaseService', $output);

        $this->assertStringContainsString('use ByJG\Gluo\Attribute\RequireAuthenticated;', $output);
        $this->assertStringContainsString('use ByJG\Gluo\Attribute\ValidateRequest;', $output);
        $this->assertStringContainsString('class ProjectController', $output);

        $this->assertStringContainsString('use ByJG\Gluo\Util\FakeApiRequester;', $output);
        $this->assertStringContainsString('class ProjectTest', $output);
    }

    public function testGenerateActiveRecordPattern(): void
    {
        $output = $this->runCodegen(['model', 'controller', '--activerecord']);

        $this->assertStringContainsString('use ByJG\MicroOrm\Trait\ActiveRecord;', $output);
        $this->assertStringContainsString('use ByJG\Gluo\Attribute\RequireAuthenticated;', $output);
        // ActiveRecord pattern must not generate repository/service imports
        $this->assertStringNotContainsString('use ByJG\Gluo\Service\BaseService;', $output);
    }

    public function testSaveWritesFilesToProjectLayout(): void
    {
        $workdir = sys_get_temp_dir() . '/gluo-codegen-save-' . uniqid();
        foreach (['src/Model', 'src/Repository', 'src/Service', 'src/Controller', 'tests/Controller'] as $dir) {
            mkdir("$workdir/$dir", 0755, true);
        }

        try {
            $scripts = new class extends Scripts {
                public function useWorkdir(string $dir): void
                {
                    $this->workdir = $dir;
                }
            };
            $scripts->useWorkdir($workdir);

            ob_start();
            try {
                $scripts->runCodeGenerator(['--env=test', '--table=project', 'model', 'controller', 'test', '--save']);
            } finally {
                ob_end_clean();
            }

            $this->assertFileExists("$workdir/src/Model/Project.php");
            $this->assertFileExists("$workdir/src/Controller/ProjectController.php");
            $this->assertFileExists("$workdir/tests/Controller/ProjectTest.php");

            $test = file_get_contents("$workdir/tests/Controller/ProjectTest.php");
            $this->assertStringContainsString('namespace Test\Controller;', $test);
            $this->assertStringContainsString('class ProjectTest', $test);
        } finally {
            shell_exec('rm -rf ' . escapeshellarg($workdir));
        }
    }
}
