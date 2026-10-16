<?php

namespace Builder;

use Composer\Script\Event;
use Exception;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Runs from the project ROOT during `composer create-project`, after Composer
 * has installed the dependencies. It is still written against PHP stdlib only:
 * it rewrites the namespace the rest of the codebase is autoloaded under, so it
 * must not depend on classes whose autoload map it is about to invalidate.
 */
class PostCreateScript
{
    public function execute($workdir, $namespace, $composerName, $phpVersion, array $dbConfig, $timezone, $installExamples, $installFrontend): void
    {
        $this->applyTemplate($workdir, $namespace, $composerName, $phpVersion, $dbConfig, $timezone, $installExamples, $installFrontend);
        $this->finalize();
    }

    /**
     * Pure file transformation: rename namespace/composer name, adjust docker
     * and config files, remove examples. No process side effects, so it can
     * be tested against a copy of the project tree.
     */
    public function applyTemplate(string $workdir, string $namespace, string $composerName, string $phpVersion, array $dbConfig, string $timezone, bool $installExamples, bool $installFrontend = true): void
    {
        $this->applyDialect($workdir, $dbConfig['schema'] ?? 'mysql');

        $devConnection = self::buildConnectionString($dbConfig, $dbConfig['dev_database']);
        $testConnection = self::buildConnectionString($dbConfig, $dbConfig['test_database']);

        // ------------------------------------------------
        // Defining function to interactively walking through the directories
        $directory = new RecursiveDirectoryIterator($workdir);
        $filter = new RecursiveCallbackFilterIterator($directory, function ($current) {
            $skipDirs = ['fw', 'vendor', 'node_modules', 'dist'];
            // Skip hidden files and directories, except .claude/ (needs namespace replacement).
            if ($current->getFilename()[0] === '.') {
                return $current->isDir() && $current->getFilename() === '.claude';
            }
            if ($current->isDir()) {
                // Only recurse into intended subdirectories (skip deps/build output).
                return !in_array($current->getFilename(), $skipDirs, true);
            }
            // else {
            //     // Only consume files of interest.
            //     return strpos($current->getFilename(), 'wanted_filename') === 0;
            // }
            return true;
        });

        $composerName = strtolower($composerName);
        $composerParts = explode("/", $composerName);
        $phpVersionMSimple = str_replace(".", "", $phpVersion);

        // ------------------------------------------------
        // Replace the composer name in the single root manifest
        // (quote-exact so the byjg/gluo-core requirement is untouched), and the starter's own
        // description ("Run composer create-project byjg/gluo ...") with a neutral one:
        $rootComposer = (string)file_get_contents($workdir . '/composer.json');
        $rootComposer = str_replace('"byjg/gluo"', '"' . $composerName . '"', $rootComposer);
        $rootComposer = (string)preg_replace(
            '/("description":\s*)"(?:[^"\\\\]|\\\\.)*"/',
            '$1"A PHP REST API built with Gluo."',
            $rootComposer,
            1
        );
        file_put_contents($workdir . '/composer.json', $rootComposer);

        // ------------------------------------------------
        // Replace Docker PHP Version
        $files = [ 'docker/Dockerfile' ];
        foreach ($files as $file) {
            $contents = file_get_contents("$workdir/$file");
            $contents = str_replace('ENV TZ=UTC', "ENV TZ=$timezone", $contents);
            $contents = str_replace('php:8.5-fpm', "php:$phpVersion-fpm", $contents);
            $contents = str_replace('php85', "php$phpVersionMSimple", $contents);
            file_put_contents(
                "$workdir/$file",
                $contents
            );
        }

        // ------------------------------------------------
        // Adjusting config files (the PHP app root is the repository root)
        $files = [
            'config/dev/credentials.env',
            'config/test/credentials.env',
            'config/staging/credentials.env',
            'config/prod/credentials.env',
            'docker-compose.yml',
        ];
        foreach ($files as $file) {
            $contents = file_get_contents("$workdir/$file");

            // Common replacements for all files
            $contents = str_replace(
                [
                    'mysql://root:mysqlp455w0rd@mysql-container/mydb',
                    'mysql://root:mysqlp455w0rd@mysql-container/localdev',
                ],
                $devConnection,
                $contents
            );
            $contents = str_replace(
                'mysql://root:mysqlp455w0rd@mysql-container/localtest',
                $testConnection,
                $contents
            );

            if ($file === 'docker-compose.yml') {
                $contents = self::composeDatabase($contents, $dbConfig['schema'] ?? 'mysql');
            }

            // staging and prod hold placeholders (CHANGE_ME), written as MySQL URIs: give them the
            // scheme of the database chosen
            if (preg_match('~^config/(staging|prod)/~', $file)) {
                $contents = self::placeholderConnection($contents, $dbConfig['schema'] ?? 'mysql');
            }

            if (!empty($dbConfig['host'])) {
                $contents = str_replace(['mysql-container', 'postgres-container'], $dbConfig['host'], $contents);
            }

            if (!empty($dbConfig['password'])) {
                $contents = str_replace('mysqlp455w0rd', $dbConfig['password'], $contents);
            }
            $contents = str_replace('resttest', $composerParts[1], $contents);

            if ($file === 'docker-compose.yml' && !empty($dbConfig['password'])) {
                $contents = preg_replace(
                    '/(MYSQL_ROOT_PASSWORD:\s*)([^\s]+)/',
                    '$1' . $dbConfig['password'],
                    $contents
                );
            }

            // JWT_SECRET only for .env files - each gets unique secret
            if (str_ends_with($file, '.env')) {
                $jwtSecret = self::generateSecret();
                $contents = preg_replace('/JWT_SECRET=.*/', "JWT_SECRET=$jwtSecret", $contents);

                if (str_contains($file, 'config/dev/')) {
                    $contents = self::replaceEnvValue($contents, 'DBDRIVER_CONNECTION', $devConnection);
                }

                if (str_contains($file, 'config/test/')) {
                    $contents = self::replaceEnvValue($contents, 'DBDRIVER_CONNECTION', $testConnection);
                }
            }

            file_put_contents("$workdir/$file", $contents);
        }

        // ------------------------------------------------
        // Create .env.sample file with database connection example
        // Build a localhost connection string for the developer's local machine
        $localhostDbConfig = $dbConfig;
        $localhostDbConfig['host'] = '127.0.0.1';
        $localhostConnection = self::buildConnectionString($localhostDbConfig, $dbConfig['dev_database']);

        $envSampleContent = <<<ENV
# IMPORTANT: This is NOT the main environment configuration file!
#
# The config/.env file should be used ONLY for specific/sensitive configurations
# on your local developer machine that you don't want to commit to version control.
#
# Main environment configurations should be placed in:
# - config/dev/credentials.env
# - config/test/credentials.env
# - config/staging/credentials.env
# - config/prod/credentials.env
#
# Use this file to override specific settings for your local machine only.
# Common use cases:
# - Local database connection strings
# - Developer-specific API keys
# - Local service URLs
# - Any sensitive data that shouldn't be in version control

# Example: Override database connection for local development
;DBDRIVER_CONNECTION=$localhostConnection

# Example: Override JWT secret for local testing
;JWT_SECRET=local-dev-secret-key
ENV;

        file_put_contents("$workdir/config/.env.sample", $envSampleContent);
        echo "Created config/.env.sample\n";

        // ------------------------------------------------
        // Adjusting namespace
        $objects = new RecursiveIteratorIterator($filter);
        foreach ($objects as $name => $object) {
            $contents = file_get_contents($name);
            if (str_contains($contents, 'RestReferenceArchitecture')) {
                echo "$name\n";

                // Replace inside Quotes
                $contents = preg_replace(
                    "/(['\"])RestReferenceArchitecture(.*?['\"])/",
                    '$1' . str_replace('\\', '\\\\\\\\', $namespace) . '$2',
                    $contents
                );

                // Replace reserved name
                $contents = str_replace('RestReferenceArchitecture', $namespace, $contents);

                // Replace the template package name, and only that exact name: a longer
                // one that merely starts with it is left alone. That covers the framework
                // dependency (byjg/gluo-core) and a project whose own name starts with
                // byjg/gluo, which composer.json already carries by this point and would
                // otherwise get its suffix appended a second time.
                $contents = preg_replace(
                    '~byjg/gluo(?![\w-])~',
                    $composerName,
                    $contents
                );

                // Save it
                file_put_contents(
                    $name,
                    $contents
                );
            }
        }

        // ------------------------------------------------
        // Remove the template machinery (it's for the reference architecture repo only):
        // its CI workflows, this script, and its test. Leaving them in the generated
        // project would ship self-modified (broken) code the user never runs.
        $templateOnlyFiles = [
            '.github/workflows/phpunit.yml',
            '.github/workflows/create-project.yml',
            '.github/workflows/frontend.yml',
            'builder/PostCreateScript.php',
            'tests/Builder/PostCreateScriptTest.php',
            'tests/Builder/SetupJsonTest.php',
            'tests/Builder/ContainerDetectionTest.php',
        ];
        foreach ($templateOnlyFiles as $file) {
            if (file_exists("$workdir/$file")) {
                unlink("$workdir/$file");
                echo "Removed $file\n";
            }
        }

        // ------------------------------------------------
        // The documentation is published online, where it follows gluo-core updates; a
        // copy in the project would be frozen at creation time. Drop docs/ and replace the
        // template's README with a short one that links to the online documentation.
        if (is_dir("$workdir/docs")) {
            self::removeDir("$workdir/docs");
            echo "Removed docs/\n";
        }
        rename("$workdir/builder/README.project.md", "$workdir/README.md");
        echo "Created README.md\n";

        // The changelogs are the history of this starter, not of the project being
        // created, which starts its own.
        foreach (glob("$workdir/CHANGELOG-*.md") ?: [] as $changelog) {
            unlink($changelog);
            echo "Removed " . basename($changelog) . "\n";
        }

        // Drop the create-project hook from composer.json (its class no longer exists).
        // Removing the whole line takes its own trailing comma with it, which is correct
        // for every position except the last entry of a block — there the *previous*
        // line's comma is left dangling before the closing brace. Repair that, so the
        // result stays valid JSON no matter where the key sits in the manifest.
        $contents = file_get_contents($workdir . '/composer.json');
        $contents = preg_replace('/^[ \t]*"post-create-project-cmd":.*\n/m', '', $contents);

        if (json_decode($contents) === null) {
            // Only reachable when the key was the last entry of its block. Applied
            // conditionally so the common case is never rewritten.
            $contents = preg_replace('/,(\s*[}\]])/', '$1', $contents);
        }

        if (json_decode($contents) === null) {
            throw new Exception(
                'Removing post-create-project-cmd produced invalid composer.json: ' . json_last_error_msg()
            );
        }

        file_put_contents($workdir . '/composer.json', $contents);

        // ------------------------------------------------
        // Remove example files if not installing examples
        if (!$installExamples) {
            echo "Removing example files...\n";

            // Example files to remove
            $exampleFiles = [
                // Db Files
                'db/migrations/up/00001-create-table-examples.sql',
                'db/migrations/down/00000-rollback-table-examples.sql',
                // Project (Repository pattern, int PK)
                'src/Model/Project.php',
                'src/Repository/ProjectRepository.php',
                'src/Service/ProjectService.php',
                'src/Controller/ProjectController.php',
                'tests/Controller/ProjectTest.php',
                // Task (Repository pattern, UUID PK)
                'src/Model/Task.php',
                'src/Repository/TaskRepository.php',
                'src/Service/TaskService.php',
                'src/Controller/TaskController.php',
                'tests/Controller/TaskTest.php',
                // Note (ActiveRecord pattern)
                'src/Model/Note.php',
                'src/Controller/NoteController.php',
                'tests/Controller/NoteTest.php',
                // Sample files
                'src/Controller/SampleController.php',
                'src/Controller/SampleProtectedController.php',
                'tests/Controller/SampleTest.php',
                'tests/Controller/SampleProtectedTest.php',
                // Codegen E2E test (exercises the generator against an example table)
                'tests/Builder/CodegenTest.php',
            ];

            foreach ($exampleFiles as $file) {
                $fullPath = "$workdir/$file";
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                    echo "  Removed: $file\n";
                }
            }

            // Remove example frontend pages (only if the frontend is present)
            if (is_dir("$workdir/frontend")) {
                foreach ($this->frontendExampleFiles() as $file) {
                    $fullPath = "$workdir/$file";
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                        echo "  Removed: $file\n";
                    }
                }
                $this->stripFrontendExampleMarkers($workdir);
            }

            // Clean up config files. Each marks its example bindings, so cut those out
            // and leave whatever else the file holds exactly as the project ships it.
            // This matters beyond tidiness: DI::bind() calls class_exists() and throws on
            // a class we just deleted, which would break the container at startup.
            //
            // 07-controllers.php is absent on purpose: it holds an Autowire pattern rule
            // rather than per-class bindings, so it names no example class and needs no
            // cleaning.
            foreach (['04-repositories', '05-services'] as $config) {
                $this->stripPhpExampleMarkers("$workdir/config/dev/$config.php");
            }

            // Clean up index.html - remove example sections marked with <!-- Start Example --> and <!-- End Example -->
            $indexFile = "$workdir/public/index.html";
            if (file_exists($indexFile)) {
                $contents = file_get_contents($indexFile);

                // Remove all content between <!-- Start Example --> and <!-- End Example --> markers (HTML)
                $contents = preg_replace(
                    '/<!--\s*Start Example\s*-->.*?<!--\s*End Example\s*-->\s*/s',
                    '',
                    $contents
                );

                // Remove all content between // Start Example and // End Example markers (JavaScript)
                $contents = preg_replace(
                    '/\/\/\s*Start Example.*?\/\/\s*End Example\s*/s',
                    '',
                    $contents
                );

                file_put_contents($indexFile, $contents);
                echo "  Cleaned: public/index.html - removed example sections\n";
            }

            echo "Example files removed successfully.\n";
        }

        // ------------------------------------------------
        // Remove the frontend entirely if not installing it. The compose service
        // block is marker-wrapped in docker-compose.yml and is always stripped;
        // the frontend/ dir and its Dockerfile are removed when present.
        if (!$installFrontend) {
            echo "Removing frontend...\n";
            $this->stripComposeFrontendService($workdir);
            if (is_dir("$workdir/frontend")) {
                self::removeDir("$workdir/frontend");
                echo "  Removed: frontend/\n";
            }
            foreach (['docker/Dockerfile-html', 'docker/static-html-entrypoint.sh'] as $file) {
                if (file_exists("$workdir/$file")) {
                    unlink("$workdir/$file");
                    echo "  Removed: $file\n";
                }
            }
            echo "Frontend removed successfully.\n";
        }
    }

    /**
     * Side effects after the template is applied: regenerate the autoload map, generate the
     * OpenAPI docs and install the frontend dependencies.
     *
     * It does not create a git repository: the project may be created inside one (a monorepo,
     * a demo with several apps), where a nested .git would have to be removed by hand.
     */
    protected function finalize(): void
    {
        $workdir = getcwd();

        // ------------------------------------------------
        // Composer already installed the dependencies before this hook ran, but the
        // namespace has just been rewritten — the autoload map must be regenerated
        // before anything (including the OpenAPI generator) can load the classes.
        $warnings = [];
        passthru('composer dump-autoload', $status);
        if ($status !== 0) {
            $warnings[] = "The autoload map could not be regenerated. Run: composer dump-autoload";
        }
        passthru('composer run openapi', $status);
        if ($status !== 0) {
            $warnings[] = "The OpenAPI spec was not generated. Run: composer run openapi";
        }

        // Install frontend dependencies if the frontend was kept. npm/bun must exist
        // *where Composer itself runs* — running create-project inside a PHP-only
        // container (a dockerised `composer`, for instance) has no Node, and the SPA
        // would silently end up without its dependencies. Say so instead.
        if (is_dir("$workdir/frontend")) {
            passthru('cd frontend && (npm install || bun install)', $status);
            if ($status !== 0) {
                $warnings[] = "Frontend dependencies were NOT installed (no npm/bun where "
                    . "Composer ran). The API is unaffected. Run: cd frontend && npm install";
            }
        }

        if ($warnings !== []) {
            echo "\n" . str_repeat('!', 72) . "\n";
            echo "The project was created, but some steps did not complete:\n\n";
            foreach ($warnings as $warning) {
                echo "  - $warning\n";
            }
            echo str_repeat('!', 72) . "\n\n";
        }
    }

    /**
     * Generate a random JWT secret. Standard base64 of 64 random bytes — the
     * consumer (JwtHashHmacSecret) base64-decodes it back to a 512-bit HS512 key.
     * Dependency-free replacement for JwtWrapper::generateSecret so it stays
     * independent of the autoload map this script rewrites.
     */
    protected static function generateSecret(): string
    {
        return base64_encode(random_bytes(64));
    }

    /**
     * Example pages shipped with the frontend, removed when install_examples=false.
     */
    protected function frontendExampleFiles(): array
    {
        return [
            'frontend/src/pages/examples/ProjectsList.jsx',
            'frontend/src/pages/examples/ProjectDetail.jsx',
            'frontend/src/pages/examples/TaskNotes.jsx',
            'frontend/src/lib/examplesApi.js',
        ];
    }

    /**
     * Remove every `// Start Example` … `// End Example` block from a PHP file.
     *
     * Editing the real file in place beats rewriting it from a string literal here: there
     * is one source of truth, so a binding added to the config cannot silently go missing
     * from generated example-free projects.
     */
    protected function stripPhpExampleMarkers(string $file): void
    {
        if (!file_exists($file)) {
            return;
        }

        $contents = file_get_contents($file);
        $stripped = preg_replace(
            '/^[ \t]*\/\/\s*Start Example\b.*?^[ \t]*\/\/\s*End Example\b[^\n]*\n/ms',
            '',
            $contents
        );

        if ($stripped === null || $stripped === $contents) {
            return;
        }

        // Cutting a block out from between two others leaves their blank lines adjacent.
        $stripped = preg_replace('/\n{3,}/', "\n\n", $stripped);

        file_put_contents($file, $stripped);
        echo "  Cleaned: " . basename($file) . " - removed example bindings\n";
    }

    /**
     * Strip the example-marked blocks ({/* &gt;&gt;&gt; examples * /} … {/* &lt;&lt;&lt; examples * /})
     * from the frontend router and navigation.
     */
    protected function stripFrontendExampleMarkers(string $workdir): void
    {
        $files = [
            'frontend/src/App.jsx',
            'frontend/src/components/AppNav.jsx',
            'frontend/src/pages/dashboard/Dashboard.jsx',
        ];
        foreach ($files as $file) {
            $full = "$workdir/$file";
            if (!file_exists($full)) {
                continue;
            }
            $contents = file_get_contents($full);
            // Remove whole lines from the ">>> examples" marker line through the
            // "<<< examples" marker line (inclusive), regardless of how they are
            // commented (// … or {/* … */}). Matching whole lines avoids leaving a
            // dangling "//" that would comment out the following statement.
            $contents = preg_replace(
                '/^[^\n]*>>>\s*examples[^\n]*\n.*?^[^\n]*<<<\s*examples[^\n]*\n/ms',
                '',
                $contents
            );
            file_put_contents($full, $contents);
            echo "  Cleaned: $file - removed example sections\n";
        }
    }

    /**
     * Remove the marker-wrapped `frontend` service from docker-compose.yml.
     */
    protected function stripComposeFrontendService(string $workdir): void
    {
        $compose = "$workdir/docker-compose.yml";
        if (!file_exists($compose)) {
            return;
        }
        $contents = file_get_contents($compose);
        $contents = preg_replace(
            '/[ \t]*#\s*>>>\s*frontend-service.*?#\s*<<<\s*frontend-service[ \t]*\n/s',
            '',
            $contents
        );
        file_put_contents($compose, $contents);
        echo "  Cleaned: docker-compose.yml - removed frontend service\n";
    }

    /**
     * The placeholder DBDRIVER_CONNECTION of staging and prod, written as a MySQL URI, for
     * $schema: "postgres://" for PostgreSQL, a file under db/ named after the database for SQLite.
     */
    protected static function placeholderConnection(string $contents, string $schema): string
    {
        return match ($schema) {
            'postgres' => str_replace('DBDRIVER_CONNECTION=mysql://', 'DBDRIVER_CONNECTION=postgres://', $contents),
            'sqlite' => (string)preg_replace_callback(
                '~^DBDRIVER_CONNECTION=mysql://[^\n]*/([^/\n]+)$~m',
                fn (array $match) => 'DBDRIVER_CONNECTION=' . self::buildConnectionString(['schema' => 'sqlite'], $match[1]),
                $contents
            ),
            default => $contents,
        };
    }

    /**
     * The PostgreSQL service and volume of docker-compose.yml, in place of the MySQL ones.
     */
    protected const POSTGRES_COMPOSE = [
        'database-service' => <<<'YAML'
  postgres-container:
    image: postgres:17
    container_name: postgres-container
    environment:
      POSTGRES_PASSWORD: mysqlp455w0rd
      TZ: UTC
    volumes:
      - postgres-volume:/var/lib/postgresql/data
    ports:
      - "5432:5432"
    networks:
      - net

YAML,
        'database-volume' => <<<'YAML'
volumes:
  postgres-volume:

YAML,
    ];

    /**
     * The database part of docker-compose.yml, between the database-service and database-volume
     * markers: the MySQL server as it is, the PostgreSQL one in its place, or nothing for SQLite,
     * whose databases are files in db/. The marker lines are removed.
     */
    protected static function composeDatabase(string $contents, string $schema): string
    {
        foreach (['database-service', 'database-volume'] as $marker) {
            $contents = (string)preg_replace_callback(
                '/[ \t]*#\s*>>>\s*' . $marker . '[^\n]*\n(.*?)[ \t]*#\s*<<<\s*' . $marker . '[^\n]*\n/s',
                fn (array $match) => match ($schema) {
                    'sqlite' => '',
                    'postgres' => rtrim(self::POSTGRES_COMPOSE[$marker], "\n") . "\n",
                    default => $match[1],
                },
                $contents
            );
        }
        return $contents;
    }

    /**
     * The host and user of the database service docker-compose.yml runs for $schema.
     *
     * @return array{host: string, user: string}
     */
    protected static function defaultServer(string $schema): array
    {
        return $schema === 'postgres'
            ? ['host' => 'postgres-container', 'user' => 'postgres']
            : ['host' => 'mysql-container', 'user' => 'root'];
    }

    /**
     * Put the SQL of the chosen database in db/. The MySQL SQL is db/ itself; every other database
     * has its own copy of each file in db/.dialects/<schema>/. db/.dialects/ is removed either way,
     * so the project keeps exactly one db/base.sql and one db/migrations/.
     *
     * @throws Exception
     */
    protected function applyDialect(string $workdir, string $schema): void
    {
        $dialects = "$workdir/db/.dialects";
        if (!is_dir($dialects)) {
            return;
        }

        if ($schema !== 'mysql') {
            $dialect = "$dialects/$schema";
            if (!is_dir($dialect)) {
                throw new Exception("There is no SQL for the database '$schema' in db/.dialects/");
            }
            foreach (array_diff(scandir($dialect), ['.', '..']) as $item) {
                $target = "$workdir/db/$item";
                if (is_dir($target)) {
                    self::removeDir($target);
                } elseif (file_exists($target)) {
                    unlink($target);
                }
                rename("$dialect/$item", $target);
            }
            echo "Database: db/ now holds the $schema SQL\n";
        }

        self::removeDir($dialects);
    }

    protected static function removeDir(string $dir): void
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = "$dir/$item";
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Load configuration from JSON file if exists
     * Checks multiple locations in priority order
     *
     * @param string $workdir
     * @return array|null
     */
    /**
     * Whether this script runs inside a container: Docker creates /.dockerenv, and Podman
     * /run/.containerenv.
     *
     * @param list<string> $markers
     */
    protected static function isInsideContainer(array $markers = ['/.dockerenv', '/run/.containerenv']): bool
    {
        foreach ($markers as $marker) {
            if (file_exists($marker)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The answers for an unattended setup, from the first setup.json found: the file named by
     * SETUP_JSON, the parent directory (where create-project was run), then ~/.gluo/setup.json.
     *
     * A setup.json that is found but cannot be read as JSON is an error, and so is SETUP_JSON
     * naming a file that is not there: falling back to the questions would silently create a
     * project with the defaults.
     *
     * @throws Exception
     */
    protected static function loadConfigFromJson(string $workdir): ?array
    {
        $setupJson = getenv('SETUP_JSON');
        if (!empty($setupJson)) {
            if (!is_file($setupJson) || !is_readable($setupJson)) {
                throw new Exception(
                    "SETUP_JSON is '$setupJson', but that file cannot be read here. It has to be "
                    . "visible where Composer runs: a Composer running in a container only sees the "
                    . "directories mounted into it, usually just the current one."
                );
            }
            return self::readConfigFile($setupJson);
        }

        $locations = [
            // Parent directory (where the user ran composer create-project)
            dirname($workdir) . '/setup.json',

            // User's home directory
            (getenv('HOME') ?: getenv('USERPROFILE')) . '/.gluo/setup.json',
        ];

        foreach ($locations as $configFile) {
            if (is_file($configFile)) {
                return self::readConfigFile($configFile);
            }
        }

        return null;
    }

    /**
     * @throws Exception
     */
    protected static function readConfigFile(string $configFile): array
    {
        $config = json_decode((string)file_get_contents($configFile), true);
        if (!is_array($config)) {
            $error = json_last_error() === JSON_ERROR_NONE ? 'it is not a JSON object' : json_last_error_msg();
            throw new Exception("Cannot read the setup from '$configFile': $error");
        }
        return $config;
    }

    protected static function buildConnectionString(array $dbConfig, string $database): string
    {
        $schema = strtolower($dbConfig['schema'] ?? 'mysql');
        $host = $dbConfig['host'] ?? '';
        $user = $dbConfig['user'] ?? '';
        $password = $dbConfig['password'] ?? '';
        $database = ltrim($database, '/');

        if ($schema === 'sqlite') {
            // A file under db/ of the project. byjg/config replaces {__ROOT__} with the project
            // root, so the same .env works on the host, in Docker and behind the web server.
            return 'sqlite://{__ROOT__}/db/' . ($database ?: 'database') . '.sqlite';
        }

        $hostPart = $host !== '' ? $host : 'localhost';
        $auth = '';
        if ($user !== '') {
            $auth = rawurlencode($user);
            if ($password !== '') {
                $auth .= ':' . rawurlencode($password);
            }
            $auth .= '@';
        }

        return sprintf('%s://%s%s/%s', $schema, $auth, $hostPart, $database);
    }

    protected static function replaceEnvValue(string $contents, string $key, string $value): string
    {
        $pattern = sprintf('/^%s=.*$/m', preg_quote($key, '/'));
        if (preg_match($pattern, $contents)) {
            return preg_replace($pattern, sprintf('%s=%s', $key, $value), $contents);
        }

        $newline = str_ends_with($contents, PHP_EOL) ? '' : PHP_EOL;
        return $contents . $newline . sprintf('%s=%s', $key, $value) . PHP_EOL;
    }

    /**
     * @param Event $event
     * @return void
     * @throws Exception
     */
    public static function run(Event $event)
    {
        $workdir = getcwd();
        $stdIo = $event->getIO();

        // Check for unattended mode via JSON config
        $config = self::loadConfigFromJson($workdir);
        $unattended = $config !== null;

        // Check if PHP is installed
        $phpCheck = shell_exec('php --version 2>&1');
        if (empty($phpCheck) || !str_contains($phpCheck, 'PHP')) {
            throw new Exception('PHP is not installed or not available in PATH. Please install PHP before proceeding.');
        }

        // Check if docker is installed (warning only)
        $dockerCheck = shell_exec('docker --version 2>&1');
        $dockerInstalled = !empty($dockerCheck) && str_contains($dockerCheck, 'Docker version');

        if (!$dockerInstalled && self::isInsideContainer()) {
            // A Composer running in a container (a dockerised composer) has no Docker of its own,
            // and cannot see the host's: saying that Docker is not installed would be wrong
            $stdIo->write("<info>Composer is running inside a container: Docker on the host could not be checked.</info>");
        } elseif (!$dockerInstalled) {
            $stdIo->write("");
            $stdIo->write("<warning>========================================================</warning>");
            $stdIo->write("<warning> WARNING: Docker is not installed</warning>");
            $stdIo->write("<warning>========================================================</warning>");
            $stdIo->write("<warning>Docker was not found on your system.</warning>");
            $stdIo->write("<warning>You will need Docker to run the containerized environment.</warning>");
            $stdIo->write("<warning></warning>");
            if (!$unattended) {
                $stdIo->write("<warning>You can:</warning>");
                $stdIo->write("<warning>  - Press Ctrl+C to abort and install Docker first</warning>");
                $stdIo->write("<warning>  - Continue without Docker (you'll need to set it up later)</warning>");
                $stdIo->write("<warning>========================================================</warning>");
                $stdIo->write("");
                $stdIo->ask('Press <ENTER> to continue or Ctrl+C to abort');
                $stdIo->write("");
            } else {
                $stdIo->write("<warning>Continuing in unattended mode...</warning>");
                $stdIo->write("<warning>========================================================</warning>");
                $stdIo->write("");
            }
        }

        $currentPhpVersion = PHP_MAJOR_VERSION . "." .PHP_MINOR_VERSION;
        $defaultDbConfig = [
            'schema' => 'mysql',
            'host' => 'mysql-container',
            'user' => 'root',
            'password' => 'mysqlp455w0rd',
            'dev_database' => 'localdev',
            'test_database' => 'localtest',
        ];
        $dbConfig = $defaultDbConfig;

        $validatePHPVersion = function ($arg) {
            $validPHPVersions = ['8.3', '8.4', '8.5'];
            if (in_array($arg, $validPHPVersions)) {
                return $arg;
            }
            throw new Exception('Only the PHP versions ' . implode(', ', $validPHPVersions) . ' are supported');
        };

        $validateNamespace = function ($arg) {
            if (empty($arg) || !preg_match('/^[A-Z][a-zA-Z0-9]*$/', $arg)) {
                throw new Exception('Namespace must be one word in CamelCase');
            }
            return $arg;
        };

        $validateComposer = function ($arg) {
            if (empty($arg) || !preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $arg)) {
                throw new Exception('Invalid Composer name');
            }
            return $arg;
        };

        $validateDbSchema = function ($arg) {
            $value = strtolower(trim($arg));
            if ($value === 'pgsql' || $value === 'postgresql') {
                $value = 'postgres';
            }
            $validSchemas = ['mysql', 'postgres', 'sqlite'];
            if (!in_array($value, $validSchemas, true)) {
                throw new Exception('Database schema must be one of: ' . implode(', ', $validSchemas));
            }
            // Driver availability is re-checked later when `composer migrate` runs.
            return $value;
        };

        $validateDbName = function ($arg) {
            $value = trim($arg);
            if ($value === '') {
                throw new Exception('Database name cannot be empty');
            }
            if (!preg_match('/^[A-Za-z0-9._\\/-]+$/', $value)) {
                throw new Exception('Database name may contain letters, numbers, ".", "-", "_" or "/"');
            }
            return $value;
        };

        $validateDbHost = function ($arg) {
            $value = trim($arg);
            if ($value === '') {
                throw new Exception('Database host cannot be empty');
            }
            return $value;
        };

        $validateTimeZone = function ($arg) {
            if (empty($arg) || !in_array($arg, timezone_identifiers_list())) {
                throw new Exception('Invalid Timezone');
            }
            return $arg;
        };

        $validateYesNo = function ($arg) {
            $arg = strtolower(trim($arg));
            if (!in_array($arg, ['yes', 'no', 'y', 'n'])) {
                throw new Exception('Please answer Yes or No (y/n)');
            }
            return in_array($arg, ['yes', 'y']);
        };

        $validateNonEmpty = function ($arg) {
            if (empty(trim($arg))) {
                throw new Exception('This field cannot be empty');
            }
            return trim($arg);
        };

        $maxRetries = 5;

        if ($unattended) {
            // Unattended mode - use config from JSON
            $stdIo->write("========================================================");
            $stdIo->write(" Setup Project - UNATTENDED MODE");
            $stdIo->write("========================================================");
            $stdIo->write("");
            $stdIo->write("Project Directory: " . $workdir);

            // Get values with defaults
            $phpVersion = $config['php_version'] ?? $currentPhpVersion;
            $namespace = $config['namespace'] ?? 'MyRest';
            $composerName = $config['composer_name'] ?? 'me/myrest';
            $timezone = $config['timezone'] ?? 'UTC';
            $installExamples = $config['install_examples'] ?? true;
            $installFrontend = $config['install_frontend'] ?? true;

            if (isset($config['mysql_connection'])) {
                $parts = parse_url($config['mysql_connection']);
                if ($parts === false || empty($parts['scheme'])) {
                    throw new Exception("Invalid mysql_connection in setup.json: expected scheme://user:pass@host/database");
                }
                $dbConfig['schema'] = strtolower($parts['scheme']);
                if (!empty($parts['host'])) {
                    $dbConfig['host'] = $parts['host'];
                }
                if (!empty($parts['user'])) {
                    $dbConfig['user'] = rawurldecode($parts['user']);
                }
                if (isset($parts['pass'])) {
                    $dbConfig['password'] = rawurldecode($parts['pass']);
                }
                $legacyDb = ltrim($parts['path'] ?? '', '/');
                if (!empty($legacyDb)) {
                    $dbConfig['dev_database'] = $legacyDb;
                    $dbConfig['test_database'] = $legacyDb;
                }
            }

            // Validate provided values (only if they were explicitly provided in JSON)
            if (isset($config['php_version'])) {
                try {
                    $phpVersion = $validatePHPVersion($phpVersion);
                } catch (Exception $e) {
                    throw new Exception("Invalid php_version in setup.json: " . $e->getMessage());
                }
            }

            if (isset($config['namespace'])) {
                try {
                    $namespace = $validateNamespace($namespace);
                } catch (Exception $e) {
                    throw new Exception("Invalid namespace in setup.json: " . $e->getMessage());
                }
            }

            if (isset($config['composer_name'])) {
                try {
                    $composerName = $validateComposer($composerName);
                } catch (Exception $e) {
                    throw new Exception("Invalid composer_name in setup.json: " . $e->getMessage());
                }
            }

            if (isset($config['timezone'])) {
                try {
                    $timezone = $validateTimeZone($timezone);
                } catch (Exception $e) {
                    throw new Exception("Invalid timezone in setup.json: " . $e->getMessage());
                }
            }

            if (isset($config['install_examples']) && !is_bool($config['install_examples'])) {
                throw new Exception("Invalid install_examples in setup.json: must be true or false (boolean)");
            }

            if (isset($config['install_frontend']) && !is_bool($config['install_frontend'])) {
                throw new Exception("Invalid install_frontend in setup.json: must be true or false (boolean)");
            }

            if (isset($config['db_schema'])) {
                try {
                    $dbConfig['schema'] = $validateDbSchema($config['db_schema']);
                } catch (Exception $e) {
                    throw new Exception("Invalid db_schema in setup.json: " . $e->getMessage());
                }
            } else {
                $dbConfig['schema'] = $validateDbSchema($dbConfig['schema']);
            }

            if ($dbConfig['schema'] !== 'sqlite' && !isset($config['mysql_connection'])) {
                // The host and user of the database service docker-compose.yml runs for this schema
                $dbConfig = array_merge($dbConfig, self::defaultServer($dbConfig['schema']));
            }

            if ($dbConfig['schema'] === 'sqlite') {
                // A file under db/: no server, no credentials
                $dbConfig['host'] = '';
                $dbConfig['user'] = '';
                $dbConfig['password'] = '';
            } elseif (isset($config['db_host'])) {
                try {
                    $dbConfig['host'] = $validateDbHost($config['db_host']);
                } catch (Exception $e) {
                    throw new Exception("Invalid db_host in setup.json: " . $e->getMessage());
                }
            } else {
                $dbConfig['host'] = $validateDbHost($dbConfig['host']);
            }

            if ($dbConfig['schema'] === 'sqlite') {
                // nothing to ask for
            } elseif (isset($config['db_user'])) {
                try {
                    $dbConfig['user'] = $validateNonEmpty($config['db_user']);
                } catch (Exception $e) {
                    throw new Exception("Invalid db_user in setup.json: " . $e->getMessage());
                }
            } else {
                $dbConfig['user'] = $validateNonEmpty($dbConfig['user']);
            }

            if ($dbConfig['schema'] === 'sqlite') {
                // nothing to ask for
            } elseif (isset($config['db_password'])) {
                try {
                    $dbConfig['password'] = $validateNonEmpty($config['db_password']);
                } catch (Exception $e) {
                    throw new Exception("Invalid db_password in setup.json: " . $e->getMessage());
                }
            } else {
                $dbConfig['password'] = $validateNonEmpty($dbConfig['password']);
            }

            if (isset($config['db_name_dev'])) {
                try {
                    $dbConfig['dev_database'] = $validateDbName($config['db_name_dev']);
                } catch (Exception $e) {
                    throw new Exception("Invalid db_name_dev in setup.json: " . $e->getMessage());
                }
            } else {
                $dbConfig['dev_database'] = $validateDbName($dbConfig['dev_database']);
            }

            if (isset($config['db_name_test'])) {
                try {
                    $dbConfig['test_database'] = $validateDbName($config['db_name_test']);
                } catch (Exception $e) {
                    throw new Exception("Invalid db_name_test in setup.json: " . $e->getMessage());
                }
            } else {
                $dbConfig['test_database'] = $validateDbName($dbConfig['test_database']);
            }

            $stdIo->write("PHP Version: $phpVersion");
            $stdIo->write("Namespace: $namespace");
            $stdIo->write("Composer name: $composerName");
            $stdIo->write("Database schema: " . $dbConfig['schema']);
            $stdIo->write("Database host: " . ($dbConfig['schema'] === 'sqlite' ? '(not used)' : $dbConfig['host']));
            $stdIo->write("Database user: " . ($dbConfig['schema'] === 'sqlite' ? '(not used)' : $dbConfig['user']));
            $stdIo->write("Dev database: " . $dbConfig['dev_database']);
            $stdIo->write("Test database: " . $dbConfig['test_database']);
            $stdIo->write("Timezone: $timezone");
            $stdIo->write("Install Frontend: " . ($installFrontend ? 'Yes' : 'No'));
            $stdIo->write("Install Examples: " . ($installExamples ? 'Yes' : 'No'));
            $stdIo->write("");
        } else {
            // Interactive mode
            $stdIo->write("========================================================");
            $stdIo->write(" Setup Project");
            $stdIo->write(" Answer the questions below");
            $stdIo->write("========================================================");
            $stdIo->write("");
            $stdIo->write("Project Directory: " . $workdir);

            $phpVersion = $stdIo->askAndValidate("PHP Version [$currentPhpVersion]: ", $validatePHPVersion, $maxRetries, $currentPhpVersion);
            $namespace = $stdIo->askAndValidate('Project namespace [MyRest]: ', $validateNamespace, $maxRetries, 'MyRest');
            $composerName = $stdIo->askAndValidate('Composer name [me/myrest]: ', $validateComposer, $maxRetries, 'me/myrest');
            $dbSchema = $stdIo->askAndValidate(
                'Database schema (mysql, postgres, sqlite) [mysql]: ',
                $validateDbSchema,
                $maxRetries,
                $defaultDbConfig['schema']
            );
            if ($dbSchema === 'sqlite') {
                $dbHost = '';
                $dbUser = '';
                $dbPassword = '';
                $stdIo->write("<info>SQLite selected: the databases are files under db/, with no host or credentials.</info>");
            } else {
                $server = self::defaultServer($dbSchema);
                $dbHost = $stdIo->askAndValidate(
                    "Database host [{$server['host']}]: ",
                    $validateDbHost,
                    $maxRetries,
                    $server['host']
                );
                $dbUser = $stdIo->askAndValidate(
                    "Database user [{$server['user']}]: ",
                    $validateNonEmpty,
                    $maxRetries,
                    $server['user']
                );
                $dbPassword = $stdIo->askAndValidate(
                    "Database password [{$defaultDbConfig['password']}]: ",
                    $validateNonEmpty,
                    $maxRetries,
                    $defaultDbConfig['password']
                );
            }
            $devDatabase = $stdIo->askAndValidate(
                "Dev database name [{$defaultDbConfig['dev_database']}]: ",
                $validateDbName,
                $maxRetries,
                $defaultDbConfig['dev_database']
            );
            $testDatabase = $stdIo->askAndValidate(
                "Test database name [{$defaultDbConfig['test_database']}]: ",
                $validateDbName,
                $maxRetries,
                $defaultDbConfig['test_database']
            );
            $timezone = $stdIo->askAndValidate('Timezone [UTC]: ', $validateTimeZone, $maxRetries, 'UTC');
            $installFrontend = $stdIo->askAndValidate('Install Frontend (Vite app) [Yes]: ', $validateYesNo, $maxRetries, 'Yes');
            $installExamples = $stdIo->askAndValidate('Install Examples [Yes]: ', $validateYesNo, $maxRetries, 'Yes');
            $stdIo->ask('Press <ENTER> to continue');

            $dbConfig = [
                'schema' => $dbSchema,
                'host' => $dbHost,
                'user' => $dbUser,
                'password' => $dbPassword,
                'dev_database' => $devDatabase,
                'test_database' => $testDatabase,
            ];
        }

        $script = new PostCreateScript();
        $script->execute($workdir, $namespace, $composerName, $phpVersion, $dbConfig, $timezone, $installExamples, $installFrontend);
    }
}
