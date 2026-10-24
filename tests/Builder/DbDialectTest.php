<?php

namespace Tests\Builder;

use Builder\DbDialect;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * `php gluo db:dialect <database>` writes the database files of the template from .db-dialects/.
 * Runs against a copy of .db-dialects/ in a temporary project root.
 */
class DbDialectTest extends TestCase
{
    protected string $root;
    protected string $dialects;

    protected function setUp(): void
    {
        $this->dialects = (string)realpath(__DIR__ . '/../../.db-dialects');
        if (!is_dir($this->dialects)) {
            $this->markTestSkipped('.db-dialects/ is missing: requires the full repository checkout.');
        }

        $this->root = sys_get_temp_dir() . '/db-dialect-' . uniqid();
        mkdir($this->root);
        shell_exec('cp -r ' . escapeshellarg($this->dialects) . ' ' . escapeshellarg("$this->root/.db-dialects"));
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            shell_exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    public function testWritesTheFilesOfTheDatabase(): void
    {
        DbDialect::copy($this->root, 'postgres');

        $this->assertFileEquals("$this->dialects/docker-compose.yml", "$this->root/docker-compose.yml");
        $this->assertFileEquals("$this->dialects/postgres/docker-compose.yml", "$this->root/docker-compose.database.yml");
        $this->assertFileEquals("$this->dialects/postgres/db/base.sql", "$this->root/db/base.sql");
        $this->assertFileEquals(
            "$this->dialects/postgres/db/migrations/up/00001-create-table-examples.sql",
            "$this->root/db/migrations/up/00001-create-table-examples.sql"
        );
    }

    /**
     * Switching database replaces the SQL of the previous one, migrations included, and leaves
     * the rest of db/ alone: a SQLite database file is data, not template.
     */
    public function testSwitchingReplacesTheSqlAndKeepsTheRestOfDb(): void
    {
        DbDialect::copy($this->root, 'mysql');
        file_put_contents("$this->root/db/migrations/up/00002-only-in-the-old-database.sql", 'select 1;');
        file_put_contents("$this->root/db/localdev.sqlite", 'data');

        DbDialect::copy($this->root, 'sqlite');

        $this->assertFileEquals("$this->dialects/sqlite/db/base.sql", "$this->root/db/base.sql");
        $this->assertFileEquals("$this->dialects/sqlite/docker-compose.yml", "$this->root/docker-compose.database.yml");
        $this->assertFileDoesNotExist("$this->root/db/migrations/up/00002-only-in-the-old-database.sql");
        $this->assertSame('data', file_get_contents("$this->root/db/localdev.sqlite"));
    }

    public function testRejectsAnUnknownDatabase(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Unknown database 'oracle'");

        DbDialect::copy($this->root, 'oracle');
    }
}
