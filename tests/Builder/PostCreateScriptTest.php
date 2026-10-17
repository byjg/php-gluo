<?php

namespace Tests\Builder;

use Builder\PostCreateScript;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Tests the create-project file transformation (applyTemplate) against a
 * copy of the real project tree (repo root, spanning the API + frontend/). The
 * side-effect part (composer dump-autoload, openapi, npm install) is in finalize()
 * and is exercised end-to-end by the create-project CI job.
 */
class PostCreateScriptTest extends TestCase
{
    protected string $workdir;

    /** Directories not copied into the sandbox (deps / build output / VCS). */
    protected const SKIP_DIRS = ['vendor', 'node_modules', 'dist', '.git', '.idea'];

    protected function setUp(): void
    {
        // repo root is two levels up from tests/Builder/
        $projectRoot = realpath(__DIR__ . '/../..');
        // Sentinels that must exist for the full-tree tests to be meaningful.
        foreach (['.db-dialects', 'docker', '.github', 'composer.json'] as $item) {
            if (!file_exists("$projectRoot/$item")) {
                // The app image (build-app-image workflow) ships only the runtime subset
                // of the repo; these tests need the full checkout and run in phpunit.yml
                // and, end-to-end, in the create-project workflow.
                $this->markTestSkipped("Template tree incomplete ($item missing) — requires the full repository checkout.");
            }
        }

        $this->workdir = sys_get_temp_dir() . '/create-project-' . uniqid();
        $this->copyRepo($projectRoot, $this->workdir);

        // What `./gluo db:dialect` wrote in this checkout is not versioned: create-project starts
        // without it, from .db-dialects/ alone
        foreach (['docker-compose.yml', 'docker-compose.database.yml'] as $file) {
            @unlink("$this->workdir/$file");
        }
        shell_exec('rm -rf ' . escapeshellarg("$this->workdir/db"));
    }

    protected function tearDown(): void
    {
        if (!isset($this->workdir)) {
            return; // setUp skipped before creating the workdir
        }
        shell_exec('rm -rf ' . escapeshellarg($this->workdir));
    }

    protected function copyRepo(string $source, string $dest): void
    {
        mkdir($dest, 0755, true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $sub = $iterator->getSubPathname();
            $segments = explode(DIRECTORY_SEPARATOR, $sub);
            if (array_intersect($segments, self::SKIP_DIRS)) {
                continue;
            }
            $target = "$dest/$sub";
            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                @mkdir(dirname($target), 0755, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    protected function applyTemplate(bool $installExamples, bool $installFrontend = true, string $composerName = 'acme/shop', array $dbConfig = []): void
    {
        $script = new PostCreateScript();
        ob_start();
        try {
            $script->applyTemplate(
                $this->workdir,
                'AcmeShop',
                $composerName,
                '8.4',
                $dbConfig + [
                    'schema' => 'mysql',
                    'host' => 'db-host',
                    'user' => 'root',
                    'password' => 'secret123',
                    'dev_database' => 'shopdev',
                    'test_database' => 'shoptest',
                ],
                'America/Sao_Paulo',
                $installExamples,
                $installFrontend
            );
        } finally {
            ob_end_clean();
        }
    }

    protected function findFilesContaining(string $needle): array
    {
        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->workdir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && str_contains((string)file_get_contents($item->getPathname()), $needle)) {
                $found[] = substr($item->getPathname(), strlen($this->workdir) + 1);
            }
        }
        return $found;
    }

    public function testNamespaceIsFullyReplacedAndGluoImportsUntouched(): void
    {
        $this->applyTemplate(true);

        $this->assertSame([], $this->findFilesContaining('RestReferenceArchitecture'));

        $login = file_get_contents($this->workdir . '/src/Controller/LoginController.php');
        $this->assertStringContainsString('namespace AcmeShop\Controller;', $login);
        $this->assertStringContainsString('use ByJG\Gluo\Attribute\ValidateRequest;', $login);
        $this->assertStringContainsString('use ByJG\Gluo\Controller\BaseLoginController;', $login);

        $user = file_get_contents($this->workdir . '/src/Model/User.php');
        $this->assertStringContainsString('namespace AcmeShop\Model;', $user);
        $this->assertStringContainsString('use ByJG\Gluo\Model\BaseUser;', $user);
    }

    /**
     * "byjg/gluo" is rewritten to the project's name wherever the template names itself. A
     * comment about the framework names byjg/gluo-core, which the rewrite leaves alone; one that
     * said "byjg/gluo" ended up naming the project ("BaseUser (acme/shop)").
     */
    public function testTheProjectNameOnlyReplacesTheStarterName(): void
    {
        $this->applyTemplate(true);

        $found = $this->findFilesContaining('acme/shop');
        sort($found);
        $this->assertSame(['README.md', 'composer.json'], $found);

        $this->assertStringContainsString(
            'BaseUser (byjg/gluo-core)',
            file_get_contents($this->workdir . '/src/Model/User.php')
        );
        $this->assertStringContainsString(
            'BaseLoginController (byjg/gluo-core)',
            file_get_contents($this->workdir . '/src/Controller/LoginController.php')
        );
    }

    /**
     * @return list<string> the files under $dir, relative to it
     */
    protected static function filesUnder(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            $files[] = substr($item->getPathname(), strlen($dir) + 1);
        }
        sort($files);
        return $files;
    }

    /**
     * Every database has its own copy of every SQL file, in .db-dialects/<schema>/db/: a migration
     * added for one database and not for the others would only fail when a project is created.
     */
    public function testEveryDialectHasTheSameDbFiles(): void
    {
        $dialects = realpath(__DIR__ . '/../../.db-dialects');
        foreach (['postgres', 'sqlite'] as $dialect) {
            $this->assertSame(self::filesUnder("$dialects/mysql/db"), self::filesUnder("$dialects/$dialect/db"), ".db-dialects/$dialect/db");
        }
    }

    /**
     * @return array<string, mixed> docker-compose.database.yml of the generated project, parsed
     */
    protected function composeDatabase(): array
    {
        $compose = \Symfony\Component\Yaml\Yaml::parseFile($this->workdir . '/docker-compose.yml');
        $this->assertSame(['docker-compose.database.yml'], $compose['include']);
        $this->assertSame(['rest', 'frontend'], array_keys($compose['services']));

        return \Symfony\Component\Yaml\Yaml::parseFile($this->workdir . '/docker-compose.database.yml');
    }

    /**
     * The connection the app in the rest container uses: the database service, where the
     * credentials.env of the host commands have 127.0.0.1.
     */
    protected function containerConnection(): string
    {
        $compose = \Symfony\Component\Yaml\Yaml::parseFile($this->workdir . '/docker-compose.yml');
        foreach ($compose['services']['rest']['environment'] as $variable) {
            if (str_starts_with($variable, 'DBDRIVER_CONNECTION=')) {
                return substr($variable, strlen('DBDRIVER_CONNECTION='));
            }
        }
        return '';
    }

    protected function envValue(string $env, string $key): string
    {
        preg_match('/^' . $key . '=(.*)$/m', (string)file_get_contents($this->workdir . "/config/$env/credentials.env"), $match);
        return $match[1] ?? '';
    }

    /**
     * The files of the database chosen are the project's own: .db-dialects/ and the command that
     * writes them from it are gone, and .gitignore no longer leaves the files out.
     */
    protected function assertTheDialectIsFinal(): void
    {
        $this->assertDirectoryDoesNotExist($this->workdir . '/.db-dialects');
        $this->assertFileDoesNotExist($this->workdir . '/builder/DbDialect.php');
        $this->assertStringNotContainsString('DbDialect', (string)file_get_contents($this->workdir . '/src/Command/Commands.php'));
        $this->assertStringNotContainsString('DbDialect', (string)file_get_contents($this->workdir . '/config/dev/05-services.php'));

        $gitignore = (string)file_get_contents($this->workdir . '/.gitignore');
        $this->assertStringNotContainsString('docker-compose', $gitignore);
        $this->assertStringNotContainsString('/db/base.sql', $gitignore);
        $this->assertStringContainsString('/db/*.sqlite', $gitignore);
    }

    public function testAMysqlProjectGetsTheMysqlSqlAndServer(): void
    {
        $base = (string)file_get_contents($this->workdir . '/.db-dialects/mysql/db/base.sql');
        $this->applyTemplate(true);

        $this->assertTheDialectIsFinal();
        $this->assertSame($base, file_get_contents($this->workdir . '/db/base.sql'));
        $compose = $this->composeDatabase();
        $this->assertSame(['db-host'], array_keys($compose['services']));
        $this->assertSame('mysql:8.0', $compose['services']['db-host']['image']);
        $this->assertSame('secret123', $compose['services']['db-host']['environment']['MYSQL_ROOT_PASSWORD']);
        $this->assertSame(['mysql-volume'], array_keys($compose['volumes']));
        $this->assertSame('mysql://root:secret123@127.0.0.1/shopdev', $this->envValue('dev', 'DBDRIVER_CONNECTION'));
        $this->assertSame('mysql://root:secret123@db-host/shopdev', $this->containerConnection());
        // The tests force the test database of the project
        $this->assertStringContainsString("withPath('/shoptest')", (string)file_get_contents($this->workdir . '/tests/bootstrap.php'));
    }

    public function testTheDatabaseServiceKeepsItsNameWithTheDefaultHost(): void
    {
        $this->applyTemplate(true, true, 'acme/shop', ['host' => 'gluo-database']);

        $this->assertSame(['gluo-database'], array_keys($this->composeDatabase()['services']));
        $this->assertSame('mysql://root:secret123@gluo-database/shopdev', $this->containerConnection());
        $this->assertSame('mysql://root:secret123@127.0.0.1/shopdev', $this->envValue('dev', 'DBDRIVER_CONNECTION'));
    }

    public function testAPostgresProjectGetsThePostgresSqlAndServer(): void
    {
        $base = (string)file_get_contents($this->workdir . '/.db-dialects/postgres/db/base.sql');
        $migration = (string)file_get_contents($this->workdir . '/.db-dialects/postgres/db/migrations/up/00001-create-table-examples.sql');
        $this->applyTemplate(true, true, 'acme/shop', ['schema' => 'postgres', 'host' => 'pg-host', 'user' => 'postgres']);

        $this->assertTheDialectIsFinal();
        $this->assertSame($base, file_get_contents($this->workdir . '/db/base.sql'));
        $this->assertSame($migration, file_get_contents($this->workdir . '/db/migrations/up/00001-create-table-examples.sql'));

        $compose = $this->composeDatabase();
        $this->assertSame(['pg-host'], array_keys($compose['services']));
        $this->assertSame('postgres:17', $compose['services']['pg-host']['image']);
        $this->assertArrayNotHasKey('container_name', $compose['services']['pg-host']);
        $this->assertSame('secret123', $compose['services']['pg-host']['environment']['POSTGRES_PASSWORD']);
        $this->assertSame(['postgres-volume'], array_keys($compose['volumes']));

        $this->assertSame('postgres://postgres:secret123@pg-host/shopdev', $this->containerConnection());
        $this->assertSame('postgres://postgres:secret123@127.0.0.1/shopdev', $this->envValue('dev', 'DBDRIVER_CONNECTION'));
        $this->assertSame('postgres://postgres:secret123@127.0.0.1/shoptest', $this->envValue('test', 'DBDRIVER_CONNECTION'));
        $this->assertStringStartsWith('postgres://', $this->envValue('staging', 'DBDRIVER_CONNECTION'));
        $this->assertStringStartsWith('postgres://', $this->envValue('prod', 'DBDRIVER_CONNECTION'));
    }

    public function testASqliteProjectHasNoServerAndKeepsItsDatabasesUnderDb(): void
    {
        $base = (string)file_get_contents($this->workdir . '/.db-dialects/sqlite/db/base.sql');
        $this->applyTemplate(false, true, 'acme/shop', ['schema' => 'sqlite', 'host' => '', 'user' => '', 'password' => '']);

        $this->assertTheDialectIsFinal();
        $this->assertSame($base, file_get_contents($this->workdir . '/db/base.sql'));
        // Without the examples, their migration is removed from the SQLite set as well
        $this->assertFileDoesNotExist($this->workdir . '/db/migrations/up/00001-create-table-examples.sql');

        $compose = $this->composeDatabase();
        $this->assertSame([], $compose['services']);
        $this->assertArrayNotHasKey('volumes', $compose);

        // The same file in the container: no connection of its own there
        $this->assertSame('', $this->containerConnection());
        $this->assertSame('sqlite://{__ROOT__}/db/shopdev.sqlite', $this->envValue('dev', 'DBDRIVER_CONNECTION'));
        $this->assertSame('sqlite://{__ROOT__}/db/shoptest.sqlite', $this->envValue('test', 'DBDRIVER_CONNECTION'));
        $this->assertSame('sqlite://{__ROOT__}/db/mydb_staging.sqlite', $this->envValue('staging', 'DBDRIVER_CONNECTION'));
        $this->assertSame('sqlite://{__ROOT__}/db/mydb_prod.sqlite', $this->envValue('prod', 'DBDRIVER_CONNECTION'));
    }

    public function testComposerJsonRenamed(): void
    {
        $this->applyTemplate(true);

        $root = file_get_contents($this->workdir . '/composer.json');
        $this->assertStringContainsString('"name": "acme/shop"', $root);
        $this->assertStringNotContainsString('"byjg/gluo"', $root);
        // the framework dependency must survive the rename
        $this->assertStringContainsString('"byjg/gluo-core"', $root);
        // the starter's own description is not the project's
        $this->assertSame('A PHP REST API built with Gluo.', json_decode($root, true)['description']);
    }

    /**
     * A project name that starts with the template's own name must not be expanded twice
     * ("byjg/gluo-shop" becoming "byjg/gluo-shop-shop").
     */
    public function testComposerNameStartingWithTheTemplateNameIsNotDoubled(): void
    {
        $this->applyTemplate(true, composerName: 'byjg/gluo-shop');

        $root = json_decode(file_get_contents($this->workdir . '/composer.json'), true);
        $this->assertSame('byjg/gluo-shop', $root['name']);
        $this->assertArrayHasKey('byjg/gluo-core', $root['require']);

        // The search below has to be able to find something, or an empty result proves
        // nothing: the new name is in the files that are meant to carry it.
        $withNewName = $this->findFilesContaining('byjg/gluo-shop');
        $this->assertContains('composer.json', $withNewName);
        $this->assertContains('README.md', $withNewName);

        // No file anywhere carries the doubled name.
        $this->assertSame([], $this->findFilesContaining('gluo-shop-shop'));
        $compose = file_get_contents($this->workdir . '/docker-compose.yml');
        $this->assertStringContainsString('image: gluo-shop:dev', $compose);
        $this->assertStringNotContainsString('container_name', $compose);
    }

    public function testDockerfileVersionAndTimezone(): void
    {
        $this->applyTemplate(true);

        $dockerfile = file_get_contents($this->workdir . '/docker/Dockerfile');
        $this->assertStringContainsString('ENV TZ=America/Sao_Paulo', $dockerfile);
        $this->assertStringContainsString('php:8.4-fpm', $dockerfile);
        $this->assertStringNotContainsString('php85', $dockerfile);
    }

    public function testConnectionStringsAndCredentials(): void
    {
        $this->applyTemplate(true);

        $dev = file_get_contents($this->workdir . '/config/dev/credentials.env');
        $this->assertStringContainsString('DBDRIVER_CONNECTION=mysql://root:secret123@127.0.0.1/shopdev', $dev);

        $test = file_get_contents($this->workdir . '/config/test/credentials.env');
        $this->assertStringContainsString('DBDRIVER_CONNECTION=mysql://root:secret123@127.0.0.1/shoptest', $test);

        $compose = file_get_contents($this->workdir . '/docker-compose.yml');
        $this->assertStringNotContainsString('resttest', $compose);
        $database = file_get_contents($this->workdir . '/docker-compose.database.yml');
        $this->assertStringContainsString('MYSQL_ROOT_PASSWORD: secret123', $database);
    }

    public function testJwtSecretsAreRegeneratedAndUniquePerEnvironment(): void
    {
        $original = [];
        foreach (['dev', 'test', 'staging', 'prod'] as $env) {
            preg_match('/JWT_SECRET=(.*)/', (string)file_get_contents($this->workdir . "/config/$env/credentials.env"), $m);
            $original[$env] = $m[1] ?? '';
        }

        $this->applyTemplate(true);

        $secrets = [];
        foreach (['dev', 'test', 'staging', 'prod'] as $env) {
            preg_match('/JWT_SECRET=(.*)/', (string)file_get_contents($this->workdir . "/config/$env/credentials.env"), $m);
            $this->assertNotEmpty($m[1], "JWT_SECRET missing in $env");
            $this->assertNotSame($original[$env], $m[1], "JWT_SECRET not regenerated in $env");
            $secrets[] = $m[1];
        }
        $this->assertCount(4, array_unique($secrets), 'JWT secrets must be unique per environment');
    }

    public function testEnvSampleCreatedWithLocalhostConnection(): void
    {
        $this->applyTemplate(true);

        $sample = file_get_contents($this->workdir . '/config/.env.sample');
        $this->assertStringContainsString('mysql://root:secret123@127.0.0.1/shopdev', $sample);
    }

    public function testTemplateMachineryRemoved(): void
    {
        $templateOnlyFiles = [
            '.github/workflows/phpunit.yml',
            '.github/workflows/create-project.yml',
            'builder/PostCreateScript.php',
            'tests/Builder/PostCreateScriptTest.php',
            'tests/Builder/SetupJsonTest.php',
            'tests/Builder/ContainerDetectionTest.php',
        ];
        foreach ($templateOnlyFiles as $file) {
            $this->assertFileExists($this->workdir . '/' . $file);
        }
        $this->assertNotEmpty(glob($this->workdir . '/CHANGELOG-*.md'), 'The starter ships changelogs for this test to be meaningful');

        $this->applyTemplate(true);

        foreach ($templateOnlyFiles as $file) {
            $this->assertFileDoesNotExist($this->workdir . '/' . $file);
        }

        // A test of the installer would fail in the project, which has no PostCreateScript
        $installerTests = array_filter(
            $this->findFilesContaining('PostCreateScript'),
            fn (string $file) => str_starts_with($file, 'tests/')
        );
        $this->assertSame([], array_values($installerTests));

        // The starter's changelogs are its own history, not the new project's.
        $this->assertSame([], glob($this->workdir . '/CHANGELOG*'));

        $composer = file_get_contents($this->workdir . '/composer.json');
        $this->assertStringNotContainsString('post-create-project-cmd', $composer);
        $this->assertNotNull(json_decode($composer), 'composer.json must remain valid JSON');
    }

    public function testDocsReplacedByReadmeLinkingToOnlineDocs(): void
    {
        $this->assertDirectoryExists($this->workdir . '/docs');
        $this->assertFileExists($this->workdir . '/builder/README.project.md');

        $this->applyTemplate(true);

        $this->assertDirectoryDoesNotExist($this->workdir . '/docs');
        $this->assertFileDoesNotExist($this->workdir . '/builder/README.project.md');

        $readme = file_get_contents($this->workdir . '/README.md');
        $this->assertStringStartsWith("# acme/shop\n", $readme);
        $this->assertStringContainsString('under the `AcmeShop` namespace', $readme);
        $this->assertStringContainsString('https://opensource.byjg.com/docs/php/gluo/', $readme);
        $this->assertStringContainsString('`byjg/gluo-core`', $readme);
        $this->assertStringNotContainsString('RestReferenceArchitecture', $readme);
    }

    /**
     * Regression: the hook is stripped by deleting its whole line, which also removes
     * that line's own trailing comma. That is correct everywhere except as the LAST
     * entry of a block, where it leaves the previous entry's comma dangling before the
     * closing brace and produces invalid JSON. Guard the manifest against a reorder.
     */
    public function testHookRemovalLeavesValidJsonWhenHookIsLastScriptEntry(): void
    {
        $file = $this->workdir . '/composer.json';
        $manifest = json_decode((string)file_get_contents($file), true);
        $this->assertArrayHasKey('post-create-project-cmd', $manifest['scripts']);

        // Re-emit the manifest with the hook as the final entry of "scripts".
        $hook = $manifest['scripts']['post-create-project-cmd'];
        unset($manifest['scripts']['post-create-project-cmd']);
        $manifest['scripts']['post-create-project-cmd'] = $hook;
        file_put_contents(
            $file,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $this->applyTemplate(true);

        $composer = (string)file_get_contents($file);
        $this->assertNotNull(
            json_decode($composer),
            'composer.json must stay valid JSON when the hook is the last script entry'
        );
        $this->assertStringNotContainsString('post-create-project-cmd', $composer);
        // The surrounding scripts must survive intact.
        $decoded = json_decode($composer, true);
        $this->assertArrayHasKey('openapi', $decoded['scripts']);
        $this->assertArrayHasKey('test', $decoded['scripts']);
    }

    public function testExamplesKeptWhenRequested(): void
    {
        $this->applyTemplate(true);

        $this->assertFileExists($this->workdir . '/src/Model/Project.php');
        $this->assertFileExists($this->workdir . '/src/Model/Task.php');
        $this->assertFileExists($this->workdir . '/src/Model/Note.php');
        $this->assertFileExists($this->workdir . '/src/Controller/SampleController.php');
        $this->assertStringContainsString(
            'namespace AcmeShop\Repository;',
            (string)file_get_contents($this->workdir . '/src/Repository/ProjectRepository.php')
        );
    }

    public function testExamplesRemovedWhenNotRequested(): void
    {
        $this->applyTemplate(false);

        $this->assertFileDoesNotExist($this->workdir . '/src/Model/Project.php');
        $this->assertFileDoesNotExist($this->workdir . '/src/Model/Task.php');
        $this->assertFileDoesNotExist($this->workdir . '/src/Model/Note.php');
        $this->assertFileDoesNotExist($this->workdir . '/src/Controller/SampleController.php');
        // the rollback lives under down/, not up/ (regression guard for the old path bug)
        $this->assertFileDoesNotExist($this->workdir . '/db/migrations/down/00000-rollback-table-examples.sql');

        $repos = (string)file_get_contents($this->workdir . '/config/dev/04-repositories.php');
        $this->assertStringNotContainsString('ProjectRepository', $repos);
        $this->assertStringNotContainsString('TaskRepository', $repos);

        $services = (string)file_get_contents($this->workdir . '/config/dev/05-services.php');
        $this->assertStringNotContainsString('ProjectService', $services);
        $this->assertStringNotContainsString('TaskService', $services);

        // Controllers are autowired by pattern, so this file names no class at all and
        // survives example removal untouched -- there is nothing pointing at a deleted
        // class for DI::bind()/class_exists() to trip over.
        $controllers = (string)file_get_contents($this->workdir . '/config/dev/07-controllers.php');
        $this->assertStringContainsString("'AcmeShop\\Controller\\*' => Autowire::rule()", $controllers);
        foreach (['ProjectController', 'TaskController', 'NoteController', 'SampleController'] as $removed) {
            $this->assertStringNotContainsString($removed, $controllers);
        }

        // The blocks are cut by marker, so a bad match could leave a dangling fragment
        // that the string assertions above would not notice. token_get_all() with
        // TOKEN_PARSE throws ParseError on invalid PHP.
        foreach ([$repos, $services, $controllers] as $config) {
            token_get_all($config, TOKEN_PARSE);
        }

        $index = file_get_contents($this->workdir . '/public/index.html');
        $this->assertStringNotContainsString('Start Example', $index);

        // Frontend example screens + their router/nav/dashboard markers are stripped,
        // without commenting out the following statement (regression guard).
        if (is_dir($this->workdir . '/frontend')) {
            $this->assertFileDoesNotExist($this->workdir . '/frontend/src/pages/examples/ProjectsList.jsx');

            $app = file_get_contents($this->workdir . '/frontend/src/App.jsx');
            $this->assertStringNotContainsString('ProjectsList', $app);
            $this->assertStringContainsString('function ProtectedLayout', $app);
            $this->assertStringNotContainsString('// function ProtectedLayout', $app);

            $dashboard = file_get_contents($this->workdir . '/frontend/src/pages/dashboard/Dashboard.jsx');
            $this->assertStringNotContainsString('/projects', $dashboard);
        }
    }

    public function testFrontendKeptWhenRequested(): void
    {
        if (!is_dir($this->workdir . '/frontend')) {
            $this->markTestSkipped('Frontend (frontend/) not present in this checkout.');
        }
        $this->applyTemplate(true, installFrontend: true);

        $this->assertDirectoryExists($this->workdir . '/frontend');
        $this->assertFileExists($this->workdir . '/docker/Dockerfile-html');
        $this->assertFileExists($this->workdir . '/docker/static-html-entrypoint.sh');
        $this->assertFileExists($this->workdir . '/frontend/public/config.js');
        $compose = file_get_contents($this->workdir . '/docker-compose.yml');
        $this->assertStringContainsString('gluo-frontend', $compose);
    }

    public function testFrontendRemovedWhenNotRequested(): void
    {
        if (!is_dir($this->workdir . '/frontend')) {
            $this->markTestSkipped('Frontend (frontend/) not present in this checkout.');
        }
        $this->applyTemplate(true, installFrontend: false);

        $this->assertDirectoryDoesNotExist($this->workdir . '/frontend');
        $this->assertFileDoesNotExist($this->workdir . '/docker/Dockerfile-html');
        $this->assertFileDoesNotExist($this->workdir . '/docker/static-html-entrypoint.sh');
        $compose = file_get_contents($this->workdir . '/docker-compose.yml');
        $this->assertStringNotContainsString('gluo-frontend', $compose);
        $this->assertStringNotContainsString('Dockerfile-html', $compose);
        // the rest + db services must survive
        $this->assertStringContainsString('rest:', $compose);
        $this->assertStringContainsString('image: mysql:', (string)file_get_contents($this->workdir . '/docker-compose.database.yml'));
    }
}
