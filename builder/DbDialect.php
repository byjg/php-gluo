<?php

namespace Builder;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The database part of the template lives in .db-dialects/: docker-compose.yml, with what every
 * database shares, and one folder per database with the docker-compose.yml of its server and its
 * db/ (base.sql and migrations). This class writes the files of one database to the project root.
 *
 * Template only: in this repository the files it writes are not versioned (.gitignore). A
 * created project keeps the files of the database chosen, and loses .db-dialects/ and this class.
 */
class DbDialect
{
    public const DIALECTS = ['mysql', 'postgres', 'sqlite'];

    /**
     * Write docker-compose.yml, docker-compose.database.yml, db/base.sql and db/migrations/ for
     * a database, from .db-dialects/. They are overwritten; anything else in db/ (a SQLite
     * database file) is kept.
     *
     * @param string $dialect mysql, postgres or sqlite
     * @throws Exception
     */
    public function apply(string $dialect = 'mysql'): void
    {
        self::copy(dirname(__DIR__), $dialect);
        echo "Database files written for $dialect\n";
    }

    /**
     * @throws Exception
     */
    public static function copy(string $root, string $dialect): void
    {
        if (!in_array($dialect, self::DIALECTS, true)) {
            throw new Exception("Unknown database '$dialect'. Use one of: " . implode(', ', self::DIALECTS));
        }

        $source = "$root/.db-dialects";
        if (!is_dir("$source/$dialect")) {
            throw new Exception("There is no .db-dialects/$dialect/ in $root");
        }

        copy("$source/docker-compose.yml", "$root/docker-compose.yml");
        copy("$source/$dialect/docker-compose.yml", "$root/docker-compose.database.yml");

        if (!is_dir("$root/db")) {
            mkdir("$root/db", 0755, true);
        }
        foreach (array_diff(scandir("$source/$dialect/db"), ['.', '..']) as $item) {
            $target = "$root/db/$item";
            if (is_dir($target)) {
                self::removeDir($target);
            } elseif (file_exists($target)) {
                unlink($target);
            }
            self::copyPath("$source/$dialect/db/$item", $target);
        }
    }

    protected static function copyPath(string $source, string $target): void
    {
        if (is_file($source)) {
            copy($source, $target);
            return;
        }

        mkdir($target, 0755, true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $path = $target . '/' . $iterator->getSubPathname();
            $item->isDir() ? mkdir($path, 0755, true) : copy($item->getPathname(), $path);
        }
    }

    public static function removeDir(string $dir): void
    {
        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $path = "$dir/$item";
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
