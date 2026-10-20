<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * tests/bootstrap.php keeps the tests on the test database: a DBDRIVER_CONNECTION set in the
 * environment (the rest container sets the dev one) only chooses the server.
 */
class BootstrapTest extends TestCase
{
    /**
     * The DBDRIVER_CONNECTION the bootstrap leaves, run in its own process with $connection set.
     */
    protected function connectionAfterBootstrap(?string $connection): string
    {
        $script = 'require ' . var_export(__DIR__ . '/bootstrap.php', true) . '; echo getenv("DBDRIVER_CONNECTION");';
        $env = getenv();
        unset($env['DBDRIVER_CONNECTION']);
        if ($connection !== null) {
            $env['DBDRIVER_CONNECTION'] = $connection;
        }

        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w']], $pipes, null, $env);
        $output = (string)stream_get_contents($pipes[1]);
        proc_close($process);

        return $output;
    }

    /**
     * The server comes from the variable; the database is the test database of config/test
     * (`/localtest` on a server, `db/localtest.sqlite` for SQLite: the name is the same).
     */
    public function testKeepsTheServerAndForcesTheTestDatabase(): void
    {
        preg_match('/^DBDRIVER_CONNECTION=(.*)$/m', (string)file_get_contents(__DIR__ . '/../config/test/credentials.env'), $match);
        $testDatabase = pathinfo((string)parse_url($match[1], PHP_URL_PATH), PATHINFO_FILENAME);

        $this->assertSame(
            'mysql://root:secret@gluo-database/' . $testDatabase,
            $this->connectionAfterBootstrap('mysql://root:secret@gluo-database/localdev')
        );
    }

    public function testASqliteConnectionIsIgnored(): void
    {
        $this->assertSame('', $this->connectionAfterBootstrap('sqlite://{__ROOT__}/db/localdev.sqlite'));
    }

    public function testWithoutTheVariableTheTestConfigApplies(): void
    {
        $this->assertSame('', $this->connectionAfterBootstrap(null));
    }
}
